<?php
/**
 * Admin Balance Adjustment Controller
 *
 * 管理 admin 手工对用户 wallet 的打钱(credit) / 扣钱(debit) 操作。
 * 每次操作会同时写入：
 *   - 一行 deposits / withdrawals（tradingAccountId=NULL，gatewaySettingId=system 网关 id，status='pending'）
 *   - 一行 admin_balance_adjustments（带 depositId 或 withdrawalId）
 * 真正的余额变化由 deposit / withdrawal 走自己的审批流后由 WalletBalanceService 自然反映。
 *
 * 注意：debit 时要先 check 当前可用余额 >= amount，不够直接拒绝。
 *      （pending 状态的 withdrawal 在 WalletBalanceService 公式里也会被计入扣减，
 *       并发短暂期内可能出现少量超扣，可在 withdrawal 审批时再次拦下，这里只做主路径校验。）
 */

require_once __DIR__ . '/../models/AdminBalanceAdjustment.php';
require_once __DIR__ . '/../models/Deposit.php';
require_once __DIR__ . '/../models/Withdrawal.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/DepositStatusHistory.php';
require_once __DIR__ . '/../models/WithdrawalStatusHistory.php';
require_once __DIR__ . '/../services/WalletBalanceService.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class AdminBalanceAdjustmentController {

    const SYSTEM_GATEWAY_KEY = 'system';
    const PERMISSION_KEY = 'page_clientsdetail_funding_adjust';

    private $db;
    private $adjustmentModel;
    private $depositModel;
    private $withdrawalModel;
    private $gatewayModel;
    private $depositHistoryModel;
    private $withdrawalHistoryModel;
    private $walletBalanceService;

    public function __construct() {
        $this->db                     = Database::getInstance();
        $this->adjustmentModel        = new AdminBalanceAdjustment();
        $this->depositModel           = new Deposit();
        $this->withdrawalModel        = new Withdrawal();
        $this->gatewayModel           = new PaymentGatewaySetting();
        $this->depositHistoryModel    = new DepositStatusHistory();
        $this->withdrawalHistoryModel = new WithdrawalStatusHistory();
        $this->walletBalanceService   = new WalletBalanceService();
    }

    /**
     * POST /api/admin/balance-adjustments
     * Body: { userId, direction: 'credit'|'debit', amount, currencyCode, reason }
     */
    public function create() {
        $admin = $this->requireAdmin();
        AuthMiddleware::checkPermission(self::PERMISSION_KEY);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $userId       = isset($data['userId']) ? (int)$data['userId'] : 0;
        $direction    = $data['direction'] ?? '';
        $amount       = isset($data['amount']) ? (float)$data['amount'] : 0;
        $currencyCode = trim($data['currencyCode'] ?? '');
        $reason       = trim($data['reason'] ?? '');

        // 基础校验
        if ($userId <= 0) {
            Response::error('userId is required', 400);
        }
        if (!in_array($direction, [AdminBalanceAdjustment::DIRECTION_CREDIT, AdminBalanceAdjustment::DIRECTION_DEBIT], true)) {
            Response::error('direction must be credit or debit', 400);
        }
        if ($amount <= 0) {
            Response::error('amount must be greater than zero', 400);
        }
        if ($currencyCode === '') {
            Response::error('currencyCode is required', 400);
        }
        if ($reason === '') {
            Response::error('reason is required', 400);
        }

        // 目标用户存在性校验
        $userRow = $this->db->fetchOne('SELECT id FROM clientUsers WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$userRow) {
            Response::error('User not found', 404);
        }

        // system 网关存在性校验
        $systemGateway = $this->gatewayModel->findByKey(self::SYSTEM_GATEWAY_KEY);
        if (!$systemGateway) {
            Response::error('System payment gateway is not configured', 500);
        }
        $gatewaySettingId = (int)$systemGateway['id'];

        // 扣钱前先校验可用余额；不够直接拒绝（按业务要求）
        if ($direction === AdminBalanceAdjustment::DIRECTION_DEBIT) {
            $available = $this->walletBalanceService->getAvailableBalance($userId);
            if ($amount > $available) {
                Response::error('Insufficient wallet balance', 400, [
                    'available' => $available,
                    'requested' => $amount,
                ]);
                return;
            }
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $this->db->beginTransaction();
        try {
            $depositId    = null;
            $withdrawalId = null;

            if ($direction === AdminBalanceAdjustment::DIRECTION_CREDIT) {
                $depositId = $this->createSystemDeposit(
                    $userId, $amount, $currencyCode, $gatewaySettingId, $reason, $ipAddress, $admin['userId']
                );
            } else {
                $withdrawalId = $this->createSystemWithdrawal(
                    $userId, $amount, $currencyCode, $gatewaySettingId, $reason, $ipAddress, $admin['userId']
                );
            }

            $adjustmentId = $this->adjustmentModel->create([
                'userId'       => $userId,
                'direction'    => $direction,
                'amount'       => $amount,
                'currencyCode' => $currencyCode,
                'depositId'    => $depositId,
                'withdrawalId' => $withdrawalId,
                'reason'       => $reason,
                'operatedBy'   => (int)$admin['userId'],
            ]);

            $this->db->commit();

            Response::success([
                'id'           => (int)$adjustmentId,
                'userId'       => $userId,
                'direction'    => $direction,
                'amount'       => $amount,
                'currencyCode' => $currencyCode,
                'depositId'    => $depositId,
                'withdrawalId' => $withdrawalId,
                'reason'       => $reason,
                'operatedBy'   => (int)$admin['userId'],
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::error('Failed to create adjustment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/balance-adjustments/wallet-balance/{userId}
     * 给 admin 打钱弹窗预览"当前余额"用，复用 WalletBalanceService 保证公式一致。
     */
    public function walletBalance($userId) {
        $this->requireAdmin();
        AuthMiddleware::checkPermission(self::PERMISSION_KEY);
        $userId = (int)$userId;
        if ($userId <= 0) {
            Response::error('userId is required', 400);
        }
        $userRow = $this->db->fetchOne('SELECT id FROM clientUsers WHERE id = :id LIMIT 1', ['id' => $userId]);
        if (!$userRow) {
            Response::error('User not found', 404);
        }
        $breakdown = $this->walletBalanceService->getBreakdown($userId);
        Response::success([
            'userId'           => $userId,
            'availableBalance' => (float)$breakdown['availableBalance'],
            'breakdown'        => $breakdown,
        ]);
    }

    /**
     * GET /api/admin/balance-adjustments
     * Query: page, per_page, userId, direction
     */
    public function index() {
        $this->requireAdmin();
        AuthMiddleware::checkPermission(self::PERMISSION_KEY);

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $where  = '1=1';
        $params = [];
        if (!empty($_GET['userId'])) {
            $where .= ' AND a.userId = :userId';
            $params['userId'] = (int)$_GET['userId'];
        }
        if (!empty($_GET['direction'])) {
            $where .= ' AND a.direction = :direction';
            $params['direction'] = $_GET['direction'];
        }

        $sql = "SELECT a.*,
                       cu.email AS userEmail,
                       TRIM(CONCAT(IFNULL(cu.firstName, ''), ' ', IFNULL(cu.lastName, ''))) AS userName,
                       au.fullName AS operatorName,
                       d.status AS depositStatus,
                       d.transactionId AS depositTransactionId,
                       w.status AS withdrawalStatus,
                       w.transactionId AS withdrawalTransactionId
                FROM admin_balance_adjustments a
                LEFT JOIN clientUsers cu ON cu.id = a.userId
                LEFT JOIN adminUsers au ON au.id = a.operatedBy
                LEFT JOIN deposits d ON d.id = a.depositId
                LEFT JOIN withdrawals w ON w.id = a.withdrawalId
                WHERE {$where}
                ORDER BY a.createdAt DESC, a.id DESC
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $items = $this->db->fetchAll($sql, $params);

        $countRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM admin_balance_adjustments a WHERE {$where}",
            $params
        );
        $total = (int)($countRow['cnt'] ?? 0);

        Response::success([
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ]);
    }

    /**
     * 写一行 deposit 订单，状态 pending，等待 admin 在 deposit 审批流里处理。
     * tradingAccountId 留 NULL，让下游 WalletBalanceService 把"已完成且无 tradingAccountId"的存款计入 wallet。
     */
    private function createSystemDeposit($userId, $amount, $currencyCode, $gatewaySettingId, $reason, $ipAddress, $adminId) {
        $transactionId = $this->generateTransactionId('D');
        $depositId = $this->db->insert('deposits', [
            'transactionId'    => $transactionId,
            'userId'           => $userId,
            'tradingAccountId' => null,
            'gatewaySettingId' => $gatewaySettingId,
            'amount'           => $amount,
            'currencyCode'     => $currencyCode,
            'displayUnit'      => $currencyCode,
            'platformAmount'   => $amount,
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
            'description'    => 'Admin manual deposit created (Admin Pay)',
            'changedBy'      => $adminId,
        ]);

        return (int)$depositId;
    }

    /**
     * 写一行 withdrawal 订单，状态 pending。
     * 注意 WalletBalanceService 的公式里 pending 状态的 withdrawal 已经会被算进"占用"，
     * 所以余额会立即扣减，不会被并发的下一个 admin 扣空。
     */
    private function createSystemWithdrawal($userId, $amount, $currencyCode, $gatewaySettingId, $reason, $ipAddress, $adminId) {
        $transactionId = $this->generateTransactionId('W');
        $withdrawalId = $this->db->insert('withdrawals', [
            'transactionId'    => $transactionId,
            'userId'           => $userId,
            'tradingAccountId' => null,
            'gatewaySettingId' => $gatewaySettingId,
            'amount'           => $amount,
            'currencyCode'     => $currencyCode,
            'displayUnit'      => $currencyCode,
            'platformAmount'   => $amount,
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
            'description'   => 'Admin manual withdrawal created (Admin Pay)',
            'changedBy'      => $adminId,
        ]);

        return (int)$withdrawalId;
    }

    /**
     * 与项目现有 Withdrawal::generateTransactionId 同格式，suffix 用 D=deposit / W=withdrawal 区分
     */
    private function generateTransactionId($suffix) {
        $date = date('Ymd');
        $random = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        return "TXN-{$date}-{$suffix}{$random}";
    }

    /**
     * 要求管理员认证（沿用 DepositController 里的同款方式）
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
            'userId' => $userId,
        ];
    }
}
