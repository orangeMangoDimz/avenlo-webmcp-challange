<?php
/**
 * Client Ticket Controller
 * 处理客户端工单相关接口
 */

require_once __DIR__ . '/../models/ClientTicket.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/SystemOperationLogTexts.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';

class ClientTicketController {
    private $ticketModel;
    private $userModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;

    public function __construct() {
        $this->ticketModel = new ClientTicket();
        $this->userModel = new ClientUser();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
    }

    /**
     * 提交工单（客户端）
     * POST /api/client-tickets/create
     */
    public function create() {
        $client = $this->requireClient();
        $userId = $client['userId'];

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'title' => 'required|string|max:255',
            'content' => 'required|string'
        ]);

        $title = trim($data['title']);
        $content = trim($data['content']);

        if (empty($title) || empty($content)) {
            Response::validationError([
                'title' => ['Title and content are required'],
                'content' => ['Title and content are required']
            ]);
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // 创建工单
            $ticketId = $this->ticketModel->create([
                'clientId' => $userId,
                'title' => $title,
                'content' => $content,
                'status' => 'open',
                'priority' => 'normal',
                'createdAt' => date('Y-m-d H:i:s')
            ]);

            // 获取客户信息
            $user = $this->userModel->findById($userId);

            // 为所有管理员创建通知
            $this->notifyAllAdmins($ticketId, $user, $title, $content);

            $db->commit();

            // 获取创建的工单信息
            $ticket = $this->ticketModel->getTicketDetails($ticketId);

            Response::created($ticket, 'Ticket submitted successfully');

        } catch (Exception $e) {
            $db->rollBack();
            Response::error('Failed to submit ticket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取工单列表（后台）
     * GET /api/client-tickets
     */
    public function index() {
        $this->requireAdmin();

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;

        $filters = [];
        if (isset($_GET['startDate'])) {
            $filters['startDate'] = $_GET['startDate'];
        }
        if (isset($_GET['endDate'])) {
            $filters['endDate'] = $_GET['endDate'];
        }
        if (isset($_GET['clientId'])) {
            $filters['clientId'] = $_GET['clientId'];
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clienttickets');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }
        if ($scope['scope'] === 'own') {
            $filters['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $result = $this->ticketModel->getTickets($page, $perPage, $filters);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个工单详情（后台）
     * GET /api/client-tickets/detail/{id}
     */
    public function show($id) {
        $admin = $this->requireAdmin();
        $adminId = (int)$admin['userId'];
        $roleId = (int)($admin['roleId'] ?? 0);

        $ticket = $this->ticketModel->getTicketDetails($id);

        if (!$ticket) {
            Response::notFound('Ticket not found');
        }

        // roleId = 1（Administrator）：可查看任意工单；其余角色规则不变
        if ($roleId !== 1) {
            $client = $this->userModel->findById($ticket['clientId']);
            if ($client) {
                $accountManagerId = $client['accountManagerId'] ?? null;
                if ($accountManagerId !== null && (int)$accountManagerId !== $adminId) {
                    Response::forbidden('You do not have permission to view this ticket');
                }
            }
        }

        Response::success($ticket);
    }

    /**
     * 标记工单状态：已解决/未解决（后台）
     * POST /api/client-tickets/{id}/status  body: { status: 'resolved' | 'open' }
     */
    public function updateStatus($id) {
        $admin = $this->requireAdmin();
        // 标记是写操作，单独走 resolve 权限（Administrator 默认拥有全部权限）
        AuthMiddleware::checkPermission('page_clienttickets_resolve');

        $adminId = (int)$admin['userId'];
        $roleId = (int)($admin['roleId'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $ticket = $this->ticketModel->getTicketDetails($id);
        if (!$ticket) {
            $this->logTicketStatusUpdate($data, null, null, false, 'Ticket not found');
            Response::notFound('Ticket not found');
        }

        // 非 Administrator：只能操作自己负责客户的工单，口径与 show() 一致
        if ($roleId !== 1) {
            $client = $this->userModel->findById($ticket['clientId']);
            if ($client) {
                $accountManagerId = $client['accountManagerId'] ?? null;
                if ($accountManagerId !== null && (int)$accountManagerId !== $adminId) {
                    $this->logTicketStatusUpdate(
                        $data,
                        $ticket,
                        $data['status'] ?? null,
                        false,
                        'You do not have permission to update this ticket'
                    );
                    Response::forbidden('You do not have permission to update this ticket');
                }
            }
        }

        $status = $data['status'] ?? null;
        if (!in_array($status, ['resolved', 'open'], true)) {
            $this->logTicketStatusUpdate($data, $ticket, $status, false, 'status must be resolved or open');
            Response::validationError(['status' => ['status must be resolved or open']]);
        }

        $this->ticketModel->update($id, ['status' => $status]);

        $updated = $this->ticketModel->getTicketDetails($id);
        $this->logTicketStatusUpdate($data, $updated, $status, true);

        Response::success($updated, 'Ticket status updated');
    }

    /**
     * 客户工单 — 标记已解决/未解决（log_system / client_tickets）
     */
    private function logTicketStatusUpdate($data, $ticket, $status, $success, $failureMessage = '') {
        if (!OperationLogPages::shouldLogForPageAlias($data, 'page_client_tickets')) {
            return;
        }

        $subModule = OperationLogPages::subModuleKeyByAlias('page_client_tickets');
        $clientId = is_array($ticket) ? (int) ($ticket['clientId'] ?? 0) : 0;
        $targetId = $clientId > 0 ? $clientId : null;
        $writer = new AdminOperationLogWriter();

        if ($success) {
            list($detailZh, $detailEn) = SystemOperationLogTexts::clientTicketStatusUpdateSuccess(
                is_array($ticket) ? ($ticket['title'] ?? '') : '',
                is_array($ticket) ? AdminOperationLogWriter::formatClientDisplayName($ticket) : '',
                $status
            );
        } else {
            list($detailZh, $detailEn) = SystemOperationLogTexts::clientTicketStatusUpdateFailure($failureMessage);
        }

        $writer->logSystemMutation($subModule, 'edit', $targetId, $detailZh, $detailEn);
    }

    /**
     * 为管理员创建通知
     * 如果用户有 accountManagerId，只发送给该管理员；否则发送给 adminId=0（所有管理员都可以查看）
     */
    private function notifyAllAdmins($ticketId, $user, $title, $content) {
        $userName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if (empty($userName)) {
            $userName = $user['email'] ?? 'Client';
        }

        $subject = "New Support Ticket from {$userName}";
        $message = "Client {$userName} ({$user['email']}) has submitted a new support ticket:\n\nTitle: {$title}\n\nContent: {$content}";

        $metadata = json_encode([
            'ticketId' => $ticketId,
            'clientId' => $user['id'],
            'action' => 'view_ticket',
            'actionUrl' => '/client-tickets'
        ]);

        // 检查用户是否有 accountManagerId
        $accountManagerId = $user['accountManagerId'] ?? null;

        if ($accountManagerId) {
            // 只发送给指定的 Manager
            $adminId = $accountManagerId;
            $this->createNotificationForAdmin($adminId, $subject, $message, $metadata);
        } else {
            // 没有 Manager，发送给 adminId=0（所有管理员都可以查看）
            $adminId = 0;
            $this->createNotificationForAdmin($adminId, $subject, $message, $metadata);
        }
    }

    /**
     * 为指定管理员创建通知
     */
    private function createNotificationForAdmin($adminId, $subject, $message, $metadata) {
        // 创建主通知记录
        $notificationId = $this->adminNotificationModel->create([
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'normal',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'createdBy' => null, // 系统创建
            'createdAt' => date('Y-m-d H:i:s')
        ]);

        // 创建系统通知渠道
        $this->adminNotificationDeliveryModel->create([
            'notificationId' => $notificationId,
            'channel' => 'system',
            'status' => 'sent',
            'sentAt' => date('Y-m-d H:i:s'),
            'createdAt' => date('Y-m-d H:i:s')
        ]);

        // 创建系统通知记录
        $this->adminSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => 'client_ticket',
            'metadata' => $metadata,
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'createdAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 要求客户端认证
     */
    private function requireClient() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            return ['userId' => $userId];
        }
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'client') {
            Response::forbidden('Client authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return ['userId' => $userId];
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
            'userId' => $userId,
            'roleId' => (int)($payload['roleId'] ?? 0)
        ];
    }
}
