<?php
/**
 * Admin Trading Account Adjustment Controller
 *
 * 后台手动调整客户【交易账户】(区别于钱包 AdminBalanceAdjustmentController)：
 *   - credit：直接调平台 creditIn/creditOut 打/扣赠金。CRM 侧不落记录，靠同步拉回。
 *   - balance：走 deposit / withdrawal 流程，但带上 tradingAccountId，让审批流真打平台：
 *       · 打钱(in)  → 建 pending deposit；审批时 markDepositSuccess 打平台 deposit
 *       · 扣钱(out) → 先 executeWithdrawalPlatformDebit 扣平台，再建 pending withdrawal（建单失败回滚扣款）
 *     金额换算 / 平台扣款 / 回滚全复用 PaymentSettlementService，与客户端出入金同一套。
 */

require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/DepositStatusHistory.php';
require_once __DIR__ . '/../models/WithdrawalStatusHistory.php';
require_once __DIR__ . '/../models/TradingGroup.php';
require_once __DIR__ . '/../models/TradingPlatformLeverage.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../utils/Password.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../services/PaymentSettlementService.php';
require_once __DIR__ . '/../services/TradingAccountAmountService.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AdminTradingAccountAdjustmentController {

    const PERMISSION_KEY = 'page_clientsdetail_funding_adjust';
    const SYSTEM_GATEWAY_KEY = 'system';

    private $db;
    private $externalAccountModel;
    private $tradingAccountModel;
    private $gatewayModel;
    private $depositHistoryModel;
    private $withdrawalHistoryModel;
    private $paymentService;
    private $amountService;
    private $mt5ApiClient;
    private $mt4ApiClient;
    private $financeProClient;
    private $financePro;
    private $tradingGroupModel;
    private $leverageModel;
    private $clientUserModel;
    private $emailSender;

    public function __construct() {
        $this->db                     = Database::getInstance();
        $this->externalAccountModel   = new TradingAccountExternalAccount();
        $this->tradingAccountModel    = new TradingAccount();
        $this->gatewayModel           = new PaymentGatewaySetting();
        $this->depositHistoryModel    = new DepositStatusHistory();
        $this->withdrawalHistoryModel = new WithdrawalStatusHistory();
        $this->paymentService         = new PaymentSettlementService();
        $this->amountService          = new TradingAccountAmountService();
        $this->tradingGroupModel      = new TradingGroup();
        $this->leverageModel          = new TradingPlatformLeverage();
        $this->clientUserModel        = new ClientUser();
        $this->emailSender            = new EmailSender();

        $this->mt5ApiClient = new Mt5ApiClient();
        $this->mt4ApiClient = new Mt4ApiClient();
        $appConfig = require __DIR__ . '/../config/app.php';
        $this->financePro = $appConfig['integrations']['finance_pro'] ?? [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
    }

    // ====================================================================
    // Credit（赠金）：直接打平台，不落 CRM 记录（靠同步拉回）
    // ====================================================================

    /**
     * POST /api/admin/trading-account-adjustments/credit
     * Body: { tradingAccountId, direction: 'in'|'out', amount, reason }
     */
    public function credit() {
        $admin = $this->requireAdmin();
        AuthMiddleware::checkPermission(self::PERMISSION_KEY);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $direction        = $data['direction'] ?? '';
        $amount           = isset($data['amount']) ? (float)$data['amount'] : 0;
        $reason           = trim($data['reason'] ?? '');

        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if (!in_array($direction, ['in', 'out'], true)) {
            Response::error('direction must be in or out', 400);
        }
        if ($amount <= 0) {
            Response::error('amount must be greater than zero', 400);
        }
        if ($reason === '') {
            Response::error('reason is required', 400);
        }

        $ext = $this->externalAccountModel->findByTradingAccount($tradingAccountId);
        if (!$ext || empty($ext['providerAccountId'])) {
            Response::error('Trading account platform binding not found', 404);
        }
        $platformKey = (string)($ext['providerKey'] ?? '');
        $login       = (string)$ext['providerAccountId'];

        // FP 去重用的 OriginOrderId：CD + 时间(到秒) + 随机，≤20 字符，基本不可能重复
        $originOrderId = 'CD' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));

        try {
            $platformResult = $this->executePlatformCredit($platformKey, $login, $direction === 'out', $amount, $reason, $originOrderId);
        } catch (Exception $e) {
            Logger::error('Admin trading account credit failed', [
                'tradingAccountId' => $tradingAccountId,
                'platformKey'      => $platformKey,
                'direction'        => $direction,
                'amount'           => $amount,
                'originOrderId'    => $originOrderId,
                'operatorId'       => (int)$admin['userId'],
                'error'            => $e->getMessage(),
            ]);
            Response::error('Platform credit failed: ' . $e->getMessage(), 500);
            return;
        }

        $ta = $this->tradingAccountModel->findById($tradingAccountId);
        $this->logAdjustment('credit', $direction, $tradingAccountId, $platformKey, (int)($ta['userId'] ?? 0), $amount, $reason, (int)$admin['userId']);

        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'login'            => $login,
            'direction'        => $direction,
            'amount'           => $amount,
            'reason'           => $reason,
            'operatedBy'       => (int)$admin['userId'],
            'platformResult'   => $platformResult,
        ], 'Credit adjustment sent to platform');
    }

    /**
     * 按平台路由赠金调整。login 统一用 providerAccountId。
     * 入(in)=正数 / creditIn；出(out)=负数 / creditOut。
     */
    private function executePlatformCredit($platformKey, $login, $isOut, $amount, $comment, $originOrderId = null) {
        switch ($platformKey) {
            case 'mt5':
                try {
                    $signed = $isOut ? -1 * $amount : $amount;
                    return $this->mt5ApiClient->updateBalance($login, $signed, $comment, 'credit');
                } finally {
                    $this->mt5ApiClient->disconnect();
                }
            case 'mt4':
                return $isOut
                    ? $this->mt4ApiClient->creditOut($login, $amount, $comment)
                    : $this->mt4ApiClient->creditIn($login, $amount, $comment);
            case 'financepro':
                // FP 用 OriginOrderId 做去重（重复会被拒，ErrCode 60002），每次调用必须传唯一值
                return $isOut
                    ? $this->financeProClient->creditOut($login, $amount, $comment, $originOrderId)
                    : $this->financeProClient->creditIn($login, $amount, $comment, $originOrderId);
            default:
                throw new Exception('Unsupported platform: ' . $platformKey);
        }
    }

    // ====================================================================
    // Balance（打钱/扣钱）：走 deposit / withdrawal，带 tradingAccountId 让审批真打平台
    // ====================================================================

    /**
     * POST /api/admin/trading-account-adjustments/balance
     * Body: { tradingAccountId, direction: 'in'|'out', amount, reason }
     *   in  = 打钱：建 pending deposit（审批后打平台）
     *   out = 扣钱：先扣平台，再建 pending withdrawal
     */
    public function balance() {
        $admin = $this->requireAdmin();
        AuthMiddleware::checkPermission(self::PERMISSION_KEY);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $direction        = $data['direction'] ?? '';
        $amount           = isset($data['amount']) ? (float)$data['amount'] : 0;
        $reason           = trim($data['reason'] ?? '');

        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if (!in_array($direction, ['in', 'out'], true)) {
            Response::error('direction must be in or out', 400);
        }
        if ($amount <= 0) {
            Response::error('amount must be greater than zero', 400);
        }
        if ($reason === '') {
            Response::error('reason is required', 400);
        }

        $tradingAccount = $this->tradingAccountModel->findById($tradingAccountId);
        if (!$tradingAccount) {
            Response::error('Trading account not found', 404);
        }
        $userId       = (int)($tradingAccount['userId'] ?? 0);
        $currencyCode = trim((string)($tradingAccount['accountCurrency'] ?? ''));
        if ($currencyCode === '') {
            $currencyCode = 'USD';
        }

        // 平台上下文（scale/unit + providerAccountId）——缺绑定直接拒
        try {
            $context = $this->amountService->getAccountContext($tradingAccountId);
        } catch (Exception $e) {
            Response::error('Trading account platform binding not found: ' . $e->getMessage(), 404);
            return;
        }

        $systemGateway = $this->gatewayModel->findByKey(self::SYSTEM_GATEWAY_KEY);
        if (!$systemGateway) {
            Response::error('System payment gateway is not configured', 500);
        }
        $gatewaySettingId = (int)$systemGateway['id'];

        $ipAddress      = $_SERVER['REMOTE_ADDR'] ?? null;
        $scale          = (float)($context['scale'] ?? 1);
        $unit           = $context['unit'] ?? $currencyCode;
        $platformAmount = $this->amountService->convertBaseToPlatformAmount($amount, $context);

        if ($direction === 'in') {
            // 打钱：建 pending deposit，审批时由 markDepositSuccess → executeDepositPlatformCredit 打平台
            try {
                $depositId = $this->createTradingAccountDeposit(
                    $userId, $tradingAccountId, $amount, $platformAmount, $unit,
                    $currencyCode, $gatewaySettingId, $reason, $ipAddress, $admin['userId']
                );
            } catch (Exception $e) {
                Logger::error('Admin trading account deposit(create) failed', [
                    'tradingAccountId' => $tradingAccountId,
                    'amount'           => $amount,
                    'operatorId'       => (int)$admin['userId'],
                    'error'            => $e->getMessage(),
                ]);
                Response::error('Failed to create deposit: ' . $e->getMessage(), 500);
                return;
            }

            $this->logAdjustment('balance', 'in', $tradingAccountId, (string)($context['platformKey'] ?? ''), $userId, $amount, $reason, (int)$admin['userId']);

            Response::success([
                'type'             => 'balance',
                'direction'        => 'in',
                'tradingAccountId' => $tradingAccountId,
                'depositId'        => $depositId,
                'amount'           => $amount,
                'reason'           => $reason,
                'operatedBy'       => (int)$admin['userId'],
            ], 'Deposit created, pending approval');
            return;
        }

        // 扣钱：先扣平台，再建 pending withdrawal（与客户端出金同序：debit → 建单；建单失败回滚 debit）
        $transactionId = $this->generateTransactionId('W');
        try {
            $platformDebit = $this->paymentService->executeWithdrawalPlatformDebit(
                $tradingAccountId,
                $amount,
                'CRM admin withdraw #' . $tradingAccountId,
                null,
                $transactionId
            );
        } catch (Exception $e) {
            Logger::error('Admin trading account withdraw(platform debit) failed', [
                'tradingAccountId' => $tradingAccountId,
                'amount'           => $amount,
                'operatorId'       => (int)$admin['userId'],
                'error'            => $e->getMessage(),
            ]);
            Response::error('Platform withdraw failed: ' . $e->getMessage(), 500);
            return;
        }

        $snapshot = $this->paymentService->buildWithdrawalPlatformSnapshot($platformDebit);
        try {
            $withdrawalId = $this->createTradingAccountWithdrawal(
                $userId,
                $tradingAccountId,
                $amount,
                $snapshot['platformAmount'] ?? $platformAmount,
                $snapshot['displayUnit'] ?? $unit,
                $currencyCode,
                $gatewaySettingId,
                $reason,
                $ipAddress,
                $admin['userId'],
                $transactionId
            );
        } catch (Exception $e) {
            // 建单失败：把已扣的平台金额补回去，避免"平台扣了 CRM 没记录"
            $this->paymentService->rollbackWithdrawalPlatformDebit($platformDebit, $transactionId);
            Logger::error('Admin trading account withdraw(create) failed, platform debit rolled back', [
                'tradingAccountId' => $tradingAccountId,
                'amount'           => $amount,
                'transactionId'    => $transactionId,
                'operatorId'       => (int)$admin['userId'],
                'error'            => $e->getMessage(),
            ]);
            Response::error('Failed to create withdrawal (platform debit rolled back): ' . $e->getMessage(), 500);
            return;
        }

        $this->logAdjustment('balance', 'out', $tradingAccountId, (string)($context['platformKey'] ?? ''), $userId, $amount, $reason, (int)$admin['userId']);

        Response::success([
            'type'             => 'balance',
            'direction'        => 'out',
            'tradingAccountId' => $tradingAccountId,
            'withdrawalId'     => $withdrawalId,
            'amount'           => $amount,
            'reason'           => $reason,
            'operatedBy'       => (int)$admin['userId'],
        ], 'Withdrawal created, platform debited');
    }

    /**
     * 建一行 pending deposit（带 tradingAccountId）。审批时走 deposit 审批流打平台。
     */
    private function createTradingAccountDeposit($userId, $tradingAccountId, $amount, $platformAmount, $unit, $currencyCode, $gatewaySettingId, $reason, $ipAddress, $adminId) {
        $transactionId = $this->generateTransactionId('D');
        $depositId = $this->db->insert('deposits', [
            'transactionId'    => $transactionId,
            'userId'           => $userId,
            'tradingAccountId' => $tradingAccountId,
            'gatewaySettingId' => $gatewaySettingId,
            'amount'           => $amount,
            'currencyCode'     => $currencyCode,
            'displayUnit'      => $unit,
            'platformAmount'   => $platformAmount,
            'quotedAmount'     => $amount,
            'platformFee'      => 0,
            'status'           => 'pending',
            'requestedAt'      => date('Y-m-d H:i:s'),
            'adminNotes'       => $reason,
            'ipAddress'        => $ipAddress,
        ]);

        $this->depositHistoryModel->create([
            'depositId'      => $depositId,
            'previousStatus' => null,
            'newStatus'      => 'pending',
            'description'    => 'Admin trading account deposit created',
            'changedBy'      => $adminId,
        ]);

        return (int)$depositId;
    }

    /**
     * 建一行 pending withdrawal（带 tradingAccountId）。平台扣款已在调用前完成。
     */
    private function createTradingAccountWithdrawal($userId, $tradingAccountId, $amount, $platformAmount, $unit, $currencyCode, $gatewaySettingId, $reason, $ipAddress, $adminId, $transactionId) {
        $withdrawalId = $this->db->insert('withdrawals', [
            'transactionId'    => $transactionId,
            'userId'           => $userId,
            'tradingAccountId' => $tradingAccountId,
            'gatewaySettingId' => $gatewaySettingId,
            'amount'           => $amount,
            'currencyCode'     => $currencyCode,
            'displayUnit'      => $unit,
            'platformAmount'   => $platformAmount,
            'quotedAmount'     => $amount,
            'networkFee'       => 0,
            'platformFee'      => 0,
            'status'           => 'pending',
            'withdrawalReason' => $reason,
            'requestedAt'      => date('Y-m-d H:i:s'),
            'adminNotes'       => $reason,
            'ipAddress'        => $ipAddress,
        ]);

        $this->withdrawalHistoryModel->create([
            'withdrawalId'   => $withdrawalId,
            'previousStatus' => null,
            'newStatus'      => 'pending',
            'description'    => 'Admin trading account withdrawal created',
            'changedBy'      => $adminId,
        ]);

        return (int)$withdrawalId;
    }

    private function generateTransactionId($suffix) {
        $date = date('Ymd');
        $random = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        return "TXN-{$date}-{$suffix}{$random}";
    }

    /**
     * 审计：应用日志 + 后台操作日志（log_transaction，按方向落 存款/提款 子模块）。
     * 写日志失败不影响主流程。
     *
     * @param string $kind      credit | balance
     * @param string $direction in | out
     */
    private function logAdjustment($kind, $direction, $tradingAccountId, $platformKey, $userId, $amount, $reason, $operatorId) {
        Logger::info('Admin trading account adjustment', [
            'kind'             => $kind,
            'direction'        => $direction,
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'userId'           => $userId,
            'amount'           => $amount,
            'operatorId'       => $operatorId,
        ]);

        try {
            $writer = new AdminOperationLogWriter();
            $subModule = $direction === 'out'
                ? OperationLogPages::resolveLogWithdrawals(null)
                : OperationLogPages::resolveLogDeposits(null);

            $kindZh = $kind === 'credit' ? '赠金' : '余额';
            $dirZh  = $direction === 'out' ? '扣减' : '增加';
            $kindEn = $kind === 'credit' ? 'credit' : 'balance';
            $dirEn  = $direction === 'out' ? 'deduct' : 'add';

            $writer->record([
                'modelKey'         => 'log_transaction',
                'subModuleKey'     => $subModule,
                'operationTypeKey' => 'add',
                'operatorId'       => (int)$operatorId,
                'targetId'         => (int)$userId,
                'detailZh'         => "后台{$dirZh}交易账户 #{$tradingAccountId}（{$platformKey}）{$kindZh}：{$amount}；原因：{$reason}",
                'detailEn'         => "Admin {$dirEn} trading account #{$tradingAccountId} ({$platformKey}) {$kindEn}: {$amount}; reason: {$reason}",
            ]);
        } catch (Exception $e) {
            Logger::error('Failed to write admin adjustment operation log: ' . $e->getMessage());
        }
    }

    // ====================================================================
    // 账户管理：重置密码 / 改 group / 改 leverage（各自独立权限）
    // ====================================================================

    /**
     * POST /api/admin/trading-account-adjustments/reset-password  Body: { tradingAccountId }
     * 生成随机密码 → 平台改密 → 邮件发给客户。响应绝不回显密码（admin 看不到）。
     */
    public function resetPassword() {
        $admin = $this->requireAdmin();
        AuthMiddleware::checkPermission('client_trading_reset_password');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }

        $ctx = $this->resolveManaged($tradingAccountId);
        $platformKey = $ctx['platformKey'];
        $login = $ctx['login'];
        $userId = (int)($ctx['account']['userId'] ?? 0);

        $user = $this->clientUserModel->findById($userId);
        $email = trim((string)($user['email'] ?? ''));
        $name = trim(((string)($user['firstName'] ?? '')) . ' ' . ((string)($user['lastName'] ?? '')));
        if ($name === '') {
            $name = $email !== '' ? $email : 'Trader';
        }

        if (!in_array($platformKey, ['mt5', 'mt4', 'financepro'], true)) {
            Response::error('Unsupported platform: ' . $platformKey, 400);
        }

        $newPassword = Password::generateRandomPassword(12);

        try {
            if ($platformKey === 'mt5') {
                try { $this->mt5ApiClient->changePassword($login, $newPassword, 'main'); }
                finally { $this->mt5ApiClient->disconnect(); }
            } elseif ($platformKey === 'mt4') {
                $this->mt4ApiClient->changePassword($login, $newPassword, 'trade');
            } else {
                if ($email === '') {
                    throw new Exception('client email is required for FinancePro password reset');
                }
                $this->financeProClient->resetPassword($email, $newPassword);
            }
        } catch (Exception $e) {
            Logger::error('Admin reset trading password failed', [
                'tradingAccountId' => $tradingAccountId, 'platformKey' => $platformKey,
                'operatorId' => (int)$admin['userId'], 'error' => $e->getMessage(),
            ]);
            Response::error('Failed to reset password: ' . $e->getMessage(), 500);
            return;
        }

        // 发新密码给客户；发信失败不影响改密成功，只记日志。admin 侧永远拿不到密码。
        $emailed = false;
        if ($email !== '') {
            try {
                $platformNameMap = ['mt5' => 'MetaTrader 5', 'mt4' => 'MetaTrader 4', 'financepro' => 'FinancePro'];
                $res = $this->emailSender->sendTradingPasswordResetEmail($email, $name, $login, $newPassword, [
                    'platformName' => $platformNameMap[$platformKey] ?? 'Trading Platform',
                ]);
                $emailed = !empty($res['success']);
            } catch (Exception $e) {
                Logger::error('Reset password email failed: ' . $e->getMessage());
            }
        }

        $this->logManage('reset_password', $tradingAccountId, $platformKey, $userId, (int)$admin['userId'], $emailed ? 'reset & emailed' : 'reset (email not sent)');

        // 响应绝不含密码
        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'emailed'          => $emailed,
        ], 'Password reset and sent to client');
    }

    /**
     * POST /api/admin/trading-account-adjustments/group  Body: { tradingAccountId, groupTradingId }
     */
    public function changeGroup() {
        $admin = $this->requireAdmin();
        AuthMiddleware::checkPermission('client_trading_change_group');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $groupTradingId = isset($data['groupTradingId']) ? (int)$data['groupTradingId'] : 0;
        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if ($groupTradingId <= 0) {
            Response::error('groupTradingId is required', 400);
        }

        $ctx = $this->resolveManaged($tradingAccountId);
        $platformKey = $ctx['platformKey'];
        $login = $ctx['login'];

        // 目标 group 必须属于该平台
        $group = $this->tradingGroupModel->findByTradingId($groupTradingId, $platformKey);
        if (!$group || empty($group['name'])) {
            Response::error('Selected group is not available for this platform', 400, ['groupTradingId' => ['not available']]);
        }
        $groupName = (string)$group['name'];

        if (!in_array($platformKey, ['mt5', 'mt4', 'financepro'], true)) {
            Response::error('Unsupported platform: ' . $platformKey, 400);
        }

        $localGroupId = 0;
        try {
            if ($platformKey === 'mt5') {
                try { $this->mt5ApiClient->updateUser($login, ['group' => $groupName]); }
                finally { $this->mt5ApiClient->disconnect(); }
                $localGroupId = (int)($group['id'] ?? 0);          // MT5 本地存 tradingGroups.id
            } elseif ($platformKey === 'mt4') {
                $this->mt4ApiClient->updateUser($login, ['group' => $groupName]);
                $localGroupId = (int)($group['id'] ?? 0);
            } else {
                $this->fpEditAccount($ctx['ext'], $groupTradingId, null);
                $localGroupId = $groupTradingId;                    // FP 本地存 trading_id
            }
        } catch (Exception $e) {
            Logger::error('Admin change group failed', [
                'tradingAccountId' => $tradingAccountId, 'platformKey' => $platformKey,
                'groupTradingId' => $groupTradingId, 'operatorId' => (int)$admin['userId'], 'error' => $e->getMessage(),
            ]);
            Response::error('Failed to change group: ' . $e->getMessage(), 500);
            return;
        }

        if ($localGroupId > 0) {
            $this->externalAccountModel->updateGroupId($login, $localGroupId);
        }

        $this->logManage('change_group', $tradingAccountId, $platformKey, (int)($ctx['account']['userId'] ?? 0), (int)$admin['userId'], "group -> {$groupName}");

        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'groupTradingId'   => $groupTradingId,
            'groupName'        => $groupName,
        ], 'Group updated');
    }

    /**
     * POST /api/admin/trading-account-adjustments/leverage  Body: { tradingAccountId, leverageValue }
     */
    public function changeLeverage() {
        $admin = $this->requireAdmin();
        AuthMiddleware::checkPermission('client_trading_change_leverage');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $tradingAccountId = isset($data['tradingAccountId']) ? (int)$data['tradingAccountId'] : 0;
        $leverageValue = trim((string)($data['leverageValue'] ?? ''));
        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }
        if ($leverageValue === '') {
            Response::error('leverageValue is required', 400);
        }

        $ctx = $this->resolveManaged($tradingAccountId);
        $platformKey = $ctx['platformKey'];
        $login = $ctx['login'];
        $platformId = (int)($ctx['account']['platformId'] ?? 0);

        if ($platformId <= 0 || !$this->leverageModel->isLeverageEnabled($platformId, $leverageValue)) {
            Response::error('Selected leverage is not available for this platform', 400, ['leverageValue' => ['not available']]);
        }
        $numeric = $this->leverageNumber($leverageValue);

        if (!in_array($platformKey, ['mt5', 'mt4', 'financepro'], true)) {
            Response::error('Unsupported platform: ' . $platformKey, 400);
        }

        try {
            if ($platformKey === 'mt5') {
                try { $this->mt5ApiClient->updateUser($login, ['leverage' => $numeric]); }
                finally { $this->mt5ApiClient->disconnect(); }
            } elseif ($platformKey === 'mt4') {
                $this->mt4ApiClient->updateUser($login, ['leverage' => $numeric]);
            } else {
                // FP：Leverage 用档位映射的 financeProLeverageId
                $row = $this->leverageModel->findByPlatformAndValue($platformId, $leverageValue);
                $leverageId = ($row && isset($row['financeProLeverageId'])) ? (int)$row['financeProLeverageId'] : 0;
                $this->fpEditAccount($ctx['ext'], null, $leverageId > 0 ? $leverageId : null);
            }
        } catch (Exception $e) {
            Logger::error('Admin change leverage failed', [
                'tradingAccountId' => $tradingAccountId, 'platformKey' => $platformKey,
                'leverageValue' => $leverageValue, 'operatorId' => (int)$admin['userId'], 'error' => $e->getMessage(),
            ]);
            Response::error('Failed to change leverage: ' . $e->getMessage(), 500);
            return;
        }

        if ($numeric > 0) {
            $this->externalAccountModel->updateLeverage($login, $numeric);
        }

        $this->logManage('change_leverage', $tradingAccountId, $platformKey, (int)($ctx['account']['userId'] ?? 0), (int)$admin['userId'], "leverage -> {$leverageValue}");

        Response::success([
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'leverageValue'    => $leverageValue,
        ], 'Leverage updated');
    }

    /**
     * GET /api/admin/trading-account-adjustments/options?tradingAccountId=X
     * 给"改 group / 改 leverage"弹窗做下拉：返回该账户所在平台的 groups + 启用的 leverages。
     */
    public function options() {
        $this->requireAdmin();
        AuthMiddleware::checkPermission('page_clientsdetail_trading');

        $tradingAccountId = isset($_GET['tradingAccountId']) ? (int)$_GET['tradingAccountId'] : 0;
        if ($tradingAccountId <= 0) {
            Response::error('tradingAccountId is required', 400);
        }

        $account = $this->tradingAccountModel->findById($tradingAccountId);
        if (!$account) {
            Response::error('Trading account not found', 404);
        }
        $platformId = (int)($account['platformId'] ?? 0);
        $ext = $this->externalAccountModel->findByTradingAccount($tradingAccountId);
        $platformKey = (string)($ext['providerKey'] ?? '');

        $groups = $platformKey !== '' ? $this->tradingGroupModel->getByPlatform($platformKey) : [];
        $leverages = $platformId > 0 ? $this->leverageModel->getEnabledByPlatform($platformId) : [];

        // 当前 group：FP 存 trading_id、MT4/MT5 存本地 id，统一解析成下拉用的 trading_id + 展示名
        $currentGroupTradingId = null;
        $currentGroupLabel = null;
        $storedGroupId = (int)($ext['groupId'] ?? 0);
        if ($storedGroupId > 0) {
            $curGroup = $platformKey === 'financepro'
                ? $this->tradingGroupModel->findByTradingId($storedGroupId, $platformKey)
                : $this->tradingGroupModel->findById($storedGroupId);
            if ($curGroup) {
                $currentGroupTradingId = isset($curGroup['trading_id']) ? (int)$curGroup['trading_id'] : null;
                $currentGroupLabel = (string)($curGroup['label'] ?? $curGroup['name'] ?? '');
            }
        }

        // 当前 leverage：存的是纯数字，按数值匹配回启用列表里的 leverageValue（"1:xxx"）
        $currentLeverageValue = null;
        $currentLeverageLabel = null;
        $storedLeverageNum = $this->leverageNumber($ext['leverage'] ?? '');
        if ($storedLeverageNum > 0) {
            foreach ($leverages as $lev) {
                if ($this->leverageNumber($lev['leverageValue'] ?? '') === $storedLeverageNum) {
                    $currentLeverageValue = $lev['leverageValue'];
                    $currentLeverageLabel = $lev['displayLabel'] ?? $lev['leverageValue'];
                    break;
                }
            }
        }

        Response::success([
            'platformKey'           => $platformKey,
            'groups'                => $groups,
            'leverages'             => $leverages,
            'currentGroupTradingId' => $currentGroupTradingId,
            'currentGroupLabel'     => $currentGroupLabel,
            'currentLeverageValue'  => $currentLeverageValue,
            'currentLeverageLabel'  => $currentLeverageLabel,
        ], 'Options loaded');
    }

    // 解析并校验被管理账户：返回 account/ext/platformKey/login；找不到直接响应错误退出
    private function resolveManaged($tradingAccountId) {
        $account = $this->tradingAccountModel->findById($tradingAccountId);
        if (!$account) {
            Response::error('Trading account not found', 404);
        }
        $ext = $this->externalAccountModel->findByTradingAccount($tradingAccountId);
        if (!$ext || empty($ext['providerAccountId'])) {
            Response::error('Trading account platform binding not found', 404);
        }
        return [
            'account'     => $account,
            'ext'         => $ext,
            'platformKey' => (string)($ext['providerKey'] ?? ''),
            'login'       => (string)$ext['providerAccountId'],
        ];
    }

    // FP EditAccount：Id/Status/Name/Email 用落库资料兜底；groupTradingId / leverageId 有则覆盖。
    private function fpEditAccount(array $ext, $groupTradingId = null, $leverageId = null) {
        if (empty($this->financePro['edit_account'])) {
            throw new Exception('FinancePro edit_account endpoint not configured');
        }
        $payload = [
            'Id'      => (string)($ext['providerAccountId'] ?? ''),
            'GroupId' => (int)($groupTradingId ?? ($ext['groupId'] ?? 0)),
            'Status'  => (int)($ext['status'] ?? 1),
            'Name'    => (string)($ext['name'] ?? ''),
            'Email'   => (string)($ext['email'] ?? ''),
        ];
        if ($leverageId !== null && (int)$leverageId > 0) {
            $payload['Leverage'] = (int)$leverageId;
        }
        $this->financeProClient->request(
            $this->financePro['edit_account'],
            'POST',
            $payload,
            ['expect_success_key' => 'Success', 'success_value' => true, 'error_message_key' => 'ErrMsg']
        );
    }

    // "1:500" / "500" → 500（MT5/MT4 updateUser 用数字）
    private function leverageNumber($value) {
        if (is_numeric($value)) {
            $n = (int)$value;
            return $n > 0 ? $n : 0;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^\s*\d+\s*:\s*(\d+)\s*$/', $value, $m)) {
            $n = (int)$m[1];
            return $n > 0 ? $n : 0;
        }
        if (preg_match('/(\d+)/', $value, $m)) {
            $n = (int)$m[1];
            return $n > 0 ? $n : 0;
        }
        return 0;
    }

    // 审计：应用日志 + 后台操作日志（log_client / 客户列表子模块）。密码类不带任何密码明文。
    private function logManage($action, $tradingAccountId, $platformKey, $userId, $operatorId, $detail) {
        Logger::info('Admin trading account manage', [
            'action'           => $action,
            'tradingAccountId' => $tradingAccountId,
            'platformKey'      => $platformKey,
            'userId'           => $userId,
            'operatorId'       => $operatorId,
        ]);
        try {
            $writer = new AdminOperationLogWriter();
            $writer->record([
                'modelKey'         => 'log_client',
                'subModuleKey'     => OperationLogPages::subModuleKeyByAlias('page_clients_list'),
                'operationTypeKey' => 'edit',
                'operatorId'       => (int)$operatorId,
                'targetId'         => (int)$userId,
                'detailZh'         => "后台交易账户 #{$tradingAccountId}（{$platformKey}）{$action}：{$detail}",
                'detailEn'         => "Admin trading account #{$tradingAccountId} ({$platformKey}) {$action}: {$detail}",
            ]);
        } catch (Exception $e) {
            Logger::error('Failed to write manage operation log: ' . $e->getMessage());
        }
    }

    /**
     * 要求管理员认证（沿用 AdminBalanceAdjustmentController 同款方式）
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }
        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }
        return ['userId' => $userId];
    }
}
