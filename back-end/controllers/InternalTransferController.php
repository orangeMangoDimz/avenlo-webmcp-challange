<?php
/**
 * Internal Transfer Controller
 * 负责内部转账管理相关接口
 */

require_once __DIR__ . '/../models/InternalTransfer.php';
require_once __DIR__ . '/../models/InternalTransferStatusHistory.php';
require_once __DIR__ . '/../models/DepositTag.php';
require_once __DIR__ . '/../models/InternalTransferTagAssignment.php';
require_once __DIR__ . '/../models/InternalTransferNote.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/RejectionReason.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../models/AutoApprovalRule.php';
require_once __DIR__ . '/../models/AutoApprovalLog.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../models/FinanceProToken.php';
require_once __DIR__ . '/../services/TradingAccountAmountService.php';
require_once __DIR__ . '/../services/WalletBalanceService.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class InternalTransferController {
    private $transferModel;
    private $statusHistoryModel;
    private $tagModel;
    private $tagAssignmentModel;
    private $noteModel;
    private $userModel;
    private $tradingAccountModel;
    private $externalAccountModel;
    private $platformModel;
    private $rejectionReasonModel;
    private $depositModel;
    private $withdrawalModel;
    private $clientNotificationModel;
    private $clientSystemNotificationModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;
    private $autoApprovalRuleModel;
    private $autoApprovalLogModel;
    private $financePro;
    private $financeProClient;
    private $mt5;
    private $mt5Client;
    private $mt4;
    private $mt4Client;
    private $tradingAccountAmountService;
    private $walletBalanceService;

    public function __construct() {
        $this->transferModel = new InternalTransfer();
        $this->statusHistoryModel = new InternalTransferStatusHistory();
        $this->tagModel = new DepositTag();
        $this->tagAssignmentModel = new InternalTransferTagAssignment();
        $this->noteModel = new InternalTransferNote();
        $this->userModel = new ClientUser();
        $this->tradingAccountModel = new TradingAccount();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->platformModel = new TradingPlatform();
        $this->rejectionReasonModel = new RejectionReason();
        $this->depositModel = new Deposit();
        $this->withdrawalModel = new Withdrawal();
        $this->clientNotificationModel = new ClientNotification();
        $this->clientSystemNotificationModel = new ClientSystemNotification();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
        $this->autoApprovalRuleModel = new AutoApprovalRule();
        $this->autoApprovalLogModel = new AutoApprovalLog();

        $appConfig = require __DIR__ . '/../config/app.php';
        $this->financePro = $appConfig['integrations']['finance_pro'] ?? [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
        $this->mt5 = $appConfig['integrations']['mt5'] ?? [];
        $this->mt5Client = new Mt5ApiClient($this->mt5);
        $this->mt4 = $appConfig['integrations']['mt4'] ?? [];
        $this->mt4Client = new Mt4ApiClient($this->mt4);
        $this->tradingAccountAmountService = new TradingAccountAmountService();
        $this->walletBalanceService = new WalletBalanceService();
    }

    /**
     * 获取内部转账列表 (管理员)
     * GET /api/internal-transfers
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $search = $_GET['search'] ?? null;

        // 构建筛选条件
        $filters = [];

        // status 支持多选
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $statusList = array_values(array_filter(array_map('trim', explode(',', $_GET['status'])), function ($s) {
                return $s !== '';
            }));
            if (!empty($statusList)) {
                $filters['status'] = $statusList;
            }
        }

        if (isset($_GET['fromType'])) {
            $filters['fromType'] = $_GET['fromType'];
        }

        if (isset($_GET['startDate'])) {
            $filters['startDate'] = $_GET['startDate'];
        }

        if (isset($_GET['endDate'])) {
            $filters['endDate'] = $_GET['endDate'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_internaltransfers');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;

        // 如果有搜索关键词
        if ($search) {
            $result = $this->transferModel->searchInternalTransfers($search, $page, $perPage, $restrictToSalesId);
        } else {
            $result = $this->transferModel->getInternalTransfers($page, $perPage, $filters);
        }

        // 为每个转账添加标签信息
        foreach ($result['items'] as &$transfer) {
            $transfer['tags'] = $this->tagAssignmentModel->getInternalTransferTags($transfer['id']);
            unset(
                $transfer['fromAmountScale'],
                $transfer['fromPlatformAmount'],
                $transfer['fromDisplayUnit'],
                $transfer['toAmountScale'],
                $transfer['toPlatformAmount'],
                $transfer['toDisplayUnit']
            );
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个内部转账详情
     * GET /api/internal-transfers/{id}
     */
    public function show($id) {
        $transfer = $this->transferModel->getInternalTransferDetails($id);

        if (!$transfer) {
            Response::notFound('Internal transfer not found');
        }

        // 获取完整信息
        $transfer['tags'] = $this->tagAssignmentModel->getInternalTransferTags($id);
        $transfer['statusHistory'] = $this->statusHistoryModel->getInternalTransferHistory($id);
        $transfer['notes'] = $this->noteModel->getInternalTransferNotes($id);

        // 计算可用余额（如果是从wallet转出，兼容旧数据 available_balance）
        if ($transfer['fromType'] == 'wallet' || $transfer['fromType'] == 'available_balance') {
            $walletBreakdown = $this->walletBalanceService->getBreakdown((int)$transfer['userId']);
            $transfer['availableBalance'] = round((float)$walletBreakdown['availableBalance'], 2);
        } elseif ($transfer['fromType'] === 'trading_account' && $transfer['fromTradingAccountId']) {
            // 如果是从交易账户转出，计算该账户的可用余额
            // 这里需要根据实际业务逻辑计算交易账户余额
            $transfer['fromAccountBalance'] = 0; // TODO: 实现交易账户余额计算
        }

        Response::success($transfer);
    }

    /**
     * 创建内部转账 (客户端)
     * POST /api/internal-transfers/create
     */
    public function create() {
        $client = $this->requireClient();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $data['fromType'] = $this->normalizeInternalTransferFromType(
            $data['fromType'] ?? null,
            $data['fromTradingAccountId'] ?? null
        );

        Validator::make($data, [
            'fromType' => 'required|in:wallet,available_balance,trading_account',
            'toTradingAccountId' => 'required|numeric',
            'amount' => 'required|numeric'
        ]);

        $fromType = $data['fromType'];
        $toTradingAccountId = (int)$data['toTradingAccountId'];
        $amount = (float)$data['amount'];
        $fromTradingAccountId = !empty($data['fromTradingAccountId']) ? (int)$data['fromTradingAccountId'] : null;

        // 验证金额
        if ($amount <= 0) {
            Response::validationError([
                'amount' => ['Amount must be greater than 0']
            ]);
        }

        // 验证目标交易账户
        $toAccount = $this->tradingAccountModel->findById($toTradingAccountId);
        if (!$toAccount || $toAccount['userId'] != $client['userId']) {
            Response::validationError([
                'toTradingAccountId' => ['Invalid target trading account']
            ]);
        }

        // 验证源账户和余额（与 DepositController::getAvailableBalance 公式一致）
        if ($fromType === 'wallet' || $fromType === 'available_balance') {
            // 统一存储为 wallet（新数据）
            $fromType = 'wallet';
            $walletBreakdown = $this->walletBalanceService->getBreakdown((int)$client['userId']);
            $availableBalance = (float)$walletBreakdown['availableBalance'];

            if ($amount > $availableBalance) {
                Response::validationError([
                    'amount' => ['Amount cannot exceed wallet balance']
                ]);
            }
        } elseif ($fromType === 'trading_account') {
            if (!$fromTradingAccountId) {
                Response::validationError([
                    'fromTradingAccountId' => ['Source trading account is required']
                ]);
            }

            // 验证源交易账户
            $fromAccount = $this->tradingAccountModel->findById($fromTradingAccountId);
            if (!$fromAccount || $fromAccount['userId'] != $client['userId']) {
                Response::validationError([
                    'fromTradingAccountId' => ['Invalid source trading account']
                ]);
            }

            // 验证不能转到同一个账户
            if ($fromTradingAccountId === $toTradingAccountId) {
                Response::validationError([
                    'toTradingAccountId' => ['Cannot transfer to the same account']
                ]);
            }

            try {
                $sourceBalance = $this->resolveTradingAccountBaseBalance($fromTradingAccountId);
            } catch (Exception $e) {
                Response::error('Failed to verify source trading account balance: ' . $e->getMessage(), 500);
            }

            if ($amount > $sourceBalance) {
                Response::validationError([
                    'amount' => ['Amount cannot exceed source trading account balance']
                ]);
            }
        }

        // 生成交易ID
        $transactionId = $this->transferModel->generateTransactionId();

        // 创建内部转账记录
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // 统一存储为 wallet（兼容旧数据 available_balance，但新数据统一使用 wallet）
        $storedFromType = ($fromType === 'available_balance') ? 'wallet' : $fromType;

        $transferData = [
            'transactionId' => $transactionId,
            'userId' => $client['userId'],
            'fromType' => $storedFromType,  // 统一存储为 wallet
            'fromTradingAccountId' => $fromTradingAccountId,
            'toTradingAccountId' => $toTradingAccountId,
            'amount' => $amount,
            'status' => 'pending',
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'clientNotes' => $data['clientNotes'] ?? null
        ];

        $platformOperations = [];
        $snapshotPayload = $this->buildInternalTransferCreateSnapshotPayload($transferData);
        try {
            $platformOperations = $this->executeCreateTimePlatformTransfers($transferData, $transactionId);
            $db = Database::getInstance();
            $db->query(
                "CALL spCreateInternalTransfer(:transactionId, :userId, :fromType, :fromTradingAccountId, :toTradingAccountId, :amount, :clientNotes, :ipAddress, :userAgent, :fromAmountScale, :fromPlatformAmount, :fromDisplayUnit, :toAmountScale, :toPlatformAmount, :toDisplayUnit, @transferId)",
                [
                    'transactionId' => $transactionId,
                    'userId' => $client['userId'],
                    'fromType' => $storedFromType,
                    'fromTradingAccountId' => $fromTradingAccountId,
                    'toTradingAccountId' => $toTradingAccountId,
                    'amount' => $amount,
                    'clientNotes' => $data['clientNotes'] ?? null,
                    'ipAddress' => $ipAddress,
                    'userAgent' => $userAgent,
                    'fromAmountScale' => $snapshotPayload['fromAmountScale'] ?? null,
                    'fromPlatformAmount' => $snapshotPayload['fromPlatformAmount'] ?? null,
                    'fromDisplayUnit' => $snapshotPayload['fromDisplayUnit'] ?? null,
                    'toAmountScale' => $snapshotPayload['toAmountScale'] ?? null,
                    'toPlatformAmount' => $snapshotPayload['toPlatformAmount'] ?? null,
                    'toDisplayUnit' => $snapshotPayload['toDisplayUnit'] ?? null,
                ]
            );
            $spResult = $db->fetchOne("SELECT @transferId as transferId");
            $transferId = (int)($spResult['transferId'] ?? 0);
        } catch (Exception $e) {
            if (!empty($platformOperations)) {
                $rollbackResult = $this->rollbackPlatformTransferOperations($platformOperations, $transactionId);
                if (empty($rollbackResult['success'])) {
                    Response::serverError('Failed to create internal transfer and rollback platform changes: ' . (string)($rollbackResult['error'] ?? 'unknown error'));
                }
            }
            Response::serverError($e->getMessage());
        }

        if (!$transferId) {
            Response::serverError('Failed to create internal transfer');
        }

        // 获取完整的转账信息
        $transfer = $this->transferModel->getInternalTransferDetails($transferId);

        if ($this->syncOnCreate($transfer)) {
            $this->dispatchOrdersBalanceSyncTask();
        }

        try {
            $this->notifyAdminsOfInternalTransferCreated($transfer);
        } catch (Exception $e) {
            // Admin notice failure should not block transfer creation.
        }

        $autoApproved = $this->tryAutoApproveInternalTransfer((int)$transferId);
        if ($autoApproved) {
            $transfer = $this->transferModel->getInternalTransferDetails($transferId);

            $context = [
                'adminNotes' => 'Auto-approved by system',
                'operatorId' => 0
            ];
            $this->sendInternalTransferStatusNotice($transfer, 'approved', $context);
            $this->sendInternalTransferAdminStatusNotice($transfer, 'approved', $context);
            if ($this->syncOnApprove($transfer)) {
                $this->dispatchOrdersBalanceSyncTask();
            }
        }

        Response::created(
            $transfer,
            $autoApproved
                ? 'Internal transfer request created and auto-approved successfully'
                : 'Internal transfer request created successfully'
        );
    }

    private function normalizeInternalTransferFromType($fromType, $fromTradingAccountId): string
    {
        $normalized = trim((string)$fromType);
        if ($normalized !== '') {
            return $normalized;
        }

        return !empty($fromTradingAccountId) ? 'trading_account' : 'wallet';
    }

    /**
     * 批准内部转账 (管理员)
     * POST /api/internal-transfers/{id}/approve
     */
    public function approve($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            $opLog->logInternalTransferApprove($subModule, 0, '', '', 0, false, 'Internal transfer not found');
            Response::notFound('Internal transfer not found');
        }

        $clientId = (int) ($transfer['userId'] ?? 0);
        $transactionId = (string) ($transfer['transactionId'] ?? '');
        $clientName = $this->resolveTransferClientName($transfer);

        if ($transfer['status'] === 'completed') {
            $opLog->logInternalTransferApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $transfer['amount'] ?? 0,
                false,
                'Internal transfer already completed'
            );
            Response::error('Internal transfer already completed', 400);
        }

        if ($transfer['status'] !== 'pending') {
            $opLog->logInternalTransferApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $transfer['amount'] ?? 0,
                false,
                'Only pending transfers can be approved'
            );
            Response::error('Only pending transfers can be approved', 400);
        }

        $adminNotes = $data['adminNotes'] ?? null;

        try {
            $updatedTransfer = $this->completePendingInternalTransfer(
                (int)$id,
                $transfer,
                (int)$admin['userId'],
                $adminNotes,
                'Internal transfer approved by admin',
                'Internal transfer completed',
                'CRM internal transfer approve deposit #' . (int)$id
            );
            $this->sendInternalTransferStatusNotice(
                $updatedTransfer,
                'approved',
                [
                    'adminNotes' => $adminNotes,
                    'operatorId' => (int)$admin['userId']
                ]
            );
            $this->sendInternalTransferAdminStatusNotice(
                $updatedTransfer,
                'approved',
                [
                    'adminNotes' => $adminNotes,
                    'operatorId' => (int)$admin['userId']
                ]
            );
            if ($this->syncOnApprove($updatedTransfer)) {
                $this->dispatchOrdersBalanceSyncTask();
            }

            $opLog->logInternalTransferApprove(
                $subModule,
                (int) ($updatedTransfer['userId'] ?? $clientId),
                (string) ($updatedTransfer['transactionId'] ?? $transactionId),
                $this->resolveTransferClientName($updatedTransfer),
                $updatedTransfer['amount'] ?? 0,
                true
            );

            Response::success($updatedTransfer, 'Internal transfer approved and completed successfully');
        } catch (Exception $e) {
            $opLog->logInternalTransferApprove(
                $subModule,
                $clientId,
                $transactionId,
                $clientName,
                $transfer['amount'] ?? 0,
                false,
                $e->getMessage()
            );
            Response::serverError('Failed to approve internal transfer: ' . $e->getMessage());
        }
    }

    /**
     * 拒绝内部转账 (管理员)
     * POST /api/internal-transfers/{id}/reject
     */
    public function reject($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            $opLog->logInternalTransferReject($subModule, 0, '', '', false, 'Internal transfer not found');
            Response::notFound('Internal transfer not found');
        }

        $clientId = (int) ($transfer['userId'] ?? 0);
        $transactionId = (string) ($transfer['transactionId'] ?? '');

        if (($transfer['status'] ?? '') !== 'pending') {
            $opLog->logInternalTransferReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                'Only pending transfers can be rejected'
            );
            Response::error('Only pending transfers can be rejected', 400);
        }

        $validator = new Validator($data, [
            'rejectionReasonId' => 'required|numeric'
        ]);
        if (!$validator->validate()) {
            $rejectErrors = $validator->getErrors();
            $opLog->logInternalTransferReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($rejectErrors)
            );
            Response::validationError($rejectErrors);
        }

        $rejectionReasonId = (int)$data['rejectionReasonId'];
        $rejectionNotes = $data['rejectionNotes'] ?? null;
        $customReason = $data['customReason'] ?? null;
        $reason = $this->rejectionReasonModel->findById($rejectionReasonId);
        if (!$reason || ($reason['scope'] ?? null) !== 'internal_transfer') {
            $opLog->logInternalTransferReject($subModule, $clientId, $transactionId, '', false, 'Invalid rejection reason');
            Response::validationError([
                'rejectionReasonId' => ['Invalid rejection reason']
            ]);
        }

        if (($reason['reasonKey'] ?? '') === 'custom' && empty($customReason)) {
            $opLog->logInternalTransferReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                'Custom reason is required when selecting "Other" option'
            );
            Response::validationError([
                'customReason' => ['Custom reason is required when selecting "Other" option']
            ]);
        }

        $finalReason = trim((string)($customReason ?: $rejectionNotes ?: $reason['reasonTitle'] ?? 'Rejected by admin'));
        $adminNotes = isset($data['adminNotes']) ? trim((string)$data['adminNotes']) : null;

        $rollbackResult = $this->rollbackPendingTransferPlatformEffects($transfer, 'reject-' . (string)$id);
        if (empty($rollbackResult['success'])) {
            $opLog->logInternalTransferReject(
                $subModule,
                $clientId,
                $transactionId,
                '',
                false,
                'Failed to rollback pending internal transfer before rejection: ' . (string)($rollbackResult['error'] ?? 'unknown error')
            );
            Response::serverError('Failed to rollback pending internal transfer before rejection: ' . (string)($rollbackResult['error'] ?? 'unknown error'));
        }

        $db = Database::getInstance();
        $db->query(
            "CALL spRejectInternalTransfer(:transferId, :rejectedBy, :rejectionReasonId, :rejectionNotes, :customReason, :adminNotes)",
            [
                'transferId' => (int)$id,
                'rejectedBy' => (int)$admin['userId'],
                'rejectionReasonId' => $rejectionReasonId,
                'rejectionNotes' => $rejectionNotes,
                'customReason' => $customReason,
                'adminNotes' => $adminNotes !== '' ? $adminNotes : null
            ]
        );

        $updatedTransfer = $this->transferModel->getInternalTransferDetails($id);
        $this->sendInternalTransferStatusNotice(
            $updatedTransfer,
            'rejected',
            [
                'rejectionReasonId' => $rejectionReasonId,
                'rejectionReasonTitle' => $reason['reasonTitle'] ?? null,
                'rejectionNotes' => $rejectionNotes,
                'customReason' => $customReason,
                'adminNotes' => $adminNotes,
                'operatorId' => (int)$admin['userId']
            ]
        );
        $this->sendInternalTransferAdminStatusNotice(
            $updatedTransfer,
            'rejected',
            [
                'rejectionReasonId' => $rejectionReasonId,
                'rejectionReasonTitle' => $reason['reasonTitle'] ?? null,
                'rejectionNotes' => $rejectionNotes,
                'customReason' => $customReason,
                'adminNotes' => $adminNotes,
                'operatorId' => (int)$admin['userId']
            ]
        );
        if ($this->syncOnRollback($updatedTransfer)) {
            $this->dispatchOrdersBalanceSyncTask();
        }

        $reasonTitle = ($reason['reasonKey'] ?? '') === 'custom'
            ? trim((string) $customReason)
            : trim((string) ($reason['reasonTitle'] ?? $finalReason));
        $opLog->logInternalTransferReject(
            $subModule,
            (int) ($updatedTransfer['userId'] ?? $clientId),
            (string) ($updatedTransfer['transactionId'] ?? $transactionId),
            $reasonTitle,
            true
        );

        Response::success($updatedTransfer, 'Internal transfer rejected successfully');
    }

    /**
     * 获取拒绝原因列表
     * GET /api/internal-transfers/rejection-reasons
     */
    public function getRejectionReasons() {
        $scope = trim((string)($_GET['scope'] ?? 'internal_transfer'));
        if (!in_array($scope, ['internal_transfer'], true)) {
            Response::validationError([
                'scope' => ['Invalid rejection reason scope']
            ]);
        }
        $reasons = $this->rejectionReasonModel->getActiveReasons($scope);
        Response::success($reasons);
    }

    /**
     * 取消内部转账 (客户端，仅自己的 pending transfer)
     * POST /api/internal-transfers/{id}/cancel
     */
    public function cancel($id) {
        $client = $this->requireClient();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            Response::notFound('Internal transfer not found');
        }

        if ((int)($transfer['userId'] ?? 0) !== (int)$client['userId']) {
            Response::forbidden('You can only cancel your own internal transfer');
        }

        if (($transfer['status'] ?? '') !== 'pending') {
            Response::error('Only pending transfers can be cancelled', 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Cancelled by client';
        }

        $rollbackResult = $this->rollbackPendingTransferPlatformEffects($transfer, 'cancel-' . (string)$id);
        if (empty($rollbackResult['success'])) {
            Response::serverError('Failed to rollback pending internal transfer before cancellation: ' . (string)($rollbackResult['error'] ?? 'unknown error'));
        }

        $db = Database::getInstance();
        $db->query(
            "CALL spCancelInternalTransfer(:transferId, :userId, :cancelReason)",
            [
                'transferId' => (int)$id,
                'userId' => (int)$client['userId'],
                'cancelReason' => $reason
            ]
        );

        $updatedTransfer = $this->transferModel->getInternalTransferDetails($id);
        if ($this->syncOnRollback($updatedTransfer)) {
            $this->dispatchOrdersBalanceSyncTask();
        }
        Response::success($updatedTransfer, 'Internal transfer cancelled successfully');
    }

    /**
     * 添加标签到内部转账
     * POST /api/internal-transfers/{id}/tags
     */
    public function addTag($id) {
        $admin = $this->requireAdmin();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            Response::notFound('Internal transfer not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'tagName' => 'required|string'
        ]);

        $tagName = trim($data['tagName']);

        // 查找或创建标签
        $tag = $this->tagModel->findOrCreate($tagName, $admin['userId']);

        // 分配标签
        $this->tagAssignmentModel->assignTag($id, $tag['id'], $admin['userId']);

        // 获取更新后的标签列表
        $tags = $this->tagAssignmentModel->getInternalTransferTags($id);

        Response::success($tags, 'Tag added successfully');
    }

    /**
     * 移除内部转账的标签
     * DELETE /api/internal-transfers/{id}/tags/{tagId}
     */
    public function removeTag($id, $tagId) {
        $this->requireAdmin();
        $subModule = OperationLogPages::resolveLogInternalTransfersFromRequest();
        $opLog = new AdminOperationLogWriter();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            $opLog->logInternalTransferTagRemove($subModule, 0, '', '', false, 'Internal transfer not found');
            Response::notFound('Internal transfer not found');
        }

        $tag = $this->tagModel->findById($tagId);
        $tagName = trim((string) ($tag['tagName'] ?? ''));
        $clientId = (int) ($transfer['userId'] ?? 0);

        $this->tagAssignmentModel->removeTag($id, $tagId);

        $opLog->logInternalTransferTagRemove(
            $subModule,
            $clientId,
            (string) ($transfer['transactionId'] ?? ''),
            $tagName !== '' ? $tagName : '—',
            true
        );

        Response::success(null, 'Tag removed successfully');
    }

    /**
     * 获取内部转账的状态历史
     * GET /api/internal-transfers/{id}/history
     */
    public function getHistory($id) {
        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            Response::notFound('Internal transfer not found');
        }

        $history = $this->statusHistoryModel->getInternalTransferHistory($id);

        Response::success($history);
    }

    /**
     * 添加内部转账备注
     * POST /api/internal-transfers/{id}/notes
     */
    public function addNote($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            $opLog->logInternalTransferNoteAdd($subModule, 0, '', false, 'Internal transfer not found');
            Response::notFound('Internal transfer not found');
        }

        $clientId = (int) ($transfer['userId'] ?? 0);
        $transactionId = (string) ($transfer['transactionId'] ?? '');

        $validator = new Validator($data, [
            'noteContent' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $noteErrors = $validator->getErrors();
            $opLog->logInternalTransferNoteAdd(
                $subModule,
                $clientId,
                $transactionId,
                false,
                OperationLogTextHelpers::validationErrorsToMessage($noteErrors)
            );
            Response::validationError($noteErrors);
        }

        $noteContent = trim($data['noteContent']);
        if (empty($noteContent)) {
            $opLog->logInternalTransferNoteAdd($subModule, $clientId, $transactionId, false, 'Note content cannot be empty');
            Response::validationError([
                'noteContent' => ['Note content cannot be empty']
            ]);
        }

        $noteId = $this->noteModel->addNote($id, $noteContent, $admin['userId']);

        // 获取新添加的备注
        $notes = $this->noteModel->getInternalTransferNotes($id);
        $note = null;
        foreach ($notes as $n) {
            if ($n['id'] == $noteId) {
                $note = $n;
                break;
            }
        }

        if (!$note) {
            $note = $this->noteModel->findById($noteId);
            require_once __DIR__ . '/../models/AdminUser.php';
            $adminUserModel = new AdminUser();
            $adminUser = $adminUserModel->findById($admin['userId']);
            $note['createdByName'] = $adminUser['fullName'] ?? 'Unknown';
        }

        $opLog->logInternalTransferNoteAdd($subModule, $clientId, $transactionId, true);

        Response::created($note, 'Note added successfully');
    }

    /**
     * 获取内部转账备注列表
     * GET /api/internal-transfers/{id}/notes
     */
    public function getNotes($id) {
        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            Response::notFound('Internal transfer not found');
        }

        $notes = $this->noteModel->getInternalTransferNotes($id);

        Response::success($notes);
    }

    /**
     * 获取客户的内部转账列表 (客户端)
     * GET /api/internal-transfers/my-transfers
     */
    public function myTransfers() {
        $client = $this->requireClient();

        $limit = $_GET['limit'] ?? 10;
        $transfers = $this->transferModel->getUserInternalTransfers($client['userId'], $limit);

        Response::success($transfers);
    }

    private function tryAutoApproveInternalTransfer($transferId): bool {
        $rule = $this->autoApprovalRuleModel->findById(3);
        if (!$rule || !$rule['isEnabled']) {
            return false;
        }

        $transfer = $this->transferModel->findById((int)$transferId);
        if (!$transfer || ($transfer['status'] ?? '') !== 'pending') {
            return false;
        }

        $ruleDetails = $this->autoApprovalRuleModel->getRuleDetails(3);
        if (!$ruleDetails) {
            return false;
        }

        $user = $this->userModel->findById($transfer['userId']);
        if (!$user) {
            return false;
        }

        $rejectionReason = $this->getInternalTransferAutoApprovalRejectionReason($transfer, $user, $ruleDetails);
        if ($rejectionReason !== null) {
            $this->logInternalTransferAutoApprovalCheck(
                (int)$transferId,
                $transfer,
                $user,
                null,
                false,
                $rejectionReason
            );
            return false;
        }

        try {
            $updatedTransfer = $this->completePendingInternalTransfer(
                (int)$transferId,
                $transfer,
                0,
                'Auto-approved by system',
                'Internal transfer auto-approved by system',
                'Internal transfer completed by auto approval',
                'CRM internal transfer auto-approve deposit #' . (int)$transferId
            );
        } catch (Exception $e) {
            $this->logInternalTransferAutoApprovalCheck(
                (int)$transferId,
                $transfer,
                $user,
                null,
                false,
                'Approval failed: ' . $e->getMessage()
            );
            return false;
        }

        try {
            $this->autoApprovalRuleModel->recordApplication(3);
        } catch (Throwable $e) {
            Logger::error('Failed to record internal transfer auto-approval rule application: ' . $e->getMessage());
        }
        $this->logInternalTransferAutoApprovalCheck(
            (int)$transferId,
            $updatedTransfer,
            $user,
            3,
            true,
            null
        );

        return true;
    }

    private function getInternalTransferAutoApprovalRejectionReason(array $transfer, array $user, array $ruleDetails): ?string {
        $amount = (float)($transfer['amount'] ?? 0);
        if ($ruleDetails['minAmount'] !== null && $ruleDetails['minAmount'] !== '' && $amount < (float)$ruleDetails['minAmount']) {
            return 'Amount below minimum';
        }
        if ($ruleDetails['maxAmount'] !== null && $ruleDetails['maxAmount'] !== '' && $amount > (float)$ruleDetails['maxAmount']) {
            return 'Amount above maximum';
        }

        if ((int)($ruleDetails['requireKycVerified'] ?? 0) === 1 && ($user['kycStatus'] ?? null) !== 'approved') {
            return 'KYC is not approved';
        }

        $allowedCountries = is_string($ruleDetails['allowedCountries'] ?? null)
            ? json_decode($ruleDetails['allowedCountries'], true)
            : ($ruleDetails['allowedCountries'] ?? []);
        if (!empty($allowedCountries) && !in_array('ALL', $allowedCountries, true)) {
            $userCountry = $user['country'] ?? null;
            if (!$userCountry || !in_array($userCountry, $allowedCountries, true)) {
                return 'Country not in allowed list';
            }
        }

        $excludedCountries = is_string($ruleDetails['excludedCountries'] ?? null)
            ? json_decode($ruleDetails['excludedCountries'], true)
            : ($ruleDetails['excludedCountries'] ?? []);
        if (!empty($excludedCountries)) {
            $userCountry = $user['country'] ?? null;
            if ($userCountry && in_array($userCountry, $excludedCountries, true)) {
                return 'Country is excluded';
            }
        }

        $clientTagNames = $this->getClientTagNames($transfer['userId']);
        $requiredTags = is_array($ruleDetails['requiredClientTags'] ?? null)
            ? $ruleDetails['requiredClientTags']
            : array_filter(array_map('trim', explode(',', (string)($ruleDetails['requiredClientTags'] ?? ''))));
        foreach ($requiredTags as $tag) {
            if (!in_array($tag, $clientTagNames, true)) {
                return 'Missing required tag: ' . $tag;
            }
        }

        $excludedTags = is_array($ruleDetails['excludedClientTags'] ?? null)
            ? $ruleDetails['excludedClientTags']
            : array_filter(array_map('trim', explode(',', (string)($ruleDetails['excludedClientTags'] ?? ''))));
        foreach ($excludedTags as $tag) {
            if (in_array($tag, $clientTagNames, true)) {
                return 'Client has excluded tag: ' . $tag;
            }
        }

        return null;
    }

    private function completePendingInternalTransfer(
        int $transferId,
        array $transfer,
        int $operatorId,
        ?string $adminNotes,
        string $processingDescription,
        string $completedDescription,
        string $platformComment
    ): array {
        if (($transfer['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Only pending transfers can be approved');
        }

        $approveDepositOperation = null;
        if (($transfer['fromType'] ?? '') === 'trading_account' && !empty($transfer['toTradingAccountId'])) {
            $approveDepositOperation = $this->executeInternalTransferApproveDeposit($transfer, $platformComment);
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        $changedBy = $operatorId > 0 ? $operatorId : null;

        try {
            $this->transferModel->update($transferId, [
                'status' => 'processing',
                'approvedAt' => date('Y-m-d H:i:s'),
                'approvedBy' => $operatorId,
                'adminNotes' => $adminNotes
            ]);

            $this->statusHistoryModel->logStatusChange(
                $transferId,
                'pending',
                'processing',
                $processingDescription,
                $changedBy
            );

            $this->transferModel->update($transferId, [
                'status' => 'completed',
                'completedAt' => date('Y-m-d H:i:s')
            ]);

            $this->statusHistoryModel->logStatusChange(
                $transferId,
                'processing',
                'completed',
                $completedDescription,
                $changedBy
            );

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            if ($approveDepositOperation !== null) {
                $this->rollbackPlatformTransferOperations([$approveDepositOperation], 'approve-' . (string)$transferId);
            }
            throw $e;
        }

        $updatedTransfer = $this->transferModel->getInternalTransferDetails($transferId);
        if (
            $approveDepositOperation &&
            (
                !isset($updatedTransfer['toPlatformAmount']) ||
                $updatedTransfer['toPlatformAmount'] === null ||
                $updatedTransfer['toPlatformAmount'] === ''
            )
        ) {
            $this->persistInternalTransferPlatformSnapshots($transferId, [$approveDepositOperation], $updatedTransfer);
            $updatedTransfer = $this->transferModel->getInternalTransferDetails($transferId);
        }

        return $updatedTransfer;
    }

    /**
     * 获取客户标签名列表（leadTagAssignments + leadTags）
     */
    private function getClientTagNames($userId) {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT lt.tagName FROM leadTagAssignments lta INNER JOIN leadTags lt ON lt.id = lta.tagId WHERE lta.leadId = :leadId',
            ['leadId' => $userId]
        );
        return array_column($rows ?: [], 'tagName');
    }

    private function logInternalTransferAutoApprovalCheck($transferId, array $transfer, array $user, $ruleId, bool $wasAutoApproved, ?string $rejectionReason): void {
        try {
            $this->autoApprovalLogModel->logCheck([
                'transactionType' => 'internal_transfer',
                'transactionId' => (int)$transferId,
                'transactionRefId' => $transfer['transactionId'] ?? null,
                'userId' => $transfer['userId'],
                'ruleId' => $ruleId,
                'wasAutoApproved' => $wasAutoApproved ? 1 : 0,
                'checkResults' => [],
                'rejectionReason' => $rejectionReason,
                'amount' => $transfer['amount'],
                'clientCountry' => $user['country'] ?? null,
                'clientTags' => implode(',', $this->getClientTagNames($transfer['userId'])),
                'kycStatus' => $user['kycStatus'] ?? null,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Throwable $e) {
            Logger::error('Failed to write internal transfer auto-approval log: ' . $e->getMessage());
        }
    }

    private function executeCreateTimePlatformTransfers(array $transferData, $transactionId) {
        $operations = [];
        $amount = (float)($transferData['amount'] ?? 0);
        $fromType = (string)($transferData['fromType'] ?? '');

        if ($fromType === 'trading_account' && !empty($transferData['fromTradingAccountId'])) {
            $withdrawOperation = $this->executeTradingAccountPlatformOperation(
                (int)$transferData['fromTradingAccountId'],
                'withdraw',
                $amount,
                'CRM internal transfer create withdraw #' . (string)$transactionId,
                $transactionId
            );
            if ($withdrawOperation !== null) {
                $operations[] = $withdrawOperation;
            }
        }

        // wallet -> trading: create time deposit
        // trading -> trading: deposit happens after admin approval
        if ($fromType !== 'trading_account' && !empty($transferData['toTradingAccountId'])) {
            $depositOperation = $this->executeTradingAccountPlatformOperation(
                (int)$transferData['toTradingAccountId'],
                'deposit',
                $amount,
                'CRM internal transfer create deposit #' . (string)$transactionId,
                $transactionId
            );
            if ($depositOperation !== null) {
                $operations[] = $depositOperation;
            }
        }

        return $operations;
    }

    private function buildInternalTransferCreateSnapshotPayload(array $transferData): array
    {
        $payload = [];
        $amount = (float)($transferData['amount'] ?? 0);
        if ($amount <= 0) {
            return $payload;
        }

        $fromTradingAccountId = (int)($transferData['fromTradingAccountId'] ?? 0);
        if ($fromTradingAccountId > 0) {
            $fromContext = $this->tradingAccountAmountService->getAccountContext($fromTradingAccountId);
            $payload['fromAmountScale'] = isset($fromContext['scale']) ? (float)$fromContext['scale'] : null;
            $payload['fromPlatformAmount'] = $this->tradingAccountAmountService->convertBaseToPlatformAmount($amount, $fromContext);
            $payload['fromDisplayUnit'] = $fromContext['unit'] ?? null;
        }

        $toTradingAccountId = (int)($transferData['toTradingAccountId'] ?? 0);
        if ($toTradingAccountId > 0) {
            $toContext = $this->tradingAccountAmountService->getAccountContext($toTradingAccountId);
            $payload['toAmountScale'] = isset($toContext['scale']) ? (float)$toContext['scale'] : null;
            $payload['toPlatformAmount'] = $this->tradingAccountAmountService->convertBaseToPlatformAmount($amount, $toContext);
            $payload['toDisplayUnit'] = $toContext['unit'] ?? null;
        }

        return $payload;
    }

    /**
     * 生成 MT4 写接口的 idempotency_key。转账涉及多条腿（转出/转入/审批入金/回滚），
     * 同一 transactionId 下必须靠后缀区分，否则网关「同 key 不同 body」会判 409。
     * 预留后缀空间避免被 24 字符上限截断。
     */
    private function mt4IdemKey($transactionId, string $suffix = ''): ?string {
        $tx = trim((string)($transactionId ?? ''));
        if ($tx === '') {
            return null; // 交给 Mt4ApiClient 兜底生成
        }
        $max = 24 - strlen($suffix);
        return substr($tx, 0, max(1, $max)) . $suffix;
    }

    // 反向退回给 FinancePro 的 originOrderId：按原单号类型前缀映射（TXN-→RF- / ITF-→RFI- / COMM-→RFC-），
    // 区别于原单号又不加长（FP originOrderId 有长度上限）；不同类型不同前缀，避免各自反向单号撞车。
    private function financeProReversalOrderId($transactionId): string {
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

    private function executeTradingAccountPlatformOperation($tradingAccountId, $direction, $amount, $comment, $originOrderId = null) {
        $context = $this->tradingAccountAmountService->getAccountContext((int)$tradingAccountId);
        $platformKey = (string)$context['platformKey'];
        $providerAccountId = (string)$context['providerAccountId'];
        $baseAmount = (float)$amount;
        $platformAmount = $this->tradingAccountAmountService->convertBaseToPlatformAmount($baseAmount, $context);

        if ($direction === 'withdraw') {
            if ($platformKey === 'financepro') {
                $this->executeFinanceProBalanceChange($providerAccountId, $platformAmount, '1', $originOrderId, 'transfer');
            } elseif ($platformKey === 'mt5') {
                try {
                    $balance = $this->mt5Client->getBalance($providerAccountId);
                    $available = (float)($balance['balance'] ?? 0);
                    if ($available < $platformAmount) {
                        throw new RuntimeException('Insufficient MT5 balance');
                    }

                    $this->mt5Client->withdraw($providerAccountId, $platformAmount, $comment);
                } finally {
                    $this->mt5Client->disconnect();
                }
            } elseif ($platformKey === 'mt4') {
                try {
                    $balance = $this->mt4Client->getBalance($providerAccountId);
                    $available = (float)($balance['balance'] ?? 0);
                    if ($available < $platformAmount) {
                        throw new RuntimeException('Insufficient MT4 balance');
                    }

                    $this->mt4Client->withdraw($providerAccountId, $platformAmount, $comment, $this->mt4IdemKey($originOrderId, 'TW'));
                } finally {
                    $this->mt4Client->disconnect();
                }
            } else {
                return null;
            }
        } else {
            if ($platformKey === 'financepro') {
                $this->executeFinanceProBalanceChange($providerAccountId, $platformAmount, '0', $originOrderId, 'transfer');
            } elseif ($platformKey === 'mt5') {
                try {
                    $this->mt5Client->deposit($providerAccountId, $platformAmount, $comment);
                } finally {
                    $this->mt5Client->disconnect();
                }
            } elseif ($platformKey === 'mt4') {
                try {
                    $this->mt4Client->deposit($providerAccountId, $platformAmount, $comment, $this->mt4IdemKey($originOrderId, 'TD'));
                } finally {
                    $this->mt4Client->disconnect();
                }
            } else {
                return null;
            }
        }

        return [
            'tradingAccountId' => (int)$tradingAccountId,
            'platformKey' => $platformKey,
            'providerAccountId' => $providerAccountId,
            'direction' => (string)$direction,
            'amount' => $platformAmount,
            'baseAmount' => $baseAmount,
            'scale' => (float)($context['scale'] ?? 1),
            'unit' => $context['unit'] ?? null,
            'transactionId' => $originOrderId
        ];
    }

    private function executeInternalTransferApproveDeposit(array $transfer, $comment) {
        $tradingAccountId = (int)($transfer['toTradingAccountId'] ?? 0);
        if ($tradingAccountId <= 0) {
            return null;
        }

        $context = $this->tradingAccountAmountService->getAccountContext($tradingAccountId);
        $platformKey = (string)$context['platformKey'];
        $providerAccountId = (string)$context['providerAccountId'];
        $baseAmount = (float)($transfer['amount'] ?? 0);
        $platformAmount = isset($transfer['toPlatformAmount']) && is_numeric($transfer['toPlatformAmount'])
            ? (float)$transfer['toPlatformAmount']
            : $this->tradingAccountAmountService->convertBaseToPlatformAmount($baseAmount, $context);
        $scale = isset($transfer['toAmountScale']) && is_numeric($transfer['toAmountScale'])
            ? (float)$transfer['toAmountScale']
            : (float)($context['scale'] ?? 1);
        $unit = array_key_exists('toDisplayUnit', $transfer) ? ($transfer['toDisplayUnit'] ?? null) : ($context['unit'] ?? null);
        $originOrderId = $transfer['transactionId'] ?? null;

        if ($platformKey === 'financepro') {
            $this->executeFinanceProBalanceChange($providerAccountId, $platformAmount, '0', $originOrderId, 'transfer');
        } elseif ($platformKey === 'mt5') {
            try {
                $this->mt5Client->deposit($providerAccountId, $platformAmount, $comment);
            } finally {
                $this->mt5Client->disconnect();
            }
        } elseif ($platformKey === 'mt4') {
            try {
                $this->mt4Client->deposit($providerAccountId, $platformAmount, $comment, $this->mt4IdemKey($originOrderId, 'AD'));
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
            'direction' => 'deposit',
            'amount' => $platformAmount,
            'baseAmount' => $baseAmount,
            'scale' => $scale,
            'unit' => $unit,
            'transactionId' => $originOrderId
        ];
    }

    private function rollbackPlatformTransferOperations(array $operations, $transactionId) {
        foreach (array_reverse($operations) as $operation) {
            try {
                $reverseDirection = ($operation['direction'] ?? '') === 'withdraw' ? 'deposit' : 'withdraw';
                $comment = 'CRM internal transfer create rollback #' . (string)$transactionId;

                if (($operation['platformKey'] ?? '') === 'financepro') {
                    $type = $reverseDirection === 'withdraw' ? '1' : '0';
                    $this->executeFinanceProBalanceChange((string)$operation['providerAccountId'], (float)$operation['amount'], $type, $this->financeProReversalOrderId($operation['transactionId'] ?? $transactionId), 'transfer');
                } elseif (($operation['platformKey'] ?? '') === 'mt5') {
                    try {
                        if ($reverseDirection === 'withdraw') {
                            $this->mt5Client->withdraw((string)$operation['providerAccountId'], (float)$operation['amount'], $comment);
                        } else {
                            $this->mt5Client->deposit((string)$operation['providerAccountId'], (float)$operation['amount'], $comment);
                        }
                    } finally {
                        $this->mt5Client->disconnect();
                    }
                } elseif (($operation['platformKey'] ?? '') === 'mt4') {
                    // 回滚 key 用原腿方向区分（RW=回滚转出腿，RD=回滚转入腿），避免与原腿 key 撞车被网关当重放
                    $rbKey = $this->mt4IdemKey(
                        $operation['transactionId'] ?? $transactionId,
                        ($operation['direction'] ?? '') === 'withdraw' ? 'RW' : 'RD'
                    );
                    try {
                        if ($reverseDirection === 'withdraw') {
                            $this->mt4Client->withdraw((string)$operation['providerAccountId'], (float)$operation['amount'], $comment, $rbKey);
                        } else {
                            $this->mt4Client->deposit((string)$operation['providerAccountId'], (float)$operation['amount'], $comment, $rbKey);
                        }
                    } finally {
                        $this->mt4Client->disconnect();
                    }
                }
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'operation' => $operation
                ];
            }
        }

        return [
            'success' => true
        ];
    }

    private function rollbackPendingTransferPlatformEffects(array $transfer, $contextId) {
        $operations = [];
        $fromType = (string)($transfer['fromType'] ?? '');
        $amount = (float)($transfer['amount'] ?? 0);

        if ($fromType === 'trading_account' && !empty($transfer['fromTradingAccountId'])) {
            $context = $this->resolveTradingAccountPlatformOperationContext((int)$transfer['fromTradingAccountId']);
            $operations[] = [
                'tradingAccountId' => (int)$transfer['fromTradingAccountId'],
                'direction' => 'withdraw',
                'amount' => $this->resolveInternalTransferPlatformAmount(
                    $transfer,
                    'fromPlatformAmount',
                    $amount,
                    $context
                ),
                'baseAmount' => $amount,
                'scale' => $this->resolveInternalTransferPlatformScale($transfer, 'fromAmountScale', $context),
                'unit' => $this->resolveInternalTransferPlatformUnit($transfer, 'fromDisplayUnit', $context)
            ] + $context;
        } elseif (($fromType === 'wallet' || $fromType === 'available_balance') && !empty($transfer['toTradingAccountId'])) {
            $context = $this->resolveTradingAccountPlatformOperationContext((int)$transfer['toTradingAccountId']);
            $operations[] = [
                'tradingAccountId' => (int)$transfer['toTradingAccountId'],
                'direction' => 'deposit',
                'amount' => $this->resolveInternalTransferPlatformAmount(
                    $transfer,
                    'toPlatformAmount',
                    $amount,
                    $context
                ),
                'baseAmount' => $amount,
                'scale' => $this->resolveInternalTransferPlatformScale($transfer, 'toAmountScale', $context),
                'unit' => $this->resolveInternalTransferPlatformUnit($transfer, 'toDisplayUnit', $context)
            ] + $context;
        }

        if (empty($operations)) {
            return ['success' => true];
        }

        return $this->rollbackPlatformTransferOperations($operations, $contextId);
    }

    private function resolveInternalTransferPlatformAmount(array $transfer, string $amountKey, float $baseAmount, array $context): float
    {
        if (
            array_key_exists($amountKey, $transfer) &&
            $transfer[$amountKey] !== null &&
            $transfer[$amountKey] !== '' &&
            is_numeric($transfer[$amountKey])
        ) {
            return (float)$transfer[$amountKey];
        }

        return $this->tradingAccountAmountService->convertBaseToPlatformAmount($baseAmount, $context);
    }

    private function resolveInternalTransferPlatformScale(array $transfer, string $scaleKey, array $context): float
    {
        if (
            array_key_exists($scaleKey, $transfer) &&
            $transfer[$scaleKey] !== null &&
            $transfer[$scaleKey] !== '' &&
            is_numeric($transfer[$scaleKey])
        ) {
            return (float)$transfer[$scaleKey];
        }

        return (float)($context['scale'] ?? 1);
    }

    private function resolveInternalTransferPlatformUnit(array $transfer, string $unitKey, array $context)
    {
        if (array_key_exists($unitKey, $transfer)) {
            return $transfer[$unitKey] ?? null;
        }

        return $context['unit'] ?? null;
    }

    private function resolveTradingAccountPlatformOperationContext($tradingAccountId) {
        $context = $this->tradingAccountAmountService->getAccountContext((int)$tradingAccountId);

        return [
            'platformKey' => (string)$context['platformKey'],
            'providerAccountId' => (string)$context['providerAccountId'],
            'scale' => (float)($context['scale'] ?? 1),
            'unit' => $context['unit'] ?? null
        ];
    }

    private function resolveTradingAccountBaseBalance($tradingAccountId): float {
        $context = $this->tradingAccountAmountService->getAccountContext((int)$tradingAccountId);
        $platformKey = (string)$context['platformKey'];

        if ($platformKey === 'mt5') {
            try {
                $balance = $this->mt5Client->getBalance((string)$context['providerAccountId']);
                $platformBalance = (float)($balance['balance'] ?? 0);
            } finally {
                $this->mt5Client->disconnect();
            }

            return $this->tradingAccountAmountService->convertPlatformToBaseAmount($platformBalance, $context);
        }

        $platformBalance = isset($context['externalAccount']['platformBalance']) && $context['externalAccount']['platformBalance'] !== null
            ? (float)$context['externalAccount']['platformBalance']
            : 0.0;

        return $this->tradingAccountAmountService->convertPlatformToBaseAmount($platformBalance, $context);
    }

    private function buildInternalTransferPlatformSnapshotPayload(array $operations, array $transfer): array {
        $payload = [];
        $fromTradingAccountId = (int)($transfer['fromTradingAccountId'] ?? 0);
        $toTradingAccountId = (int)($transfer['toTradingAccountId'] ?? 0);

        foreach ($operations as $operation) {
            $operationAccountId = (int)($operation['tradingAccountId'] ?? 0);
            if ($operationAccountId <= 0) {
                continue;
            }

            if ($fromTradingAccountId > 0 && $operationAccountId === $fromTradingAccountId) {
                $payload['fromAmountScale'] = isset($operation['scale']) ? (float)$operation['scale'] : null;
                $payload['fromPlatformAmount'] = isset($operation['amount']) ? (float)$operation['amount'] : null;
                $payload['fromDisplayUnit'] = $operation['unit'] ?? null;
            }

            if ($toTradingAccountId > 0 && $operationAccountId === $toTradingAccountId) {
                $payload['toAmountScale'] = isset($operation['scale']) ? (float)$operation['scale'] : null;
                $payload['toPlatformAmount'] = isset($operation['amount']) ? (float)$operation['amount'] : null;
                $payload['toDisplayUnit'] = $operation['unit'] ?? null;
            }
        }

        if (empty($payload)) {
            return [];
        }

        return $payload;
    }

    private function persistInternalTransferPlatformSnapshots($transferId, array $operations, ?array $transfer = null): void {
        if (empty($operations)) {
            return;
        }

        if ($transfer === null) {
            $transfer = $this->transferModel->findById((int)$transferId);
        }
        if (!$transfer) {
            return;
        }

        $payload = $this->buildInternalTransferPlatformSnapshotPayload($operations, $transfer);
        if (empty($payload)) {
            return;
        }

        try {
            $this->transferModel->update((int)$transferId, $payload);
        } catch (Throwable $e) {
            Logger::error('Failed to persist internal transfer platform snapshots: ' . $e->getMessage());
        }
    }

    private function executeFinanceProBalanceChange($accountId, $amount, $type, $originOrderId = null, $comment = 'transfer') {
        if (!$this->financePro['balance']) {
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

    /**
     * 要求客户端认证（支持预览 X-Preview-Token 与 JWT）
     */
    private function requireClient() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            $user = $this->userModel->findById($userId);
            if (!$user) {
                Response::unauthorized('User not found');
            }
            return [
                'userId' => $userId,
                'user' => $user
            ];
        }

        $payload = JWT::getPayload();
        if (!$payload || ($payload['type'] ?? '') !== 'client') {
            Response::forbidden('Client authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::unauthorized('User not found');
        }

        return [
            'userId' => $userId,
            'user' => $user
        ];
    }

    private function sendInternalTransferStatusNotice(array $transfer, string $status, array $context = []): void
    {
        $clientId = (int)($transfer['userId'] ?? 0);
        $transferId = (int)($transfer['id'] ?? 0);
        if ($clientId <= 0 || $transferId <= 0) {
            return;
        }

        [$subject, $message, $type] = $this->buildInternalTransferStatusNoticePayload($status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');

        try {
            $notificationId = $this->clientNotificationModel->create([
                'clientId' => $clientId,
                'subject' => $subject,
                'message' => $message,
                'priority' => $status === 'rejected' ? 'high' : 'medium',
                'scheduleType' => 'immediate',
                'status' => 'sent',
                'emailTemplate' => null,
                'createdBy' => $context['operatorId'] ?? null,
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
                    'transferId' => $transferId,
                    'clientId' => $clientId,
                    'status' => $status,
                    'amount' => (float)($transfer['amount'] ?? 0),
                    'fromType' => $transfer['fromType'] ?? null,
                    'fromTradingAccountId' => $transfer['fromTradingAccountId'] ?? null,
                    'toTradingAccountId' => $transfer['toTradingAccountId'] ?? null,
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

    private function notifyAdminsOfInternalTransferCreated(array $transfer): void
    {
        $clientId = (int)($transfer['userId'] ?? 0);
        if ($clientId <= 0) {
            return;
        }

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            return;
        }

        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? ('Client #' . $clientId);
        }

        $subject = "New Internal Transfer Request from {$clientName}";
        $message = "Client {$clientName} has created an internal transfer request for review.";
        $metadata = json_encode([
            'transferId' => (int)($transfer['id'] ?? 0),
            'clientId' => $clientId,
            'amount' => (float)($transfer['amount'] ?? 0),
            'action' => 'view_internal_transfer',
            'actionUrl' => '/internal-transfers'
        ]);

        $this->createAdminNotification(0, $subject, $message, $metadata, 'internal_transfer_created');
    }

    private function createAdminNotification(int $adminId, string $subject, string $message, string $metadata, string $type): void
    {
        if ($adminId < 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $notificationId = $this->adminNotificationModel->create([
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'normal',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'createdBy' => null,
            'createdAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->adminNotificationDeliveryModel->create([
            'notificationId' => $notificationId,
            'channel' => 'system',
            'status' => 'sent',
            'sentAt' => $now,
            'createdAt' => $now
        ]);

        $this->adminSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => $type,
            'metadata' => $metadata,
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'createdAt' => $now
        ]);
    }

    private function sendInternalTransferAdminStatusNotice(array $transfer, string $status, array $context = []): void
    {
        $clientId = (int)($transfer['userId'] ?? 0);
        $transferId = (int)($transfer['id'] ?? 0);
        if ($clientId <= 0 || $transferId <= 0) {
            return;
        }

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            return;
        }

        [$subject, $message, $type] = $this->buildInternalTransferAdminStatusNoticePayload($client, $status, $context);
        if ($subject === '' || $message === '' || $type === '') {
            return;
        }

        $metadata = json_encode([
            'transferId' => $transferId,
            'clientId' => $clientId,
            'status' => $status,
            'amount' => (float)($transfer['amount'] ?? 0),
            'fromType' => $transfer['fromType'] ?? null,
            'fromTradingAccountId' => $transfer['fromTradingAccountId'] ?? null,
            'toTradingAccountId' => $transfer['toTradingAccountId'] ?? null,
            'adminNotes' => $context['adminNotes'] ?? null,
            'rejectionReasonId' => $context['rejectionReasonId'] ?? null,
            'rejectionReasonTitle' => $context['rejectionReasonTitle'] ?? null,
            'rejectionNotes' => $context['rejectionNotes'] ?? null,
            'customReason' => $context['customReason'] ?? null,
            'action' => 'view_internal_transfer',
            'actionUrl' => '/internal-transfers'
        ]);

        $this->createAdminNotification(0, $subject, $message, $metadata, $type);
    }

    private function buildInternalTransferStatusNoticePayload(string $status, array $context = []): array
    {
        if ($status === 'approved') {
            $subject = 'Your internal transfer has been approved';
            $message = 'Your internal transfer request has been approved.';
            $adminNotes = trim((string)($context['adminNotes'] ?? ''));
            if ($adminNotes !== '') {
                $message .= ' Notes: ' . $adminNotes;
            }

            return [$subject, $message, 'internal_transfer_approved'];
        }

        if ($status === 'rejected') {
            $subject = 'Your internal transfer has been rejected';
            $message = 'Your internal transfer request has been rejected.';
            $reasonText = trim((string)($context['customReason'] ?? $context['rejectionNotes'] ?? $context['rejectionReasonTitle'] ?? ''));
            if ($reasonText !== '') {
                $message .= ' Reason: ' . $reasonText;
            }

            return [$subject, $message, 'internal_transfer_rejected'];
        }

        return ['', '', ''];
    }

    private function buildInternalTransferAdminStatusNoticePayload(array $client, string $status, array $context = []): array
    {
        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? 'Client';
        }

        if ($status === 'approved') {
            $subject = "Internal transfer approved for {$clientName}";
            $message = "Internal transfer request from {$clientName} has been approved.";
            $adminNotes = trim((string)($context['adminNotes'] ?? ''));
            if ($adminNotes !== '') {
                $message .= ' Notes: ' . $adminNotes;
            }

            return [$subject, $message, 'internal_transfer_approved'];
        }

        if ($status === 'rejected') {
            $subject = "Internal transfer rejected for {$clientName}";
            $message = "Internal transfer request from {$clientName} has been rejected.";
            $reasonText = trim((string)($context['customReason'] ?? $context['rejectionNotes'] ?? $context['rejectionReasonTitle'] ?? ''));
            if ($reasonText !== '') {
                $message .= ' Reason: ' . $reasonText;
            }

            return [$subject, $message, 'internal_transfer_rejected'];
        }

        return ['', '', ''];
    }

    private function dispatchOrdersBalanceSyncTask(): void
    {
        try {
            require_once __DIR__ . '/../services/DeveloperSettingsService.php';
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

    private function syncOnCreate(array $transfer): bool
    {
        return (string)($transfer['fromType'] ?? '') === 'trading_account';
    }

    private function syncOnApprove(array $transfer): bool
    {
        return !empty($transfer['toTradingAccountId']);
    }

    private function syncOnRollback(array $transfer): bool
    {
        return (string)($transfer['fromType'] ?? '') === 'trading_account';
    }

    private function hasApproveSyncTransfers(array $transferIds): bool
    {
        foreach ($transferIds as $transferId) {
            $transfer = $this->transferModel->findById((int)$transferId);
            if ($transfer && $this->syncOnApprove($transfer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 发送邮件给客户
     * POST /api/internal-transfers/{id}/send-email
     */
    public function sendEmail($id) {
        $admin = $this->requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();

        $transfer = $this->transferModel->findById($id);
        if (!$transfer) {
            $opLog->logInternalTransferEmail($subModule, 0, '', false, 'Internal transfer not found');
            Response::notFound('Internal transfer not found');
        }

        $clientId = (int) ($transfer['userId'] ?? 0);

        $validator = new Validator($data, [
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'content' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $emailErrors = $validator->getErrors();
            $opLog->logInternalTransferEmail(
                $subModule,
                $clientId,
                trim((string) ($data['email'] ?? '')),
                false,
                OperationLogTextHelpers::validationErrorsToMessage($emailErrors)
            );
            Response::validationError($emailErrors);
        }

        $email = $data['email'];
        $subject = $data['subject'];
        $content = $data['content'];

        // 验证邮箱是否匹配转账的客户邮箱
        $user = $this->userModel->findById($transfer['userId']);
        if (!$user || $user['email'] !== $email) {
            $opLog->logInternalTransferEmail(
                $subModule,
                $clientId,
                $email,
                false,
                'Email address does not match the transfer client'
            );
            Response::validationError([
                'email' => ['Email address does not match the transfer client']
            ],'Email address does not match the transfer client');
        }

        try {
            // 发送邮件
            $emailSender = new EmailSender();

            $success = $emailSender->sendClientNotification(
                $email,
                $subject,
                $content,
                [
                    'transferId' => $id,
                    'sentBy' => $admin['userId'],
                    'type' => 'internal_transfer'
                ]
            );

            if ($success) {
                $opLog->logInternalTransferEmail($subModule, $clientId, $email, true);
                Response::success([
                    'message' => 'Email sent successfully',
                    'email' => $email,
                    'subject' => $subject
                ], 'Email sent successfully');
            } else {
                $opLog->logInternalTransferEmail($subModule, $clientId, $email, false, 'Failed to send email');
                Response::error('Failed to send email', 500);
            }
        } catch (Exception $e) {
            $opLog->logInternalTransferEmail(
                $subModule,
                $clientId,
                $email,
                false,
                'Failed to send email: ' . $e->getMessage()
            );
            Response::error('Failed to send email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取内部转账统计
     * GET /api/internal-transfers/statistics
     */
    public function statistics() {
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;

        $stats = $this->transferModel->getStatistics($startDate, $endDate);

        Response::success($stats);
    }

    /**
     * 批量批准内部转账
     * POST /api/internal-transfers/bulk-approve
     */
    public function bulkApprove() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();

        $validator = new Validator($data, [
            'transferIds' => 'required|array'
        ]);
        if (!$validator->validate()) {
            $bulkApproveErrors = $validator->getErrors();
            $opLog->logInternalTransferBulkApprove(
                $subModule,
                [],
                false,
                OperationLogTextHelpers::validationErrorsToMessage($bulkApproveErrors)
            );
            Response::validationError($bulkApproveErrors);
        }

        $transferIds = $data['transferIds'];
        $adminNotes = $data['adminNotes'] ?? null;

        $results = [];
        $successCount = 0;
        $failCount = 0;
        $approvedTransactionIds = [];

        foreach ($transferIds as $transferId) {
            try {
                $transfer = $this->transferModel->findById($transferId);
                $approveDepositOperation = null;

                if (!$transfer || $transfer['status'] === 'completed') {
                    $errorMsg = 'Transfer not found or already completed';
                    $apiMessage = "Bulk approval failed at transfer ID {$transferId}: {$errorMsg}";
                    $opLog->logInternalTransferBulkApprove($subModule, [], false, $apiMessage);
                    Response::error($apiMessage, 400);
                }

                if ($transfer['status'] !== 'pending') {
                    $errorMsg = 'Only pending transfers can be approved';
                    $apiMessage = "Bulk approval failed at transfer ID {$transferId}: {$errorMsg}";
                    $opLog->logInternalTransferBulkApprove($subModule, [], false, $apiMessage);
                    Response::error($apiMessage, 400);
                }

                if ($transfer['fromType'] === 'trading_account' && !empty($transfer['toTradingAccountId'])) {
                    try {
                        $approveDepositOperation = $this->executeInternalTransferApproveDeposit(
                            $transfer,
                            'CRM internal transfer bulk-approve deposit #' . (int)$transferId
                        );
                    } catch (Exception $e) {
                        $apiMessage = "Bulk approval failed at transfer ID {$transferId}: Failed to call target platform deposit - " . $e->getMessage();
                        $opLog->logInternalTransferBulkApprove($subModule, [], false, $apiMessage);
                        Response::error($apiMessage, 500);
                    }
                }

                $db = Database::getInstance();
                $db->beginTransaction();

                try {
                    // 更新状态为processing
                    $this->transferModel->update($transferId, [
                        'status' => 'processing',
                        'approvedAt' => date('Y-m-d H:i:s'),
                        'approvedBy' => $admin['userId'],
                        'adminNotes' => $adminNotes
                    ]);

                    // 记录状态历史
                    $this->statusHistoryModel->logStatusChange(
                        $transferId,
                        'pending',
                        'processing',
                        'Internal transfer approved by admin',
                        $admin['userId']
                    );

                    // 完成转账（更新状态为completed）
                    $this->transferModel->update($transferId, [
                        'status' => 'completed',
                        'completedAt' => date('Y-m-d H:i:s')
                    ]);

                    // 记录状态历史
                    $this->statusHistoryModel->logStatusChange(
                        $transferId,
                        'processing',
                        'completed',
                        'Internal transfer completed',
                        $admin['userId']
                    );

                    $db->commit();
                    if (
                        $approveDepositOperation &&
                        (
                            !isset($transfer['toPlatformAmount']) ||
                            $transfer['toPlatformAmount'] === null ||
                            $transfer['toPlatformAmount'] === ''
                        )
                    ) {
                        $this->persistInternalTransferPlatformSnapshots((int)$transferId, [$approveDepositOperation], $transfer);
                    }

                    $txnId = trim((string) ($transfer['transactionId'] ?? ''));
                    if ($txnId !== '') {
                        $approvedTransactionIds[] = $txnId;
                    }

                    $results[] = [
                        'transferId' => $transferId,
                        'success' => true,
                        'message' => 'Approved'
                    ];
                    $successCount++;

                } catch (Exception $e) {
                    $db->rollBack();
                    if ($approveDepositOperation !== null) {
                        $this->rollbackPlatformTransferOperations([$approveDepositOperation], 'bulk-approve-' . (string)$transferId);
                    }
                    // 批量操作遇到错误立即停止
                    $errorMsg = $e->getMessage();
                    $apiMessage = "Bulk approval failed at transfer ID {$transferId}: {$errorMsg}";
                    $opLog->logInternalTransferBulkApprove($subModule, [], false, $apiMessage);
                    Response::error($apiMessage, 400);
                }

            } catch (Exception $e) {
                // 批量操作遇到错误立即停止
                $errorMsg = $e->getMessage();
                $apiMessage = "Bulk approval failed at transfer ID {$transferId}: {$errorMsg}";
                $opLog->logInternalTransferBulkApprove($subModule, [], false, $apiMessage);
                Response::error($apiMessage, 400);
            }
        }

        if ($successCount > 0 && $this->hasApproveSyncTransfers($transferIds)) {
            $this->dispatchOrdersBalanceSyncTask();
        }

        $opLog->logInternalTransferBulkApprove($subModule, $approvedTransactionIds, true);

        Response::success([
            'results' => $results,
            'summary' => [
                'total' => count($transferIds),
                'success' => $successCount,
                'failed' => $failCount
            ]
        ], "Bulk approval completed: {$successCount} succeeded, {$failCount} failed");
    }

    /**
     * 批量添加标签
     * POST /api/internal-transfers/bulk-add-tags
     */
    public function bulkAddTags() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();

        $validator = new Validator($data, [
            'transferIds' => 'required|array',
            'tagName' => 'required|string'
        ]);
        if (!$validator->validate()) {
            $tagBulkErrors = $validator->getErrors();
            $opLog->logInternalTransferTagBulk(
                $subModule,
                [],
                '',
                false,
                OperationLogTextHelpers::validationErrorsToMessage($tagBulkErrors)
            );
            Response::validationError($tagBulkErrors);
        }

        $transferIds = $data['transferIds'];
        $tagName = trim($data['tagName']);

        try {
            $tag = $this->tagModel->findOrCreate($tagName, $admin['userId']);
            $this->tagAssignmentModel->bulkAssignTag($transferIds, $tag['id'], $admin['userId']);
        } catch (Exception $e) {
            $opLog->logInternalTransferTagBulk($subModule, [], $tagName, false, $e->getMessage());
            Response::serverError($e->getMessage());
        }

        $transferSnapshots = [];
        foreach ($transferIds as $transferId) {
            $row = $this->transferModel->findById($transferId);
            if (!$row) {
                continue;
            }
            $transferSnapshots[] = [
                'userId' => (int) ($row['userId'] ?? 0),
                'transactionId' => (string) ($row['transactionId'] ?? ''),
            ];
        }

        $opLog->logInternalTransferTagBulk($subModule, $transferSnapshots, $tagName, true);

        Response::success([
            'tag' => $tag,
            'assignedCount' => count($transferIds)
        ], "Tag '{$tagName}' added to " . count($transferIds) . " transfer(s)");
    }

    /**
     * 导出内部转账数据
     * POST /api/internal-transfers/export
     */
    public function export() {
        $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogInternalTransfers($data);
        $opLog = new AdminOperationLogWriter();
        $transferIds = $data['transferIds'] ?? [];
        $format = $data['format'] ?? 'csv';

        if (empty($transferIds)) {
            $opLog->logInternalTransferExport(
                $subModule,
                0,
                $format,
                false,
                'Please select at least one transfer to export'
            );
            Response::validationError([
                'transferIds' => ['Please select at least one transfer to export']
            ]);
        }

        // 获取转账数据
        $transfers = [];
        foreach ($transferIds as $transferId) {
            $transfer = $this->transferModel->getInternalTransferDetails($transferId);
            if ($transfer) {
                $transfers[] = $transfer;
            }
        }

        if (empty($transfers)) {
            $opLog->logInternalTransferExport($subModule, 0, $format, false, 'No valid transfers found for export');
            Response::error('No valid transfers found for export', 404);
        }

        // 返回数据用于前端导出（成功日志由前端 recordOperationLog 记录）
        Response::success([
            'transfers' => $transfers,
            'format' => $format,
            'count' => count($transfers)
        ], 'Export data ready');
    }

    private function resolveTransferClientName(array $transfer) {
        $name = trim((string) (($transfer['firstName'] ?? '') . ' ' . ($transfer['lastName'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        $userId = (int) ($transfer['userId'] ?? 0);
        if ($userId > 0) {
            $user = $this->userModel->findById($userId);
            if ($user) {
                return OperationLogTextHelpers::formatClientDisplayName($user);
            }
        }
        return $userId > 0 ? ('Client #' . $userId) : '—';
    }

    /**
     * 要求管理员认证
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

        return [
            'userId' => $userId
        ];
    }
}
