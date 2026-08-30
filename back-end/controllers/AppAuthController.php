<?php
/**
 * App 端认证控制器
 * 复用 clientUsers 用户体系，独立 session 表（appSessions）以支持设备级吊销与刷新令牌。
 *
 * JWT payload:
 *   type      = 'app'
 *   userId    = clientUsers.id
 *   email     = clientUsers.email
 *   sessionId = appSessions.id        // 中间件二次校验，可吊销
 *   deviceId  = 上送的设备标识（可空）
 */

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/AppSession.php';
require_once __DIR__ . '/../models/AppWebHandoff.php';
require_once __DIR__ . '/../models/ClientActivityLog.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../middleware/AppAuthMiddleware.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/TradingPlatformJwt.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AppAuthController {
    private $userModel;
    private $sessionModel;
    private $activityLogModel;
    private $externalAccountModel;

    // App 专用 token 时长：access 2h，refresh 30 天
    private const ACCESS_TTL_SECONDS = 7200;
    private const REFRESH_TTL_SECONDS = 2592000;

    public function __construct() {
        $this->userModel = new ClientUser();
        $this->sessionModel = new AppSession();
        $this->activityLogModel = new ClientActivityLog();
        $this->externalAccountModel = new TradingAccountExternalAccount();
    }

    /**
     * 给 trading platform 签发独立 token。
     * - 用户已在 FP 开过户（有 SysUserId）→ 签 user-bound token（password grant）
     * - 用户尚未开户 → 签 client_credentials token，让前端依然能访问 trading platform 的公共接口
     * 任一分支签发失败（配置缺失等）均不影响主登录流程，返回 null。
     */
    private function issueTradingPlatformToken(array $user) {
        $sysUserId = $this->externalAccountModel->findFinanceProSysUserIdByUserId((int)$user['id']);
        try {
            if ($sysUserId === null || $sysUserId === '') {
                return TradingPlatformJwt::issueClientCredentials(self::ACCESS_TTL_SECONDS);
            }
            return TradingPlatformJwt::issue((string)$user['email'], $sysUserId, self::ACCESS_TTL_SECONDS);
        } catch (Exception $e) {
            // 配置缺失等签发失败不影响主登录流程
            error_log('TradingPlatformJwt issue failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * App 登录
     * POST /api/external/login
     * Body: { email, password, deviceId?, deviceModel?, os?, appVersion? }
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = $this->userModel->findByEmail($data['email'], true);

        if (!$user) {
            $this->activityLogModel->logActivity([
                'activityType' => 'app_login_failed',
                'description' => 'App login attempt with non-existent email: ' . $data['email']
            ]);
            Response::error('Invalid credentials', 401);
        }

        if ($user['status'] === 'suspended') {
            Response::error('账户已被暂停', 403);
        }
        if ($user['status'] === 'inactive' || $user['status'] === 'closed') {
            Response::error('账户已注销或停用', 403);
        }

        if (!$this->userModel->verifyPassword($data['password'], $user['passwordHash'])) {
            $this->activityLogModel->logActivity([
                'userId' => $user['id'],
                'activityType' => 'app_login_failed',
                'description' => 'App login: invalid password'
            ]);
            Response::error('Invalid credentials', 401);
        }

        if (empty($user['emailVerified'])) {
            Response::error('Please verify your email before logging in', 403);
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $this->userModel->updateLastLogin($user['id'], $ipAddress);

        $session = $this->sessionModel->createSession(
            $user['id'],
            [
                'deviceId' => isset($data['deviceId']) ? (string)$data['deviceId'] : null,
                'deviceModel' => isset($data['deviceModel']) ? (string)$data['deviceModel'] : null,
                'os' => isset($data['os']) ? (string)$data['os'] : null,
                'appVersion' => isset($data['appVersion']) ? (string)$data['appVersion'] : null,
                'ipAddress' => $ipAddress,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ],
            self::ACCESS_TTL_SECONDS,
            self::REFRESH_TTL_SECONDS
        );

        $this->activityLogModel->logLogin($user['id'], true);

        // 生成 access token（JWT）
        // 注意：JWT::encode 内部会用 config 的 expire 覆盖 exp；这里 access TTL 与 config['jwt']['expire']
        // 一致即可保持语义一致。如需独立 TTL，可后续在 JWT::encode 接收 ttl 参数。
        $accessToken = JWT::encode([
            'userId' => (int)$user['id'],
            'email' => $user['email'],
            'sessionId' => $session['id'],
            'deviceId' => isset($data['deviceId']) ? (string)$data['deviceId'] : null,
            'type' => 'app',
        ]);

        $userInfo = $this->userModel->findById($user['id']);
        $userInfo['hasAccount'] = (new TradingAccount())->count(['userId' => (int)$user['id']]) > 0;

        Response::success([
            'accessToken' => $accessToken,
            'refreshToken' => $session['refreshToken'],
            'expiresIn' => self::ACCESS_TTL_SECONDS,
            'refreshExpiresIn' => self::REFRESH_TTL_SECONDS,
            'tokenType' => 'Bearer',
            'tradingPlatformToken' => $this->issueTradingPlatformToken($user),
            'user' => $userInfo,
        ], 'Login successful');
    }

    /**
     * 刷新 access token（带 refresh token 轮转）
     * POST /api/external/refresh
     * Body: { refreshToken }
     *
     * 旧 refresh token 一次性使用：成功后立刻吊销旧 session 并签发新 session，避免被重放。
     */
    public function refresh() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        Validator::make($data, [
            'refreshToken' => 'required'
        ]);

        $oldSession = $this->sessionModel->findByRefreshToken((string)$data['refreshToken']);
        if (!$oldSession) {
            Response::unauthorized('Invalid or expired refresh token');
        }

        $user = $this->userModel->findById((int)$oldSession['userId']);
        if (!$user) {
            $this->sessionModel->revoke((int)$oldSession['id']);
            Response::unauthorized('User not found');
        }

        $status = strtolower((string)($user['status'] ?? ''));
        if (in_array($status, ['inactive', 'suspended', 'closed'], true)) {
            $this->sessionModel->revoke((int)$oldSession['id']);
            Response::forbidden($status === 'suspended' ? '账户已被暂停' : '账户已注销或停用');
        }

        // 轮转：吊销旧 session，签发新 session（沿用设备信息）
        $this->sessionModel->revoke((int)$oldSession['id']);

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $newSession = $this->sessionModel->createSession(
            (int)$user['id'],
            [
                'deviceId' => $oldSession['deviceId'] ?? null,
                'deviceModel' => $oldSession['deviceModel'] ?? null,
                'os' => $oldSession['os'] ?? null,
                'appVersion' => $oldSession['appVersion'] ?? null,
                'ipAddress' => $ipAddress,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ],
            self::ACCESS_TTL_SECONDS,
            self::REFRESH_TTL_SECONDS
        );

        $accessToken = JWT::encode([
            'userId' => (int)$user['id'],
            'email' => $user['email'],
            'sessionId' => $newSession['id'],
            'deviceId' => $oldSession['deviceId'] ?? null,
            'type' => 'app',
        ]);

        Response::success([
            'accessToken' => $accessToken,
            'refreshToken' => $newSession['refreshToken'],
            'expiresIn' => self::ACCESS_TTL_SECONDS,
            'refreshExpiresIn' => self::REFRESH_TTL_SECONDS,
            'tokenType' => 'Bearer',
            'tradingPlatformToken' => $this->issueTradingPlatformToken($user),
        ], 'Token refreshed');
    }

    /**
     * App 登出 —— 仅吊销当前设备的 session
     * POST /api/external/logout
     * Header: Authorization: Bearer <accessToken>
     *
     * 幂等：token 无效或会话已吊销时也返回成功，避免前端重试风暴。
     */
    public function logout() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::success(null, 'Logout successful');
            return;
        }

        try {
            $payload = JWT::decode($token);
        } catch (Exception $e) {
            Response::success(null, 'Logout successful');
            return;
        }

        if (($payload['type'] ?? '') !== 'app') {
            Response::success(null, 'Logout successful');
            return;
        }

        $sessionId = isset($payload['sessionId']) ? (int)$payload['sessionId'] : 0;
        if ($sessionId > 0) {
            $this->sessionModel->revoke($sessionId);
        }

        if (!empty($payload['userId'])) {
            try {
                $this->activityLogModel->logLogout((int)$payload['userId']);
            } catch (Throwable $e) {
                // ignore log failure
            }
        }

        Response::success(null, 'Logout successful');
    }

    /**
     * App 自助注销账户
     * POST /api/external/close-account   （受 AppAuthMiddleware 保护）
     *
     * 置 status=closed + deletedAt（复用 AccountClosureService），并吊销该用户所有设备会话。
     */
    public function closeAccount() {
        require_once __DIR__ . '/../services/AccountClosureService.php';

        $payload = JWT::getPayload();
        $userId = isset($payload['userId']) ? (int)$payload['userId'] : 0;
        if ($userId <= 0) {
            Response::unauthorized('Invalid token payload');
            return;
        }

        (new AccountClosureService())->closeAccount($userId);

        // 注销后吊销该用户全部 App 会话，所有设备需重新登录
        $this->sessionModel->revokeAllForUser($userId);

        Response::success(null, '账户已注销');
    }

    /**
     * 申请一个 App→WebView 的一次性 handoff code
     * POST /api/external/web-handoff      （受 AppAuthMiddleware 保护）
     * Body: { redirect: "/client/funding/deposit?..." }
     *
     * 返回的 url 直接喂给原生 WebView 加载即可。
     * redirect 必须命中 config['app_web_handoff']['allowed_redirect_prefixes']，
     * 否则被静默替换为 fallback（不报错，避免泄露白名单）。
     */
    public function createWebHandoff() {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $payload = AppAuthMiddleware::getCurrentUser();
        $session = AppAuthMiddleware::getCurrentSession();
        if (!$payload || empty($payload['userId'])) {
            Response::unauthorized();
        }

        $appConfig = require __DIR__ . '/../config/app.php';
        $cfg = $appConfig['app_web_handoff'] ?? [];
        $ttl = (int)($cfg['ttl_seconds'] ?? 60);
        $fallback = (string)($cfg['fallback_redirect'] ?? '/client');
        $allowed = $cfg['allowed_redirect_prefixes'] ?? [];

        $redirect = isset($data['redirect']) ? trim((string)$data['redirect']) : '';
        $redirect = self::sanitizeRedirect($redirect, $allowed, $fallback);

        $handoffModel = new AppWebHandoff();
        $issued = $handoffModel->issue(
            (int)$payload['userId'],
            isset($session['id']) ? (int)$session['id'] : null,
            $redirect,
            $ttl,
            [
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );

        $clientFrontendUrl = rtrim($appConfig['client_frontend_url'] ?? '', '/');
        $url = $clientFrontendUrl . '/#/auth/handoff?code=' . urlencode($issued['code']);

        Response::success([
            'code' => $issued['code'],
            'url' => $url,
            'redirect' => $redirect,
            'expiresIn' => $issued['expiresIn'],
        ], 'Handoff issued');
    }

    /**
     * 把外部传入的 redirect 收敛到允许范围：
     * 1. 必须以 '/' 开头（相对路径）
     * 2. 不能包含 '//' 或协议（防止 //evil.com、http://...）
     * 3. 必须命中白名单前缀
     * 任意一条不满足 → 返回 fallback。
     */
    private static function sanitizeRedirect($redirect, array $allowedPrefixes, $fallback) {
        if ($redirect === '' || $redirect[0] !== '/') {
            return $fallback;
        }
        if (strpos($redirect, '//') !== false) {
            return $fallback;
        }
        if (preg_match('#^/[a-z][a-z0-9+\-.]*:#i', $redirect)) {
            // 类似 "/javascript:" 这种诡异输入
            return $fallback;
        }
        // 截掉 query/hash 再比对前缀，避免 ?redirect=/client/foo&xxx=/client/funding/deposit 这种绕过
        $pathOnly = $redirect;
        $qPos = strpos($pathOnly, '?');
        if ($qPos !== false) {
            $pathOnly = substr($pathOnly, 0, $qPos);
        }
        $hPos = strpos($pathOnly, '#');
        if ($hPos !== false) {
            $pathOnly = substr($pathOnly, 0, $hPos);
        }

        foreach ($allowedPrefixes as $prefix) {
            if (strpos($pathOnly, $prefix) === 0) {
                return $redirect;
            }
        }
        return $fallback;
    }
}
