<?php
/**
 * 认证中间件
 * 验证JWT令牌
 */

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';
require_once __DIR__ . '/../models/ClientUser.php';

class AuthMiddleware {

    /**
     * 客户端 JWT 需要额外校验账号当前状态，避免被停用后旧 token 继续访问。
     */
    private static function validateClientAccess($payload) {
        if (($payload['type'] ?? '') !== 'client') {
            return;
        }

        $userId = isset($payload['userId']) ? (int)$payload['userId'] : 0;
        if ($userId <= 0) {
            Response::unauthorized('Invalid token payload');
        }

        $userModel = new ClientUser();
        $user = $userModel->findById($userId);
        if (!$user) {
            Response::unauthorized('User not found');
        }

        $status = strtolower((string)($user['status'] ?? ''));
        if ($status === 'inactive' || $status === 'closed') {
            Response::forbidden('Account is inactive');
        }

        if ($status === 'suspended') {
            Response::forbidden('Account is suspended');
        }
    }

    /**
     * 验证请求是否已认证
     */
    public static function authenticate() {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            Response::unauthorized('No token provided');
        }

        try {
            $payload = JWT::decode($token);
            self::validateClientAccess($payload);

            // 将用户信息存储到全局变量供后续使用
            $GLOBALS['current_user'] = $payload;

            RequestLogContext::attachUser(
                $payload['userId'] ?? null,
                $payload['type'] ?? 'anonymous'
            );

            return true;
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * 获取当前用户
     */
    public static function getCurrentUser() {
        return $GLOBALS['current_user'] ?? null;
    }

    /**
     * 确保当前请求来自管理员
     */
    public static function requireAdmin() {
        $currentUser = self::getCurrentUser();

        if (!$currentUser) {
            Response::unauthorized();
        }

        if (($currentUser['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        return $currentUser;
    }

    /**
     * 判断当前管理员是否拥有指定权限
     */
    public static function hasPermission($permissionKey) {
        require_once __DIR__ . '/../models/AdminPermission.php';

        $currentUser = self::requireAdmin();
        $permissionModel = new AdminPermission();

        return $permissionModel->userHasPermission(
            $currentUser['userId'],
            $permissionKey
        );
    }

    /**
     * 检查权限
     */
    public static function checkPermission($permissionKey) {
        if (!self::hasPermission($permissionKey)) {
            Response::forbidden('You do not have permission to perform this action');
        }

        return true;
    }

    /**
     * 检查当前管理员是否拥有任一权限
     */
    public static function checkAnyPermission($permissionKeys) {
        if (!is_array($permissionKeys) || empty($permissionKeys)) {
            Response::forbidden('No permission key provided');
        }

        foreach ($permissionKeys as $permissionKey) {
            if (self::hasPermission($permissionKey)) {
                return true;
            }
        }

        Response::forbidden('You do not have permission to perform this action');
    }
}
