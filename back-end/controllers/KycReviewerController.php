<?php
/**
 * KYC Reviewer 控制器
 * 管理KYC审核员
 */

require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../utils/Response.php';

class KycReviewerController {
    private $userModel;
    private $submissionModel;

    public function __construct() {
        $this->userModel = new AdminUser();
        $this->submissionModel = new ClientKycSubmission();
    }

    /**
     * 获取审核员列表
     * GET /api/kyc-reviewers
     */
    public function index() {
        try {
            // 获取活跃的管理员用户作为审核员
            $sql = "SELECT id, username, email, fullName, roleId, status
                    FROM adminUsers
                    WHERE status = 'active' AND deletedAt IS NULL
                    ORDER BY fullName, username";

            $reviewers = $this->userModel->query($sql);

            // 格式化审核员数据
            $formattedReviewers = [];
            foreach ($reviewers as $reviewer) {
                $formattedReviewers[] = [
                    'id' => $reviewer['id'],
                    'fullName' => $reviewer['fullName'] ?: $reviewer['username'],
                    'username' => $reviewer['username'],
                    'email' => $reviewer['email'],
                    'role' => 'Reviewer',
                    'isActive' => $reviewer['status'] === 'active'
                ];
            }

            Response::success($formattedReviewers);

        } catch (Exception $e) {
            Response::error('Failed to get reviewers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取审核员统计信息
     * GET /api/kyc-reviewers/{id}/statistics
     */
    public function getStatistics($reviewerId) {
        try {
            // 验证审核员是否存在
            $reviewer = $this->userModel->findById($reviewerId);
            if (!$reviewer) {
                Response::notFound('Reviewer not found');
            }

            // 获取审核员的统计数据
            $sql = "SELECT
                        submissionStatus,
                        COUNT(*) as count
                    FROM clientKycSubmissions
                    WHERE reviewedBy = :reviewerId
                    GROUP BY submissionStatus";

            $statusStats = $this->userModel->query($sql, ['reviewerId' => $reviewerId]);

            // 格式化统计数据
            $stats = [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'under_review' => 0
            ];

            foreach ($statusStats as $stat) {
                $status = $stat['submissionStatus'];
                $count = (int)$stat['count'];
                $stats['total'] += $count;

                if (isset($stats[$status])) {
                    $stats[$status] = $count;
                } elseif ($status !== 'approved' && $status !== 'rejected') {
                    $stats['pending'] += $count;
                }
            }

            Response::success([
                'reviewer' => [
                    'id' => $reviewer['id'],
                    'fullName' => $reviewer['fullName'] ?: $reviewer['username'],
                    'username' => $reviewer['username']
                ],
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            Response::error('Failed to get reviewer statistics: ' . $e->getMessage(), 500);
        }
    }
}
