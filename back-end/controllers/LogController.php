<?php
/**
 * 日志管理控制器
 */

require_once __DIR__ . '/../models/AdminLoginLog.php';
require_once __DIR__ . '/../utils/Response.php';

class LogController {
    private $loginLogModel;

    public function __construct() {
        $this->loginLogModel = new AdminLoginLog();
    }

    /**
     * 获取登录日志列表
     * GET /api/logs/login
     */
    public function loginLogs() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        $userId = $_GET['user_id'] ?? null;
        $status = $_GET['status'] ?? null;

        if ($userId) {
            $logs = $this->loginLogModel->getUserLoginHistory($userId, $perPage);
            Response::success($logs);
        } else {
            $conditions = [];
            if ($status) {
                $conditions['loginStatus'] = $status;
            }

            $result = $this->loginLogModel->paginate($page, $perPage, $conditions, 'createdAt DESC');

            Response::paginated(
                $result['items'],
                $result['total'],
                $result['page'],
                $result['per_page']
            );
        }
    }

    /**
     * 获取登录统计
     * GET /api/logs/stats
     */
    public function loginStats() {
        $days = $_GET['days'] ?? 7;
        $stats = $this->loginLogModel->getLoginStats($days);

        Response::success($stats);
    }

    /**
     * 获取失败登录记录
     * GET /api/logs/failed-logins
     */
    public function failedLogins() {
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $logs = $this->loginLogModel->getFailedLogins($startDate, $endDate);

        Response::success($logs);
    }
}
