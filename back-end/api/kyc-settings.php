<?php
/**
 * KYC Settings API 路由
 * KYC通知设置、状态消息、邮件模板管理
 */

require_once __DIR__ . '/../controllers/KycSettingsController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$settingsController = new KycSettingsController();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // 解析路径参数
    $pathParts = explode('/', trim($path, '/'));
    $action = $pathParts[0] ?? null;
    $id = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;

    // ============================================================
    // 公开路由（不需要认证）
    // ============================================================

    // 获取客户端通知配置
    if ($action === 'client-notice' && $method === 'GET') {
        $settingsController->getClientNoticeConfig();
        exit;
    }

    // 获取客户端状态消息
    elseif ($action === 'client-status-message' && $id && $method === 'GET') {
        $settingsController->getClientStatusMessage($id);
    }

    // ============================================================
    // 受保护路由（需要管理员认证）
    // ============================================================

    // 所有其他路由都需要管理员认证
    AuthMiddleware::authenticate();

    // 主路由（获取所有设置概览）
    if (empty($action)) {
        // GET /api/kyc-settings
        if ($method === 'GET') {
            $settingsController->index();
        }
    }
    // 统计信息
    elseif ($action === 'statistics' && $method === 'GET') {
        // GET /api/kyc-settings/statistics
        $settingsController->statistics();
    }
    // ============================================================
    // 通知设置路由 - 获取
    // ============================================================
    elseif ($action === 'notice' && $id && $id !== 'modify') {
        if ($method === 'GET') {
            // GET /api/kyc-settings/notice/${key}
            $settingsController->getNoticeSettings($id);
        }
    }
    // ============================================================
    // 通知设置路由 - 更新
    // ============================================================
    elseif ($action === 'notice' && $id === 'modify') {
        if ($method === 'PUT') {
            // PUT /api/kyc-settings/notice/modify
            $settingsController->updateNoticeSettings();
        }
    }
    // ============================================================
    // 状态消息路由
    // ============================================================
    elseif ($action === 'status-messages') {
        if ($id === 'bulk' && $method === 'PUT') {
            // PUT /api/kyc-settings/status-messages/bulk
            $settingsController->bulkUpdateStatusMessages();
        } elseif ($id && $method === 'PUT') {
            // PUT /api/kyc-settings/status-messages/{id}
            $settingsController->updateStatusMessage($id);
        } elseif ($method === 'GET') {
            // GET /api/kyc-settings/status-messages
            $settingsController->getStatusMessages();
        }
    }
    // ============================================================
    // 要求项路由
    // ============================================================
    elseif ($action === 'requirements') {
        if ($id === 'reorder' && $method === 'PUT') {
            // PUT /api/kyc-settings/requirements/reorder
            $settingsController->reorderRequirements();
        } elseif ($id && $method === 'PUT') {
            // PUT /api/kyc-settings/requirements/{id}
            $settingsController->updateRequirement($id);
        } elseif ($id && $method === 'DELETE') {
            // DELETE /api/kyc-settings/requirements/{id}
            $settingsController->deleteRequirement($id);
        } elseif ($method === 'POST') {
            // POST /api/kyc-settings/requirements
            $settingsController->createRequirement();
        } elseif ($method === 'GET') {
            // GET /api/kyc-settings/requirements
            $settingsController->getRequirements();
        }
    }
    // ============================================================
    // 邮件模板路由
    // ============================================================
    elseif ($action === 'email-templates') {
        if ($id && $subAction === 'preview' && $method === 'POST') {
            // POST /api/kyc-settings/email-templates/{id}/preview
            $settingsController->previewEmailTemplate($id);
        } elseif ($id && $method === 'GET') {
            // GET /api/kyc-settings/email-templates/{id}
            $settingsController->getEmailTemplate($id);
        } elseif ($id && $method === 'PUT') {
            // PUT /api/kyc-settings/email-templates/{id}
            $settingsController->updateEmailTemplate($id);
        } elseif ($method === 'GET') {
            // GET /api/kyc-settings/email-templates
            $settingsController->getEmailTemplates();
        }
    }
    // ============================================================
    // 更改历史路由
    // ============================================================
    elseif ($action === 'change-history' && $method === 'GET') {
        // GET /api/kyc-settings/change-history
        $settingsController->getChangeHistory();
    }
    // ============================================================
    // 未找到路由
    // ============================================================
    else {
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
