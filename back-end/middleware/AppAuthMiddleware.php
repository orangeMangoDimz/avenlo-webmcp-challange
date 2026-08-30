<?php
/**
 * App 端认证中间件
 *
 * 校验链：
 *   1. Authorization: Bearer <jwt> 必须存在且签名有效
 *   2. payload.type === 'app'
 *   3. payload.sessionId 对应的 appSessions 行未被吊销、未过期
 *   4. clientUsers 状态非 inactive / suspended
 *
 * 通过后将 payload + session 写入 $GLOBALS['app_current_user'] / $GLOBALS['app_current_session']。
 */

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/AppSession.php';
require_once __DIR__ . '/../models/ClientUser.php';

class AppAuthMiddleware {

    public static function authenticate() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::unauthorized('No token provided');
        }

        try {
            $payload = JWT::decode($token);
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token: ' . $e->getMessage());
        }

        if (($payload['type'] ?? '') !== 'app') {
            Response::unauthorized('Invalid token type');
        }

        $sessionId = isset($payload['sessionId']) ? (int)$payload['sessionId'] : 0;
        $userId = isset($payload['userId']) ? (int)$payload['userId'] : 0;
        if ($sessionId <= 0 || $userId <= 0) {
            Response::unauthorized('Invalid token payload');
        }

        $sessionModel = new AppSession();
        $session = $sessionModel->findActive($sessionId);
        if (!$session || (int)$session['userId'] !== $userId) {
            Response::unauthorized('Session has been revoked or expired');
        }

        $userModel = new ClientUser();
        $user = $userModel->findById($userId);
        if (!$user) {
            Response::unauthorized('User not found');
        }

        $status = strtolower((string)($user['status'] ?? ''));
        if ($status === 'inactive' || $status === 'closed') {
            Response::forbidden('账户已注销或停用');
        }
        if ($status === 'suspended') {
            Response::forbidden('账户已被暂停');
        }

        // 异步刷新最后活跃时间（失败不影响请求）
        try {
            $sessionModel->touch($sessionId);
        } catch (Throwable $e) {
            // ignore
        }

        $GLOBALS['app_current_user'] = $payload;
        $GLOBALS['app_current_session'] = $session;
        return true;
    }

    public static function getCurrentUser() {
        return $GLOBALS['app_current_user'] ?? null;
    }

    public static function getCurrentSession() {
        return $GLOBALS['app_current_session'] ?? null;
    }
}
