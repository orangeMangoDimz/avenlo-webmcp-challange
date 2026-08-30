<?php
/**
 * OTP相关API路由
 * 通用OTP接口，支持后台和客户端调用
 */

require_once __DIR__ . '/../controllers/OTPController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/ApplicationErrorHandler.php';

$controller = new OTPController();
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['path'] ?? '', '/');

// 所有路由都需要认证
AuthMiddleware::authenticate();

try {
    // 路由映射（参考 withdrawals.php 的方式）
    if ($path === 'send-email-code') {
        // POST /api/otp/send-email-code
        if ($method === 'POST') {
            $controller->sendEmailVerificationCode();
        } else {
            Response::error('Method not allowed', 405);
        }
    } elseif ($path === 'verify-email-code') {
        // POST /api/otp/verify-email-code
        if ($method === 'POST') {
            $controller->verifyEmailVerificationCode();
        } else {
            Response::error('Method not allowed', 405);
        }
    } else {
        // 路由未找到
        Response::error('Route not found', 404);
    }

} catch (Throwable $e) {
    ApplicationErrorHandler::handleException($e);
}
