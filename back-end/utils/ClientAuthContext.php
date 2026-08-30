<?php
/**
 * 客户端请求的「当前用户」解析
 * 优先 X-Preview-Token（预览模式），否则 JWT
 * 供所有客户端 API 使用，保证预览与正式登录看到同一套数据
 */

require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/../models/AdminPreviewToken.php';

class ClientAuthContext {

    /**
     * 获取当前客户端用户 ID（预览 token 或 JWT 二选一）
     * @return int|null
     */
    public static function getCurrentClientUserId() {
        $previewToken = self::getPreviewTokenFromRequest();
        if ($previewToken !== '') {
            $tokenModel = new AdminPreviewToken();
            $session = $tokenModel->validateToken($previewToken);
            if ($session) {
                return (int) $session['clientUserId'];
            }
        }

        $payload = JWT::getPayload();
        if ($payload && isset($payload['userId'])) {
            $type = $payload['type'] ?? '';
            // 同时认 client（Web）和 app（移动端）：两套 JWT 指向同一张 clientUsers 表，
            // 业务侧逻辑可完全复用，不需要每个 controller 写双分支。
            if ($type === 'client' || $type === 'app') {
                return (int) $payload['userId'];
            }
        }
        return null;
    }

    /**
     * 从请求头读取 X-Preview-Token
     * @return string
     */
    private static function getPreviewTokenFromRequest() {
        if (isset($_SERVER['HTTP_X_PREVIEW_TOKEN'])) {
            return trim((string) $_SERVER['HTTP_X_PREVIEW_TOKEN']);
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['X-Preview-Token'])) {
                return trim((string) $headers['X-Preview-Token']);
            }
            if (isset($headers['x-preview-token'])) {
                return trim((string) $headers['x-preview-token']);
            }
        }
        return '';
    }

    /**
     * 若请求带有有效的 X-Preview-Token，则设置全局当前用户并返回 true，供 API 入口跳过 JWT 认证。
     * 仅当预览 token 有效时返回 true；JWT 请求仍须走 AuthMiddleware::authenticate()。
     * @return bool
     */
    public static function allowPreviewRequest() {
        $previewToken = self::getPreviewTokenFromRequest();
        if ($previewToken === '') {
            return false;
        }
        $tokenModel = new AdminPreviewToken();
        $session = $tokenModel->validateToken($previewToken);
        if (!$session) {
            return false;
        }
        $GLOBALS['current_user'] = [
            'userId' => (int) $session['clientUserId'],
            'type' => 'client'
        ];
        return true;
    }
}
