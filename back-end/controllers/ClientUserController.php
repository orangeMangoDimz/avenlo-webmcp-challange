<?php
/**
 * 客户用户控制器
 * 管理客户用户的CRUD操作
 */

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/ClientActivityLog.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class ClientUserController {
    private $userModel;
    private $activityLogModel;

    public function __construct() {
        $this->userModel = new ClientUser();
        $this->activityLogModel = new ClientActivityLog();
    }

    /**
     * 获取所有客户（分页）
     * GET /api/client/users
     */
    public function index() {
        $this->requireAdminAuth();

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['perPage'] ?? 10;
        $status = $_GET['status'] ?? null;

        $conditions = [];
        if ($status) {
            $conditions['status'] = $status;
        }

        $users = $this->userModel->paginate($page, $perPage, $conditions, 'createdAt DESC');

        Response::success($users);
    }

    /**
     * 搜索客户
     * GET /api/client/users/search
     */
    public function search() {
        $this->requireAdminAuth();

        $keyword = $_GET['keyword'] ?? '';
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['perPage'] ?? 10;

        if (empty($keyword)) {
            Response::error('Search keyword is required', 400);
        }

        $results = $this->userModel->search($keyword, $page, $perPage);

        Response::success($results);
    }

    /**
     * 获取单个客户
     * GET /api/client/users/:id
     */
    public function show($id) {
        $this->requireAdminAuth();

        $user = $this->userModel->findById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::success($user);
    }

    /**
     * 更新客户信息
     * PUT /api/client/users/:id
     */
    public function update($id) {
        $this->requireAdminAuth();

        $data = json_decode(file_get_contents('php://input'), true);

        $user = $this->userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
        }

        // 移除不应该被更新的字段
        unset($data['passwordHash']);
        unset($data['id']);
        unset($data['createdAt']);

        $this->userModel->update($id, $data);

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $id,
            'activityType' => 'profile_updated',
            'description' => 'Profile updated by admin',
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'changes' => $data
            ])
        ]);

        Response::success(null, 'User updated successfully');
    }

    /**
     * 删除客户
     * DELETE /api/client/users/:id
     */
    public function delete($id) {
        $this->requireAdminAuth();

        $user = $this->userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
        }

        $this->userModel->delete($id);

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'activityType' => 'user_deleted',
            'description' => 'User deleted by admin: ' . $user['email'],
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'deletedUser' => $user
            ])
        ]);

        Response::success(null, 'User deleted successfully');
    }

    /**
     * 更新客户状态
     * PUT /api/client/users/:id/status
     */
    public function updateStatus($id) {
        $this->requireAdminAuth();

        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'status' => 'required'
        ]);

        $user = $this->userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
        }

        $this->userModel->update($id, ['status' => $data['status']]);

        // 记录活动
        $this->activityLogModel->logActivity([
            'userId' => $id,
            'activityType' => 'status_changed',
            'description' => "Status changed from {$user['status']} to {$data['status']}"
        ]);

        Response::success(null, 'User status updated successfully');
    }

    /**
     * 获取客户活动日志
     * GET /api/client/users/:id/activity-log
     */
    public function getActivityLog($id) {
        $this->requireAdminAuth();

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['perPage'] ?? 50;

        $logs = $this->activityLogModel->getUserActivity($id, $page, $perPage);

        Response::success($logs);
    }

    /**
     * 获取客户统计
     * GET /api/client/users/stats
     */
    public function getStats() {
        $this->requireAdminAuth();

        $statsByStatus = $this->userModel->getStatsByStatus();
        $totalUsers = $this->userModel->count();
        $activeUsers = $this->userModel->count(['status' => 'active']);
        $pendingVerification = $this->userModel->count(['status' => 'pending_verification']);

        Response::success([
            'total' => $totalUsers,
            'active' => $activeUsers,
            'pendingVerification' => $pendingVerification,
            'byStatus' => $statsByStatus
        ]);
    }

    /**
     * 重置客户密码（管理员操作）
     * POST /api/client/users/:id/reset-password
     */
    public function adminResetPassword($id) {
        $this->requireAdminAuth();

        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'newPassword' => 'required|min:6'
        ]);

        $user = $this->userModel->findById($id);
        if (!$user) {
            Response::notFound('User not found');
        }

        // 更新密码
        $newPasswordHash = $this->userModel->hashPassword($data['newPassword']);
        $this->userModel->update($id, ['passwordHash' => $newPasswordHash]);

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $id,
            'activityType' => 'password_reset_by_admin',
            'description' => 'Password reset by administrator',
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null
            ])
        ]);

        Response::success(null, 'Password reset successfully');
    }

    // ========== Helper Methods ==========

    private function requireAdminAuth() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::unauthorized();
        }

        try {
            $payload = JWT::decode($token);
            // 检查是否是管理员令牌（不是客户令牌）
            if (isset($payload['type']) && $payload['type'] === 'client') {
                Response::forbidden('Admin access required');
            }
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token');
        }
    }
}
