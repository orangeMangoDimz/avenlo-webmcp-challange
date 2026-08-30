<?php
/**
 * Payment Settlement Service
 *
 * 集中处理 deposit / withdrawal 的资金结算流程：
 *   - 入金成功 / 拒绝 / 客户撤回
 *   - 出金成功 / 拒绝 / 客户撤回
 *   - 平台账户的入金、扣款、回滚
 *   - 状态变更后的客户端通知
 *
 * 设计目标：所有调用方（DepositController / WithdrawalController / PaymentsProcessorCallbackController
 * 以及未来的 cron / queue worker）共用同一份业务逻辑，避免分散写多套结算路径。
 *
 * 该 service 自身管理依赖（参考 CommissionOrderService、WalletBalanceService 的写法）。
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/RejectionReason.php';
require_once __DIR__ . '/../models/PaymentProcessorCallbackLog.php';
require_once __DIR__ . '/../services/TradingAccountAmountService.php';
require_once __DIR__ . '/../services/CommissionOrderService.php';
require_once __DIR__ . '/../services/PaymentProcessorRequestLogService.php';

class PaymentSettlementService
{
    private $db;
    private $depositModel;
    private $withdrawalModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $tradingAccountAmountService;
    private $rejectionReasonModel;
    private $callbackLogModel;
    private $requestLogService;
    private $financePro;
    private $financeProClient;
    private $mt5;
    private $mt5Client;
    private $mt4;
    private $mt4Client;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->depositModel = new Deposit();
        $this->withdrawalModel = new Withdrawal();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $this->tradingAccountAmountService = new TradingAccountAmountService();
        $this->rejectionReasonModel = new RejectionReason();
        $this->callbackLogModel = new PaymentProcessorCallbackLog();
        $this->requestLogService = new PaymentProcessorRequestLogService();

        $appConfig = require __DIR__ . '/../config/app.php';
        $this->financePro = $appConfig['integrations']['finance_pro'] ?? [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
        $this->mt5 = $appConfig['integrations']['mt5'] ?? [];
        $this->mt5Client = new Mt5ApiClient($this->mt5);
        $this->mt4 = $appConfig['integrations']['mt4'] ?? [];
        $this->mt4Client = new Mt4ApiClient($this->mt4);
    }

    // ====================================================================
    // Deposit
    // ====================================================================

    /**
     * 入金审批成功的统一流程，所有入金通过的入口都应走这里：
     *   1. 必要时调用平台 API 给交易账户入金（FinancePro / MT5）
     *   2. spApproveDeposit 把 deposits.status 置为 completed
     *   3. 持久化平台快照（amountScale / platformAmount / displayUnit）
     *   4. 触发 IB 返佣 CommissionOrderService::createFromDeposit
     *      —— 严格依赖 status='completed'，必须放在 SP 之后
     *   5. 发送客户端入金通过通知
     *
     * 一致性保证：平台入金成功后若 SP 失败，会先回滚平台入金再抛出，
     * 避免出现「平台账户已入账但 CRM 仍是 pending」的脏状态。
     * IB 返佣 / 通知失败仅记录日志，不阻断入金成功。
     *
     * @param array       $deposit     已加载的 pending 状态 deposit 行
     * @param int         $operatorId  审批人 userId；自动审批传 0
     * @param string|null $adminNotes
     * @param string      $source      'approve' | 'bulk-approve' | 'auto-approve'
     *                                  用来区分平台备注和回滚 contextId，方便 MT5 日志排查
     * @return array  spApproveDeposit 后通过 getDepositDetails 取到的最新 deposit
     * @throws RuntimeException 平台入金或 spApproveDeposit 失败（已尽量回滚平台入金）
     */
    public function markDepositSuccess(
        array $deposit,
        int $operatorId,
        ?string $adminNotes,
        string $source
    ): array {
        $depositId = (int)($deposit['id'] ?? 0);
        if ($depositId <= 0) {
            throw new RuntimeException('Invalid deposit id');
        }

        $platformOperation = null;
        if ($this->depositRequiresPlatformCredit($deposit)) {
            try {
                $platformOperation = $this->executeDepositPlatformCredit(
                    $deposit,
                    'CRM deposit ' . $source . ' #' . $depositId
                );
            } catch (Exception $e) {
                throw new RuntimeException('Failed to call target platform deposit: ' . $e->getMessage(), 0, $e);
            }
        }

        try {
            $stmt = $this->db->query("CALL spApproveDeposit(:depositId, :approvedBy, :adminNotes)", [
                'depositId' => $depositId,
                'approvedBy' => $operatorId,
                'adminNotes' => $adminNotes
            ]);
            // 消费存储过程结果集，避免同一连接上后续查询错读
            if ($stmt) {
                $stmt->closeCursor();
            }
            $this->persistDepositPlatformSnapshot($depositId, $platformOperation);
        } catch (Exception $e) {
            // SP 失败时回滚平台入金，避免账户余额与 CRM 状态不一致
            if ($platformOperation !== null) {
                $rollbackContextId = $source === 'approve'
                    ? (string)$depositId
                    : ($source === 'bulk-approve'
                        ? 'bulk-' . $depositId
                        : 'auto-' . $depositId);
                $this->rollbackDepositPlatformCredit($platformOperation, $rollbackContextId);
            }
            throw new RuntimeException('Failed to approve deposit: ' . $e->getMessage(), 0, $e);
        }

        // IB 返佣依赖 deposits.status='completed'，必须在 spApproveDeposit 之后
        // 失败仅记录日志，不阻断入金成功
        try {
            $commissionService = new CommissionOrderService();
            $commissionService->createFromDeposit($depositId);
        } catch (Exception $e) {
            Logger::error('CommissionOrder createFromDeposit failed for deposit ' . $depositId . ': ' . $e->getMessage());
        }

        $updatedDeposit = $this->depositModel->getDepositDetails($depositId);

        try {
            $this->sendDepositStatusNotice($updatedDeposit, 'approved', [
                'adminNotes' => $adminNotes,
                'operatorId' => $operatorId
            ]);
        } catch (Exception $e) {
            Logger::error('Failed to create deposit approval notice for deposit ' . $depositId . ': ' . $e->getMessage());
        }

        return $updatedDeposit;
    }

    /**
     * 入金 拒绝 / 客户端撤回 的统一流程：
     *   - mode='reject' (后台)：spRejectDeposit + 发送 rejected 通知
     *   - mode='cancel' (客户)：spCancelDeposit + 不发通知（保留原行为）
     *
     * 不需要回滚平台入金：spRejectDeposit / spCancelDeposit 都强制要求 status='pending'，
     * 即此时尚未走过 executeDepositPlatformCredit，根本没有平台金额可以回滚。
     * 通知失败仅记录日志，不阻断状态变更。
     *
     * @param array  $deposit     已加载的 pending 状态 deposit 行
     * @param string $mode        'reject' | 'cancel'
     * @param int    $operatorId  reject 模式 = 审批人 userId；cancel 模式 = 客户 userId
     * @param array  $payload     reject 模式需要：rejectionReasonId, rejectionNotes, customReason,
     *                            adminNotes, rejectionReasonTitle
     *                            cancel 模式需要：cancelReason
     * @return array  SP 执行后通过 getDepositDetails 取到的最新 deposit
     * @throws RuntimeException SP 执行失败，或 mode 非法
     */
    public function markDepositRejected(
        array $deposit,
        string $mode,
        int $operatorId,
        array $payload
    ): array {
        $depositId = (int)($deposit['id'] ?? 0);
        if ($depositId <= 0) {
            throw new RuntimeException('Invalid deposit id');
        }

        if ($mode === 'reject') {
            $this->db->query(
                "CALL spRejectDeposit(:depositId, :rejectedBy, :rejectionReasonId, :rejectionNotes, :customReason, :adminNotes)",
                [
                    'depositId' => $depositId,
                    'rejectedBy' => $operatorId,
                    'rejectionReasonId' => $payload['rejectionReasonId'] ?? null,
                    'rejectionNotes' => $payload['rejectionNotes'] ?? null,
                    'customReason' => $payload['customReason'] ?? null,
                    'adminNotes' => $payload['adminNotes'] ?? null
                ]
            );
        } elseif ($mode === 'cancel') {
            $this->db->query(
                "CALL spCancelDeposit(:depositId, :userId, :cancelReason)",
                [
                    'depositId' => $depositId,
                    'userId' => $operatorId,
                    'cancelReason' => $payload['cancelReason'] ?? null
                ]
            );
        } else {
            throw new RuntimeException('Invalid deposit rejection mode: ' . $mode);
        }

        $updatedDeposit = $this->depositModel->getDepositDetails($depositId);

        if ($mode === 'reject') {
            try {
                $this->sendDepositStatusNotice($updatedDeposit, 'rejected', [
                    'rejectionReasonId' => $payload['rejectionReasonId'] ?? null,
                    'rejectionReasonTitle' => $payload['rejectionReasonTitle'] ?? null,
                    'rejectionNotes' => $payload['rejectionNotes'] ?? null,
                    'customReason' => $payload['customReason'] ?? null,
                    'adminNotes' => $payload['adminNotes'] ?? null,
                    'operatorId' => $operatorId
                ]);
            } catch (Exception $e) {
                Logger::error('Failed to create deposit rejection notice for deposit ' . $depositId . ': ' . $e->getMessage());
            }
        }

        return $updatedDeposit;
    }

    /**
     * Apply a terminal status confirmed by the provider's status inquiry/callback.
     *
     * This intentionally does not use the admin rejection flow: provider expiry
     * and cancellation are terminal payment outcomes, not manual rejection, and
     * therefore must not create a client rejection notification.
     *
     * @param array  $deposit        Current deposit row.
     * @param string $status         'expired' | 'cancelled'.
     * @param string $reason         Internal/provider-facing reason.
     * @param string $providerStatus Raw provider status code.
     * @param string $providerSource Provider key written into status-history metadata.
     * @return array Updated deposit details.
     */
    public function markDepositProviderTerminal(
        array $deposit,
        string $status,
        string $reason,
        string $providerStatus,
        string $providerSource = '5pay'
    ): array {
        $depositId = (int)($deposit['id'] ?? 0);
        if ($depositId <= 0) {
            throw new RuntimeException('Invalid deposit id');
        }
        if (!in_array($status, ['expired', 'cancelled'], true)) {
            throw new RuntimeException('Invalid provider terminal deposit status: ' . $status);
        }

        $providerSource = strtolower(trim($providerSource));
        if ($providerSource === '') {
            $providerSource = '5pay';
        }

        $currentStatus = (string)($deposit['status'] ?? '');
        if ($currentStatus === $status) {
            return $this->depositModel->getDepositDetails($depositId) ?: $deposit;
        }
        if ($currentStatus !== '' && !in_array($currentStatus, ['unpaid', 'pending', 'processing'], true)) {
            throw new RuntimeException(
                'Cannot apply provider terminal status to deposit ' . $depositId
                . ' with current status ' . $currentStatus
            );
        }

        try {
            $stmt = $this->db->query(
                'CALL spMarkDepositProviderTerminal(:depositId, :newStatus, :reason, :providerStatus, :providerSource)',
                [
                    'depositId' => $depositId,
                    'newStatus' => $status,
                    'reason' => $reason,
                    'providerStatus' => $providerStatus,
                    'providerSource' => $providerSource,
                ]
            );
            if ($stmt) {
                $stmt->closeCursor();
            }
        } catch (Throwable $e) {
            // A callback and the scheduler can race. Treat a concurrent
            // transition to the same terminal state as an idempotent success.
            $latest = $this->depositModel->getDepositDetails($depositId);
            if (($latest['status'] ?? '') !== $status) {
                throw new RuntimeException(
                    'Failed to apply provider terminal deposit status: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
            return $latest ?: $deposit;
        }

        return $this->depositModel->getDepositDetails($depositId) ?: $deposit;
    }

    /**
     * PSP callback 金额对齐：部分 PSP 不严格按下单金额收款（汇率取整、最小步进等），
     * 实际结算 total 跟我们记录的 quotedAmount 不一致时，由调用方在需要时主动调用此方法，
     * 反推真实的 amount 并改写 deposit 行，让后续审批 / IB 返佣 / 平台入金都基于真实金额。
     *
     * 公式（与原下单 chargeToClient=true 路径一致）：
     *   原:    total = (amount + platformFee) * exchangeRate
     *   反推:  amount = settledTotal / exchangeRate - platformFee
     *
     * 注意：
     *   - 汇率与 platformFee 都按 deposit 行已记录的值原样使用，不重新拉汇率、不重算费率
     *   - 同时清空 platformAmount / amountScale / displayUnit 平台快照，让 markDepositSuccess
     *     在调平台入金 API 时按新 amount 重新换算，避免用旧值打错金额到交易账户
     *   - 仅允许 status='pending' 时调用（completed/rejected 已定盘，不应再改金额）
     *
     * @param array $deposit       已加载的 pending 状态 deposit 行
     * @param float $settledTotal  PSP 实际结算的 total（gateway 货币，单位与 deposit.quotedAmount 一致）
     * @return array  更新后通过 getDepositDetails 取到的最新 deposit
     * @throws RuntimeException 状态不对、汇率缺失、反推结果非正
     */
    public function reconcileDepositAmountFromCallback(
        array $deposit,
        float $settledTotal
    ): array {
        $depositId = (int)($deposit['id'] ?? 0);
        if ($depositId <= 0) {
            throw new RuntimeException('Invalid deposit id');
        }
        if (($deposit['status'] ?? '') !== 'pending') {
            throw new RuntimeException(
                'Only pending deposits can be reconciled, current status: ' . (string)($deposit['status'] ?? '')
            );
        }

        $exchangeRate = (float)($deposit['exchangeRate'] ?? 0);
        if ($exchangeRate <= 0) {
            throw new RuntimeException('Deposit exchange rate is missing or invalid');
        }
        $platformFee = (float)($deposit['platformFee'] ?? 0);

        $newAmount = round($settledTotal / $exchangeRate - $platformFee, 2);

        if ($newAmount <= 0) {
            throw new RuntimeException(sprintf(
                'Reconciled amount is non-positive: settledTotal=%s, exchangeRate=%s, platformFee=%s',
                $settledTotal,
                $exchangeRate,
                $platformFee
            ));
        }

        // 平台快照清空：amount 已变，原先按旧 amount 算出来的 platformAmount/scale/unit
        // 都失效；交给 markDepositSuccess -> executeDepositPlatformCredit 在审批时按新值重算
        $this->depositModel->update($depositId, [
            'amount' => $newAmount,
            'quotedAmount' => $settledTotal,
            'platformAmount' => null,
            'amountScale' => null,
            'displayUnit' => null
        ]);

        return $this->depositModel->getDepositDetails($depositId);
    }


    // ====================================================================
    // Withdrawal
    // ====================================================================

    /**
     * 提款审批通过的统一流程：
     *   1. spApproveWithdrawal 把 withdrawals.status 置为 processing
     *   2. （可选）发送客户端审批通过通知
     *
     * 注意：审批阶段不涉及任何平台 API 调用——平台扣款在创建提款时就已经执行过了
     * （参见 executeWithdrawalPlatformDebit）。PSP 出金在 Approve 后发起，
     * completed 仅在 PSP 成功回调（或非 PSP 网关的手动完成）时写入。
     *
     * 此方法刻意不包含 dispatchOrdersBalanceSyncTask：bulk 入口依赖循环外只调一次的优化，
     * 调用方按自身场景在合适位置调度即可（也可以直接调用 needsBalanceSync / dispatchOrdersBalanceSyncTask）。
     *
     * @param array       $withdrawal  已加载的 pending 状态 withdrawal 行
     * @param int         $operatorId  审批人 userId
     * @param string|null $adminNotes
     * @param bool        $sendNotice  是否发送客户端通知；bulk 入口保留原行为传 false
     * @return array  spApproveWithdrawal 后通过 getWithdrawalDetails 取到的最新 withdrawal
     * @throws RuntimeException spApproveWithdrawal 失败
     */
    public function markWithdrawalSuccess(
        array $withdrawal,
        int $operatorId,
        ?string $adminNotes,
        bool $sendNotice = true
    ): array {
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($withdrawalId <= 0) {
            throw new RuntimeException('Invalid withdrawal id');
        }

        try {
            $this->db->query(
                "CALL spApproveWithdrawal(:withdrawalId, :approvedBy, :adminNotes)",
                [
                    'withdrawalId' => $withdrawalId,
                    'approvedBy' => $operatorId,
                    'adminNotes' => $adminNotes
                ]
            );
        } catch (Exception $e) {
            throw new RuntimeException('Failed to approve withdrawal: ' . $e->getMessage(), 0, $e);
        }

        $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId);

        if ($sendNotice) {
            // sendWithdrawalStatusNotice 内部已 catch Exception 兜底
            $this->sendWithdrawalStatusNotice(
                $updatedWithdrawal,
                'approved',
                [
                    'adminNotes' => $adminNotes,
                    'operatorId' => $operatorId
                ]
            );
        }

        return $updatedWithdrawal;
    }

    /**
     * 提款 拒绝 / 客户端撤回 的统一流程：
     *   - mode='reject' (后台)：回滚平台账户 → spRejectWithdrawal → 发送 rejected 通知
     *   - mode='cancel' (客户)：回滚平台账户 → spCancelWithdrawal → 不发通知（保留原行为）
     *
     * 注意：提款在创建时就已经从平台账户扣过钱了，即便 status 还是 pending，
     * 平台余额也已减少。因此 reject/cancel 都必须先把平台账户的钱补回去
     * （rollbackApprovedWithdrawalIfNeeded），再走对应 SP。
     *
     * 幂等：同一笔 withdrawal 并发/重复 reject（例如 PSP API fail + callback fail）
     * 只会回滚平台一次；已是 rejected/cancelled 时直接返回当前记录。
     *
     * 异常补偿：如果 rollback 已成功但 SP 执行失败，会再调用一次 executeWithdrawalPlatformDebit
     * 把钱重新扣回去，避免出现「平台余额已退还但 CRM 仍是 pending」的脏状态。
     *
     * @param array  $withdrawal  已加载的 withdrawal 行
     * @param string $mode        'reject' | 'cancel'
     * @param int    $operatorId  reject 模式 = 审批人 userId；cancel 模式 = 客户 userId
     * @param array  $payload     reject 模式需要：rejectionReasonId, rejectionNotes,
     *                            customReason, rejectionReasonTitle
     *                            cancel 模式需要：cancelReason
     * @return array  SP 执行后通过 getWithdrawalDetails 取到的最新 withdrawal
     * @throws RuntimeException 平台回滚失败、SP 执行失败、或 mode 非法
     */
    public function markWithdrawalRejected(
        array $withdrawal,
        string $mode,
        int $operatorId,
        array $payload
    ): array {
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($withdrawalId <= 0) {
            throw new RuntimeException('Invalid withdrawal id');
        }
        if ($mode !== 'reject' && $mode !== 'cancel') {
            throw new RuntimeException('Invalid withdrawal rejection mode: ' . $mode);
        }

        $lockName = 'crm_wd_reject_' . $withdrawalId;
        if (!$this->acquireNamedLock($lockName, 15)) {
            throw new RuntimeException('Failed to acquire withdrawal rejection lock #' . $withdrawalId);
        }

        try {
            $freshWithdrawal = $this->withdrawalModel->findById($withdrawalId);
            if (!$freshWithdrawal) {
                throw new RuntimeException('Withdrawal not found');
            }

            $currentStatus = (string)($freshWithdrawal['status'] ?? '');
            if (in_array($currentStatus, ['rejected', 'cancelled'], true)) {
                Logger::info('Skip duplicate withdrawal ' . $mode, [
                    'domain' => 'payment',
                    'withdrawalId' => $withdrawalId,
                    'transactionId' => $freshWithdrawal['transactionId'] ?? null,
                    'status' => $currentStatus,
                    'mode' => $mode,
                    'operatorId' => $operatorId,
                ], true);

                return $this->withdrawalModel->getWithdrawalDetails($withdrawalId) ?: $freshWithdrawal;
            }

            $rollbackResult = $this->rollbackApprovedWithdrawalIfNeeded($freshWithdrawal);
            if (!empty($rollbackResult['attempted']) && empty($rollbackResult['success'])) {
                $stage = $mode === 'reject' ? 'rejection' : 'cancellation';
                throw new RuntimeException(
                    'Failed to rollback trading account before ' . $stage . ': '
                    . (string)($rollbackResult['error'] ?? 'unknown error')
                );
            }

            try {
                if ($mode === 'reject') {
                    $this->db->query(
                        "CALL spRejectWithdrawal(:withdrawalId, :rejectedBy, :rejectionReasonId, :rejectionNotes, :customReason)",
                        [
                            'withdrawalId' => $withdrawalId,
                            'rejectedBy' => $operatorId,
                            'rejectionReasonId' => $payload['rejectionReasonId'] ?? null,
                            'rejectionNotes' => $payload['rejectionNotes'] ?? null,
                            'customReason' => $payload['customReason'] ?? null
                        ]
                    );
                } else {
                    $this->db->query(
                        "CALL spCancelWithdrawal(:withdrawalId, :userId, :cancelReason)",
                        [
                            'withdrawalId' => $withdrawalId,
                            'userId' => $operatorId,
                            'cancelReason' => $payload['cancelReason'] ?? null
                        ]
                    );
                }
            } catch (Exception $e) {
                if (
                    !empty($rollbackResult['attempted'])
                    && !empty($rollbackResult['success'])
                    && !empty($freshWithdrawal['tradingAccountId'])
                ) {
                    try {
                        $this->executeWithdrawalPlatformDebit(
                            (int)$freshWithdrawal['tradingAccountId'],
                            $this->calculateWithdrawalPlatformDebitAmount($freshWithdrawal),
                            'CRM withdrawal ' . $mode . ' compensate #' . $withdrawalId,
                            $freshWithdrawal,
                            $freshWithdrawal['transactionId'] ?? null
                        );
                    } catch (Exception $compensateException) {
                        throw new RuntimeException(
                            'Failed to ' . $mode . ' withdrawal after rollback. Compensation also failed: '
                            . $compensateException->getMessage(),
                            0,
                            $compensateException
                        );
                    }
                }
                throw new RuntimeException('Failed to ' . $mode . ' withdrawal: ' . $e->getMessage(), 0, $e);
            }

            $updatedWithdrawal = $this->withdrawalModel->getWithdrawalDetails($withdrawalId);

            if ($mode === 'reject') {
                $this->sendWithdrawalStatusNotice(
                    $updatedWithdrawal,
                    'rejected',
                    [
                        'rejectionReasonId' => $payload['rejectionReasonId'] ?? null,
                        'rejectionReasonTitle' => $payload['rejectionReasonTitle'] ?? null,
                        'rejectionNotes' => $payload['rejectionNotes'] ?? null,
                        'customReason' => $payload['customReason'] ?? null,
                        'operatorId' => $operatorId
                    ]
                );
            }

            if ($this->needsBalanceSync($updatedWithdrawal)) {
                $this->dispatchOrdersBalanceSyncTask();
            }

            return $updatedWithdrawal;
        } finally {
            $this->releaseNamedLock($lockName);
        }
    }


    // ====================================================================
    // Withdrawal 平台调用工具方法
    // ====================================================================

    /**
     * 调用平台 API 从交易账户扣款，用于：
     *   - 创建提款时（createWithdrawalRequest）
     *   - markWithdrawalRejected 内部 SP 失败后的补偿
     *
     * @return array|null 平台操作快照（用于回滚 / 持久化），不支持的平台返回 null
     * @throws Exception 平台调用失败
     */
    public function executeWithdrawalPlatformDebit($tradingAccountId, $amount, $commentPrefix, ?array $snapshot = null, $originOrderId = null)
    {
        $context = $this->tradingAccountAmountService->getAccountContext((int)$tradingAccountId);
        $accountId = (string)$context['providerAccountId'];
        $platformKey = (string)$context['platformKey'];
        $baseAmount = (float)$amount;
        $snapshot = $snapshot ?? [];
        $platformAmount = $this->resolveWithdrawalPlatformAmount($snapshot, $baseAmount, $context);
        $scale = $this->resolveWithdrawalPlatformScale($snapshot, $context);
        $unit = $this->resolveWithdrawalPlatformUnit($snapshot, $context);

        if ($platformKey === 'financepro') {
            if (!$this->financePro['balance']) {
                throw new RuntimeException('Trading Platform balance endpoint not configured');
            }

            $this->financeProClient->request(
                $this->financePro['balance'],
                'GET',
                [
                    'accountId' => $accountId,
                    'amount' => (string)$platformAmount,
                    'type' => '1',
                    'originOrderId' => (string)($originOrderId ?? ''),
                    'comment' => 'withdraw'
                ],
                [
                    'expect_success_key' => 'Success',
                    'success_value' => true,
                    'error_message_key' => 'ErrMsg'
                ]
            );

            return [
                'tradingAccountId' => (int)$tradingAccountId,
                'platformKey' => $platformKey,
                'providerAccountId' => $accountId,
                'amount' => $platformAmount,
                'baseAmount' => $baseAmount,
                'scale' => $scale,
                'unit' => $unit
            ];
        }

        if ($platformKey === 'mt5') {
            try {
                $balance = $this->mt5Client->getBalance($accountId);
                $available = (float)($balance['balance'] ?? 0);
                Logger::info('MT5 withdrawal debit check', [
                    'domain' => 'payment',
                    'tradingAccountId' => (int)$tradingAccountId,
                    'providerAccountId' => $accountId,
                    'baseAmount' => $baseAmount,
                    'scale' => $scale,
                    'unit' => $unit,
                    'platformAmount' => $platformAmount,
                    'availableBalance' => $available,
                    'rawBalance' => $balance,
                ], true);
                if ($available < $platformAmount) {
                    Logger::error('MT5 withdrawal blocked by insufficient balance', [
                        'tradingAccountId' => (int)$tradingAccountId,
                        'providerAccountId' => $accountId,
                        'baseAmount' => $baseAmount,
                        'scale' => $scale,
                        'unit' => $unit,
                        'platformAmount' => $platformAmount,
                        'availableBalance' => $available,
                        'shortfall' => $platformAmount - $available,
                    ]);
                    throw new RuntimeException('Insufficient MT5 balance');
                }

                $this->mt5Client->withdraw(
                    $accountId,
                    $platformAmount,
                    $commentPrefix
                );
            } finally {
                $this->mt5Client->disconnect();
            }

            return [
                'tradingAccountId' => (int)$tradingAccountId,
                'platformKey' => $platformKey,
                'providerAccountId' => $accountId,
                'amount' => $platformAmount,
                'baseAmount' => $baseAmount,
                'scale' => $scale,
                'unit' => $unit
            ];
        }

        if ($platformKey === 'mt4') {
            try {
                $balance = $this->mt4Client->getBalance($accountId);
                $available = (float)($balance['balance'] ?? 0);
                if ($available < $platformAmount) {
                    Logger::error('MT4 withdrawal blocked by insufficient balance', [
                        'tradingAccountId' => (int)$tradingAccountId,
                        'providerAccountId' => $accountId,
                        'platformAmount' => $platformAmount,
                        'availableBalance' => $available,
                        'shortfall' => $platformAmount - $available,
                    ]);
                    throw new RuntimeException('Insufficient MT4 balance');
                }

                $this->mt4Client->withdraw(
                    $accountId,
                    $platformAmount,
                    $commentPrefix,
                    $this->mt4IdemKey($originOrderId)
                );
            } finally {
                $this->mt4Client->disconnect();
            }

            return [
                'tradingAccountId' => (int)$tradingAccountId,
                'platformKey' => $platformKey,
                'providerAccountId' => $accountId,
                'amount' => $platformAmount,
                'baseAmount' => $baseAmount,
                'scale' => $scale,
                'unit' => $unit
            ];
        }

        return null;
    }

    /**
     * 创建提款时若 spCreateWithdrawal 失败，需要把已扣的平台金额补回去。
     * 与 markWithdrawalRejected 的 rollback 不同：这里以 executeWithdrawalPlatformDebit
     * 返回的 operation 为依据，反向调用一次平台 API。
     */
    public function rollbackWithdrawalPlatformDebit($operation, $originOrderId = null): array
    {
        if (!is_array($operation) || empty($operation['platformKey']) || empty($operation['providerAccountId'])) {
            return [
                'attempted' => false,
                'success' => true
            ];
        }

        try {
            if ($operation['platformKey'] === 'financepro') {
                if (!$this->financePro['balance']) {
                    throw new RuntimeException('Trading Platform balance endpoint not configured');
                }

                $this->financeProClient->request(
                    $this->financePro['balance'],
                    'GET',
                    [
                        'accountId' => (string)$operation['providerAccountId'],
                        'amount' => (string)$operation['amount'],
                        'type' => '0',
                        'originOrderId' => $this->financeProReversalOrderId($originOrderId),
                        'comment' => 'withdraw'
                    ],
                    [
                        'expect_success_key' => 'Success',
                        'success_value' => true,
                        'error_message_key' => 'ErrMsg'
                    ]
                );
            } elseif ($operation['platformKey'] === 'mt5') {
                try {
                    $this->mt5Client->deposit(
                        (string)$operation['providerAccountId'],
                        (float)$operation['amount'],
                        'CRM withdrawal create rollback #' . (int)($operation['tradingAccountId'] ?? 0)
                    );
                } finally {
                    $this->mt5Client->disconnect();
                }
            } elseif ($operation['platformKey'] === 'mt4') {
                try {
                    $this->mt4Client->deposit(
                        (string)$operation['providerAccountId'],
                        (float)$operation['amount'],
                        'CRM withdrawal create rollback #' . (int)($operation['tradingAccountId'] ?? 0),
                        $this->mt4IdemKey($originOrderId, '_rb')
                    );
                } finally {
                    $this->mt4Client->disconnect();
                }
            } else {
                return [
                    'attempted' => false,
                    'success' => true
                ];
            }

            return [
                'attempted' => true,
                'success' => true,
                'type' => (string)$operation['platformKey']
            ];
        } catch (Exception $e) {
            return [
                'attempted' => true,
                'success' => false,
                'type' => (string)$operation['platformKey'],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 把 executeWithdrawalPlatformDebit 返回的 operation 转成 spCreateWithdrawal
     * 需要的快照字段（amountScale / platformAmount / displayUnit）。
     */
    public function buildWithdrawalPlatformSnapshot(?array $platformOperation): array
    {
        if (!$platformOperation) {
            return [];
        }

        return [
            'amountScale' => isset($platformOperation['scale']) ? (float)$platformOperation['scale'] : null,
            'platformAmount' => isset($platformOperation['amount']) ? (float)$platformOperation['amount'] : null,
            'displayUnit' => $platformOperation['unit'] ?? null,
        ];
    }

    public function needsBalanceSync(array $withdrawal): bool
    {
        return !empty($withdrawal['tradingAccountId']);
    }

    /**
     * Fire-and-forget：通过 TCP socket 把 sync_orders_balances 任务投递给后台 worker。
     * 失败不抛出（业务流程不应因 sync 派发失败而中断）。
     */
    public function dispatchOrdersBalanceSyncTask(): void
    {
        try {
            require_once __DIR__ . '/DeveloperSettingsService.php';
            if (!(new DeveloperSettingsService())->shouldDispatchOrderBalanceSync()) {
                return;
            }

            $socket = @stream_socket_client(config_swoole_address(), $errno, $errstr, 0.5);
            if (!$socket) {
                return;
            }

            $payload = json_encode(array_merge([
                'type' => 'sync_orders_balances'
            ], class_exists('RequestLogContext') ? RequestLogContext::toTaskFields() : []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($payload === false) {
                fclose($socket);
                return;
            }

            @fwrite($socket, $payload . '$$$###');
            fclose($socket);
        } catch (Throwable $e) {
            // Balance sync dispatch failure should not block business flow.
        }
    }

    /**
     * 把第三方 PSP 回调（或 PSP 出金请求）的失败结果落到 paymentProcessorCallbackLogs 表，
     * 作为审计/排查依据。
     *
     * 这个方法是**纯 log 写入**，不嵌套在 reject 流程里——调用方应自己先把 deposit/withdrawal
     * 的状态切到 rejected（通过 markDepositRejected / markWithdrawalRejected），再调一次此方法
     * 单独把失败 payload 落库，便于排查和重放。
     *
     * 失败本身不抛异常 —— 不能因为审计写不进就阻断业务流程。
     *
     * @param string      $transactionType  'deposit' | 'withdrawal'
     * @param array       $row              对应的 deposit / withdrawal 行（取 id / transactionId / amount）
     * @param string      $provider         'ibeepay' | 'payment_asia' | ...
     * @param string      $reasonText       失败原因文案
     * @param array|null  $rawResponse      PSP 原始响应；写入 log.rawPayload 便于排查
     */
    public function logProcessorCallbackFailure(
        string $transactionType,
        array $row,
        string $provider,
        string $reasonText,
        ?array $rawResponse = null
    ): void {
        if ($transactionType !== 'deposit' && $transactionType !== 'withdrawal') {
            Logger::error('logProcessorCallbackFailure: invalid transactionType ' . $transactionType);
            return;
        }

        try {
            $payload = [
                'provider' => $provider,
                'transactionType' => $transactionType,
                'orderId' => isset($row['transactionId']) && $row['transactionId'] !== ''
                    ? (string)$row['transactionId']
                    : null,
                'callbackStatus' => 'failed',
                'amount' => isset($row['amount']) ? (string)$row['amount'] : null,
                'rawPayload' => $rawResponse !== null
                    ? json_encode($rawResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'isValid' => 1,
                'isProcessed' => 1,
                'processResult' => 'failed',
                'errorMessage' => mb_substr($reasonText, 0, 500),
                'processedAt' => date('Y-m-d H:i:s'),
            ];
            if ($transactionType === 'deposit') {
                $payload['depositId'] = (int)($row['id'] ?? 0);
            } else {
                $payload['withdrawalId'] = (int)($row['id'] ?? 0);
            }

            $providerOrderId = trim((string)($row['gatewayTransactionId'] ?? ''));
            $match = $this->requestLogService->findAttemptForCallback(
                $provider,
                $transactionType,
                $payload['depositId'] ?? null,
                $payload['withdrawalId'] ?? null,
                $payload['orderId'] ?? null,
                $providerOrderId !== '' ? $providerOrderId : null
            );
            if (!empty($match['requestLogId'])) {
                $payload['requestLogId'] = (int)$match['requestLogId'];
            }
            $payload['correlationMethod'] = $match['correlationMethod'] ?? 'unmatched';

            $this->callbackLogModel->create($payload);
        } catch (Throwable $e) {
            Logger::error(
                'Failed to log processor callback failure (' . $transactionType . ' #'
                . (int)($row['id'] ?? 0) . ', provider=' . $provider . '): ' . $e->getMessage()
            );
        }
    }

    /**
     * 解析对应 scope 下 reasonKey='custom' 的 rejectionReasonId。
     * PSP / 系统自动拒绝场景没有 admin 选的具体 reason，统一用 'custom' 兜底。
     */
    public function resolveCustomRejectionReasonId(string $scope): ?int
    {
        $reason = $this->rejectionReasonModel->findByKey('custom', $scope);
        $id = $reason['id'] ?? null;
        return $id !== null ? (int)$id : null;
    }

    // ====================================================================
    // Deposit 平台调用 - 私有 helper
    // ====================================================================

    private function depositRequiresPlatformCredit(array $deposit): bool
    {
        return !empty($deposit['tradingAccountId']);
    }

    /**
     * 生成 MT4 写接口的 idempotency_key。
     * 主流程直接用业务流水号 transactionId；rollback 传 '_rb' 后缀，
     * 区别于主流程同 key，避免被 gateway 当主流程重放命中（gateway key 上限 24 字符）。
     */
    private function mt4IdemKey($transactionId, string $suffix = ''): ?string
    {
        $tx = trim((string)($transactionId ?? ''));
        if ($tx === '') {
            return null; // 交给 Mt4ApiClient 兜底（仅非资金重放敏感场景）
        }
        return substr($tx . $suffix, 0, 24);
    }

    /**
     * 退款/回滚给 FinancePro 的 originOrderId：按原单号类型前缀映射，区别于原单号又不加长
     * （FP originOrderId 有长度上限），否则会被当重复单去重、返回 Success 但不入账。
     *   TXN-（出金/入金）→ RF-    ITF-（转账）→ RFI-    COMM-（IB佣金）→ RFC-
     * 不同类型给不同前缀，避免各自反向单号之间撞车。
     */
    private function financeProReversalOrderId($transactionId): string
    {
        $tx = trim((string)($transactionId ?? ''));
        if ($tx === '') {
            return '';
        }
        $dashPos = strpos($tx, '-');
        if ($dashPos === false) {
            return 'RF-' . $tx;
        }
        $prefixMap = ['TXN' => 'RF', 'ITF' => 'RFI', 'COMM' => 'RFC'];
        $reversalPrefix = $prefixMap[substr($tx, 0, $dashPos)] ?? 'RF';
        return $reversalPrefix . '-' . substr($tx, $dashPos + 1);
    }

    private function executeDepositPlatformCredit(array $deposit, $comment)
    {
        $tradingAccountId = (int)($deposit['tradingAccountId'] ?? 0);
        if ($tradingAccountId <= 0) {
            return null;
        }

        $context = $this->tradingAccountAmountService->getAccountContext($tradingAccountId);
        $tradingAccount = $context['tradingAccount'];
        if (!$tradingAccount || empty($tradingAccount['platformId'])) {
            throw new RuntimeException('Trading account platform is missing');
        }
        if ((int)($tradingAccount['userId'] ?? 0) !== (int)($deposit['userId'] ?? 0)) {
            throw new RuntimeException('Trading account does not belong to deposit user');
        }

        $providerAccountId = (string)$context['providerAccountId'];
        $platformKey = (string)$context['platformKey'];
        $baseAmount = (float)($deposit['amount'] ?? 0);
        $platformAmount = isset($deposit['platformAmount']) && is_numeric($deposit['platformAmount'])
            ? (float)$deposit['platformAmount']
            : $this->tradingAccountAmountService->convertBaseToPlatformAmount($baseAmount, $context);
        $scale = isset($deposit['amountScale']) && is_numeric($deposit['amountScale'])
            ? (float)$deposit['amountScale']
            : (float)($context['scale'] ?? 1);
        $unit = array_key_exists('displayUnit', $deposit) ? ($deposit['displayUnit'] ?? null) : ($context['unit'] ?? null);

        if ($platformKey === 'financepro') {
            $this->executeFinanceProBalanceChange($providerAccountId, $platformAmount, '0', $deposit['transactionId'] ?? null, 'deposit');
        } elseif ($platformKey === 'mt5') {
            try {
                $this->mt5Client->deposit($providerAccountId, $platformAmount, $comment);
            } finally {
                $this->mt5Client->disconnect();
            }
        } elseif ($platformKey === 'mt4') {
            // 主流程 idempotency_key 用业务流水号 transactionId，天然防重放
            try {
                $this->mt4Client->deposit($providerAccountId, $platformAmount, $comment, $this->mt4IdemKey($deposit['transactionId'] ?? null));
            } finally {
                $this->mt4Client->disconnect();
            }
        } else {
            return null;
        }

        return [
            'tradingAccountId' => $tradingAccountId,
            'platformKey' => $platformKey,
            'providerAccountId' => $providerAccountId,
            'amount' => $platformAmount,
            'baseAmount' => $baseAmount,
            'scale' => $scale,
            'unit' => $unit,
            'transactionId' => $deposit['transactionId'] ?? null
        ];
    }

    private function rollbackDepositPlatformCredit($operation, $contextId): void
    {
        if (!is_array($operation) || empty($operation['platformKey']) || empty($operation['providerAccountId'])) {
            return;
        }

        if ($operation['platformKey'] === 'financepro') {
            $this->executeFinanceProBalanceChange((string)$operation['providerAccountId'], (float)$operation['amount'], '1', $this->financeProReversalOrderId($operation['transactionId'] ?? null), 'deposit');
            return;
        }

        if ($operation['platformKey'] === 'mt5') {
            try {
                $this->mt5Client->withdraw(
                    (string)$operation['providerAccountId'],
                    (float)$operation['amount'],
                    'CRM deposit approve rollback #' . (string)$contextId
                );
            } finally {
                $this->mt5Client->disconnect();
            }
        }

        if ($operation['platformKey'] === 'mt4') {
            // rollback 与主流程是不同调用，idempotency_key 加 _rb 后缀，避免被 gateway 当主流程重放命中
            try {
                $this->mt4Client->withdraw(
                    (string)$operation['providerAccountId'],
                    (float)$operation['amount'],
                    'CRM deposit approve rollback #' . (string)$contextId,
                    $this->mt4IdemKey($operation['transactionId'] ?? null, '_rb')
                );
            } finally {
                $this->mt4Client->disconnect();
            }
        }
    }

    private function buildDepositPlatformSnapshot(?array $platformOperation): array
    {
        if (!$platformOperation) {
            return [];
        }

        return [
            'amountScale' => isset($platformOperation['scale']) ? (float)$platformOperation['scale'] : null,
            'platformAmount' => isset($platformOperation['amount']) ? (float)$platformOperation['amount'] : null,
            'displayUnit' => $platformOperation['unit'] ?? null,
        ];
    }

    private function persistDepositPlatformSnapshot($depositId, ?array $platformOperation): void
    {
        $payload = $this->buildDepositPlatformSnapshot($platformOperation);
        if (empty($payload)) {
            return;
        }

        try {
            $this->depositModel->update((int)$depositId, $payload);
        } catch (Throwable $e) {
            Logger::error('Failed to persist deposit platform snapshot: ' . $e->getMessage());
        }
    }

    // ====================================================================
    // Withdrawal 平台调用 - 私有 helper
    // ====================================================================

    private function rollbackApprovedWithdrawalIfNeeded($withdrawal): array
    {
        $tradingAccountId = (int)($withdrawal['tradingAccountId'] ?? 0);
        if ($tradingAccountId <= 0) {
            return [
                'attempted' => false,
                'success' => true,
                'type' => null
            ];
        }

        try {
            $context = $this->tradingAccountAmountService->getAccountContext($tradingAccountId);
        } catch (Exception $e) {
            return [
                'attempted' => true,
                'success' => false,
                'type' => 'trading_account',
                'error' => $e->getMessage()
            ];
        }

        $accountId = $context['providerAccountId'];
        $platformKey = (string)$context['platformKey'];
        $amount = (string)$this->resolveWithdrawalPlatformAmount(
            $withdrawal,
            $this->calculateWithdrawalPlatformDebitAmount($withdrawal),
            $context
        );

        try {
            if ($platformKey === 'financepro') {
                if (!$this->financePro['balance']) {
                    throw new RuntimeException('Trading Platform balance endpoint not configured');
                }

                $response = $this->financeProClient->request(
                    $this->financePro['balance'],
                    'GET',
                    [
                        'accountId' => $accountId,
                        'amount' => $amount,
                        'type' => '0',
                        'originOrderId' => $this->financeProReversalOrderId($withdrawal['transactionId'] ?? null),
                        'comment' => 'withdraw'
                    ],
                    [
                        'expect_success_key' => 'Success',
                        'success_value' => true,
                        'error_message_key' => 'ErrMsg'
                    ]
                );

                if (!isset($response['Success']) || $response['Success'] !== true) {
                    throw new RuntimeException((string)($response['ErrMsg'] ?? 'Trading Platform rollback failed'));
                }

                return [
                    'attempted' => true,
                    'success' => true,
                    'type' => 'financepro'
                ];
            }

            if ($platformKey === 'mt5') {
                $this->mt5Client->deposit(
                    $accountId,
                    (float)$amount,
                    'CRM withdrawal rollback #' . (int)($withdrawal['id'] ?? 0)
                );

                return [
                    'attempted' => true,
                    'success' => true,
                    'type' => 'mt5'
                ];
            }

            if ($platformKey === 'mt4') {
                $this->mt4Client->deposit(
                    $accountId,
                    (float)$amount,
                    'CRM withdrawal rollback #' . (int)($withdrawal['id'] ?? 0),
                    $this->mt4IdemKey($withdrawal['transactionId'] ?? null, '_rb')
                );

                return [
                    'attempted' => true,
                    'success' => true,
                    'type' => 'mt4'
                ];
            }

            return [
                'attempted' => true,
                'success' => false,
                'type' => $platformKey,
                'error' => 'Unsupported trading platform rollback'
            ];
        } catch (Exception $e) {
            return [
                'attempted' => true,
                'success' => false,
                'type' => $platformKey,
                'error' => $e->getMessage()
            ];
        } finally {
            if ($platformKey === 'mt5') {
                $this->mt5Client->disconnect();
            }
            if ($platformKey === 'mt4') {
                $this->mt4Client->disconnect();
            }
        }
    }

    private function acquireNamedLock(string $lockName, int $timeoutSeconds): bool
    {
        $row = $this->db->fetchOne(
            'SELECT GET_LOCK(:lockName, :timeoutSeconds) AS lockResult',
            [
                'lockName' => $lockName,
                'timeoutSeconds' => $timeoutSeconds,
            ]
        );

        return isset($row['lockResult']) && (int)$row['lockResult'] === 1;
    }

    private function releaseNamedLock(string $lockName): void
    {
        try {
            $this->db->fetchOne(
                'SELECT RELEASE_LOCK(:lockName) AS lockResult',
                ['lockName' => $lockName]
            );
        } catch (Exception $e) {
            Logger::error('Failed to release withdrawal rejection lock: ' . $e->getMessage(), [
                'lockName' => $lockName,
            ]);
        }
    }

    private function calculateWithdrawalPlatformDebitAmount(array $withdrawal): float
    {
        return round((float)($withdrawal['amount'] ?? 0), 2);
    }

    private function resolveWithdrawalPlatformAmount(array $snapshot, float $baseAmount, array $context): float
    {
        if (
            array_key_exists('platformAmount', $snapshot) &&
            $snapshot['platformAmount'] !== null &&
            $snapshot['platformAmount'] !== '' &&
            is_numeric($snapshot['platformAmount'])
        ) {
            return (float)$snapshot['platformAmount'];
        }

        return $this->tradingAccountAmountService->convertBaseToPlatformAmount($baseAmount, $context);
    }

    private function resolveWithdrawalPlatformScale(array $snapshot, array $context): float
    {
        if (
            array_key_exists('amountScale', $snapshot) &&
            $snapshot['amountScale'] !== null &&
            $snapshot['amountScale'] !== '' &&
            is_numeric($snapshot['amountScale'])
        ) {
            return (float)$snapshot['amountScale'];
        }

        return (float)($context['scale'] ?? 1);
    }

    private function resolveWithdrawalPlatformUnit(array $snapshot, array $context)
    {
        if (array_key_exists('displayUnit', $snapshot)) {
            return $snapshot['displayUnit'] ?? null;
        }

        return $context['unit'] ?? null;
    }

    // ====================================================================
    // 客户端通知 - 私有 helper
    // ====================================================================

    private function sendDepositStatusNotice(array $deposit, string $status, array $context = []): void
    {
        $clientId = (int)($deposit['userId'] ?? 0);
        $depositId = (int)($deposit['id'] ?? 0);
        if ($clientId <= 0 || $depositId <= 0) {
            return;
        }

        [$subject, $message, $type] = $this->buildDepositStatusNoticePayload($status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        // operatorId=0 表示自动审批（如 PSP callback），clientNotifications.createdBy 是 adminUsers FK，
        // 不能写 0（会触发 FK 校验失败），必须写 null
        $createdBy = (int)($context['operatorId'] ?? 0) > 0 ? (int)$context['operatorId'] : null;
        $notificationId = $this->clientNotificationModel->create([
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'priority' => $status === 'rejected' ? 'high' : 'normal',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'emailTemplate' => null,
            'createdBy' => $createdBy,
            'createdAt' => $now,
            'updatedAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->clientSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => $type,
            'metadata' => json_encode([
                'depositId' => $depositId,
                'clientId' => $clientId,
                'status' => $status,
                'amount' => (float)($deposit['amount'] ?? 0),
                'tradingAccountId' => $deposit['tradingAccountId'] ?? null,
                'adminNotes' => $context['adminNotes'] ?? null,
                'rejectionReasonId' => $context['rejectionReasonId'] ?? null,
                'rejectionReasonTitle' => $context['rejectionReasonTitle'] ?? null,
                'rejectionNotes' => $context['rejectionNotes'] ?? null,
                'customReason' => $context['customReason'] ?? null
            ]),
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'readAt' => null,
            'createdAt' => $now
        ]);
    }

    private function buildDepositStatusNoticePayload(string $status, array $context = []): array
    {
        if ($status === 'approved') {
            $subject = 'Your deposit has been approved';
            $message = 'Your deposit request has been approved.';
            $adminNotes = trim((string)($context['adminNotes'] ?? ''));
            if ($adminNotes !== '') {
                $message .= ' Notes: ' . $adminNotes;
            }

            return [$subject, $message, 'deposit_approved'];
        }

        if ($status === 'rejected') {
            $subject = 'Your deposit has been rejected';
            $message = 'Your deposit request has been rejected.';
            $reasonText = trim((string)($context['customReason'] ?? $context['rejectionNotes'] ?? $context['rejectionReasonTitle'] ?? ''));
            if ($reasonText !== '') {
                $message .= ' Reason: ' . $reasonText;
            }

            return [$subject, $message, 'deposit_rejected'];
        }

        return ['', '', ''];
    }

    private function sendWithdrawalStatusNotice(array $withdrawal, string $status, array $context = []): void
    {
        $clientId = (int)($withdrawal['userId'] ?? 0);
        $withdrawalId = (int)($withdrawal['id'] ?? 0);
        if ($clientId <= 0 || $withdrawalId <= 0) {
            return;
        }

        [$subject, $message, $type] = $this->buildWithdrawalStatusNoticePayload($status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        // operatorId=0 表示自动审批（如 PSP callback），clientNotifications.createdBy 是 adminUsers FK，
        // 不能写 0（会触发 FK 校验失败），必须写 null
        $createdBy = (int)($context['operatorId'] ?? 0) > 0 ? (int)$context['operatorId'] : null;

        try {
            $notificationId = $this->clientNotificationModel->create([
                'clientId' => $clientId,
                'subject' => $subject,
                'message' => $message,
                'priority' => $status === 'rejected' ? 'high' : 'normal',
                'scheduleType' => 'immediate',
                'status' => 'sent',
                'emailTemplate' => null,
                'createdBy' => $createdBy,
                'createdAt' => $now,
                'updatedAt' => $now
            ]);

            if (!$notificationId) {
                return;
            }

            $this->clientSystemNotificationModel->create([
                'notificationId' => $notificationId,
                'type' => $type,
                'metadata' => json_encode([
                    'withdrawalId' => $withdrawalId,
                    'clientId' => $clientId,
                    'status' => $status,
                    'amount' => (float)($withdrawal['amount'] ?? 0),
                    'adminNotes' => $context['adminNotes'] ?? null,
                    'rejectionReasonId' => $context['rejectionReasonId'] ?? null,
                    'rejectionReasonTitle' => $context['rejectionReasonTitle'] ?? null,
                    'rejectionNotes' => $context['rejectionNotes'] ?? null,
                    'customReason' => $context['customReason'] ?? null
                ]),
                'clientId' => $clientId,
                'subject' => $subject,
                'message' => $message,
                'isRead' => 0,
                'readAt' => null,
                'createdAt' => $now
            ]);
        } catch (Exception $e) {
            // Notice creation failure should not block status updates.
        }
    }

    private function buildWithdrawalStatusNoticePayload(string $status, array $context = []): array
    {
        if ($status === 'approved') {
            $subject = 'Your withdrawal has been approved';
            $message = 'Your withdrawal request has been approved and is being processed.';
            $adminNotes = trim((string)($context['adminNotes'] ?? ''));
            if ($adminNotes !== '') {
                $message .= ' Notes: ' . $adminNotes;
            }

            return [$subject, $message, 'withdrawal_approved'];
        }

        if ($status === 'rejected') {
            $subject = 'Your withdrawal has been rejected';
            $message = 'Your withdrawal request has been rejected.';
            $reasonText = trim((string)($context['customReason'] ?? $context['rejectionNotes'] ?? $context['rejectionReasonTitle'] ?? ''));
            if ($reasonText !== '') {
                $message .= ' Reason: ' . $reasonText;
            }

            return [$subject, $message, 'withdrawal_rejected'];
        }

        return ['', '', ''];
    }

    // ====================================================================
    // FinancePro 共享 helper
    // ====================================================================

    private function executeFinanceProBalanceChange($accountId, $amount, $type, $originOrderId = null, $comment = ''): void
    {
        if (empty($this->financePro['balance'])) {
            throw new RuntimeException('Trading Platform balance endpoint not configured');
        }

        $this->financeProClient->request(
            $this->financePro['balance'],
            'GET',
            [
                'accountId' => (string)$accountId,
                'amount' => (string)$amount,
                'type' => (string)$type,
                'originOrderId' => (string)($originOrderId ?? ''),
                'comment' => (string)$comment
            ],
            [
                'expect_success_key' => 'Success',
                'success_value' => true,
                'error_message_key' => 'ErrMsg'
            ]
        );
    }
}
