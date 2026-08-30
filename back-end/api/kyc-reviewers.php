<?php
/**
 * KYC Reviewers API 路由
 * KYC审核员管理
 */

require_once __DIR__ . '/../controllers/KycReviewerController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$reviewerController = new KycReviewerController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 检查认证（管理端）
AuthMiddleware::authenticate();
$guardKycReviewer = static function ($permissionKeys) {
    AuthMiddleware::checkAnyPermission((array)$permissionKeys);
};

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $id = $pathParts[0] ?? null;
    $action = $pathParts[1] ?? null;

    if (empty($path)) {
        // /api/kyc-reviewers
        if ($method === 'GET') {
            $guardKycReviewer(['page_kyclist_readonly', 'page_kyclist_assignreviewer']);
            $reviewerController->index();
        }
    } elseif ($id && $action === 'statistics') {
        // /api/kyc-reviewers/{id}/statistics
        if ($method === 'GET') {
            $guardKycReviewer(['page_kyclist_readonly', 'page_kyclist_assignreviewer']);
            $reviewerController->getStatistics($id);
        }
    } else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
