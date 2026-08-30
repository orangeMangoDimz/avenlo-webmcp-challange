<?php
/**
 * 认证控制器
 * 处理登录、登出、密码重置等功能
 */

require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminLoginLog.php';
require_once __DIR__ . '/../models/AdminSession.php';
require_once __DIR__ . '/../models/AdminPasswordReset.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../models/FinanceProToken.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';

class AuthController {
    private $userModel;
    private $loginLogModel;
    private $sessionModel;
    private $passwordResetModel;

    public function __construct() {
        $this->userModel = new AdminUser();
        $this->loginLogModel = new AdminLoginLog();
        $this->sessionModel = new AdminSession();
        $this->passwordResetModel = new AdminPasswordReset();
    }

    /**
     * 登录
     * POST /api/auth/login
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证输入
        Validator::make($data, [
            'username' => 'required',
            'password' => 'required|min:6'
        ]);

        $username = $data['username'];
        $password = $data['password'];
        $rememberMe = isset($data['rememberMe']) ? (int)$data['rememberMe'] : 0;

        // 查找用户
        $user = $this->userModel->findByCredentials($username);

        if (!$user) {
            // 记录失败日志
            $this->loginLogModel->logLogin([
                'username' => $username,
                'loginStatus' => 'failed',
                'failureReason' => 'User not found'
            ]);

            Response::error('Invalid credentials', 401);
        }

        // 检查账户状态
        if ($user['status'] !== 'active') {
            Response::error('Account is inactive', 403);
        }

        // 检查账户是否锁定（后台手动锁定）
        if ($user['isLocked']) {
            $this->loginLogModel->logLogin([
                'userId' => $user['id'],
                'username' => $username,
                'loginStatus' => 'blocked',
                'failureReason' => 'Account locked'
            ]);

            Response::error('Account is locked due to multiple failed login attempts', 403);
        }

        // 每日限制：检查当日失败次数是否已达上限（不累积，每日重置）
        $config = require __DIR__ . '/../config/app.php';
        $maxAttempts = $config['security']['max_login_attempts'];
        $todayFailedCount = $this->loginLogModel->getTodayFailedCount($user['id']);
        if ($todayFailedCount >= $maxAttempts) {
            $this->loginLogModel->logLogin([
                'userId' => $user['id'],
                'username' => $username,
                'loginStatus' => 'blocked',
                'failureReason' => 'Daily login attempt limit exceeded'
            ]);
            Response::error('Daily login attempt limit reached. Please try again tomorrow.', 403);
        }

        // 验证密码
        $verificationResult = $this->userModel->verifyPassword($password, $user['passwordHash']);

        if (!$verificationResult) {
            // 仅记录失败日志（每日限制由上面按当日失败次数判断，不再累积 failedLoginAttempts）

            // 记录失败日志
            $this->loginLogModel->logLogin([
                'userId' => $user['id'],
                'username' => $username,
                'loginStatus' => 'failed',
                'failureReason' => 'Invalid password'
            ]);

            Response::error('Incorrect password', 401);
        }

        // 登录成功
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 更新最后登录信息
        $this->userModel->updateLastLogin($user['id'], $ipAddress);

        // 创建会话
        $sessionId = $this->sessionModel->createSession($user['id'], [
            'rememberMe' => $rememberMe
        ]);

        // 记录成功日志
        $this->loginLogModel->logLogin([
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'loginStatus' => 'success',
            'rememberMe' => $rememberMe,
            'sessionId' => $sessionId
        ]);

        // 生成JWT令牌
        $token = JWT::encode([
            'userId' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'roleId' => $user['roleId'],
            'sessionId' => $sessionId,
            'type' => 'admin'  // 添加type字段标识为管理员
        ]);

        // 获取完整用户信息
        $userInfo = $this->userModel->getUserFullInfo($user['id']);

        // 获取用户权限
        $permissionModel = new AdminPermission();
        $permissions = $permissionModel->getUserPermissions($user['id']);

        // 获取并保存FinancePro Token
        try {
            $financeProClient = new FinanceProApiClient();
            $tokenData = $financeProClient->getToken();

            $tokenModel = new FinanceProToken();
            $tokenModel->saveToken($tokenData);

        } catch (Exception $e) {
            // Token获取失败不影响登录流程，只记录日志
//            Logger::error('Failed to get FinancePro Token on admin login', [
//                'userId' => $user['id'],
//                'exception' => $e->getMessage()
//            ]);
        }

        Response::success([
            'token' => $token,
            'user' => $userInfo,
            'permissions' => $permissions
        ], 'Login successful');
    }

    /**
     * 登出
     * POST /api/auth/logout
     * 注意：logout应该是幂等的，即使没有token也应该返回成功，只清理本地状态即可
     */
    public function logout() {
        $token = JWT::getTokenFromHeader();

        // 如果没有token，直接返回成功（幂等性）
        if (!$token) {
            Response::success(null, 'Logout successful');
        }

        try {
            $payload = JWT::decode($token);

            // 删除会话
            if (isset($payload['sessionId'])) {
                $this->sessionModel->delete($payload['sessionId']);
            }

            Response::success(null, 'Logout successful');
        } catch (Exception $e) {
            // Token无效时也返回成功（幂等性），避免前端循环调用
            Response::success(null, 'Logout successful');
        }
    }

    /**
     * 获取当前用户信息
     * GET /api/auth/me
     */
    public function me() {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            Response::unauthorized();
        }

        try {
            $payload = JWT::decode($token);
            $userId = $payload['userId'];

            $userInfo = $this->userModel->getUserFullInfo($userId);

            if (!$userInfo) {
                Response::notFound('User not found');
            }

            // 获取用户权限
            $permissionModel = new AdminPermission();
            $permissions = $permissionModel->getUserPermissions($userId);

            Response::success([
                'user' => $userInfo,
                'permissions' => $permissions
            ]);
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token');
        }
    }

    /**
     * 修改密码
     * POST /api/auth/change-password
     */
    public function changePassword() {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            Response::unauthorized();
        }

        try {
            $payload = JWT::decode($token);
            $userId = $payload['userId'];

            $data = json_decode(file_get_contents('php://input'), true);

            // 验证输入
            Validator::make($data, [
                'currentPassword' => 'required',
                'newPassword' => 'required|min:8',
                'confirmPassword' => 'required'
            ]);

            if ($data['newPassword'] !== $data['confirmPassword']) {
                Response::error('New password and confirm password do not match', 400);
            }

            // 获取用户（需要包含 passwordHash，所以直接查询数据库，不使用 findById 避免隐藏字段）
            $db = Database::getInstance();
            $sql = "SELECT * FROM adminUsers WHERE id = :id AND deletedAt IS NULL LIMIT 1";
            $user = $db->fetchOne($sql, ['id' => $userId]);

            if (!$user) {
                Response::notFound('User not found');
            }

            // 验证当前密码
            if (!$this->userModel->verifyPassword($data['currentPassword'], $user['passwordHash'])) {
                Response::error('Current password is incorrect', 400);
            }

            // 更新密码
            $newPasswordHash = $this->userModel->hashPassword($data['newPassword']);
            $this->userModel->update($userId, [
                'passwordHash' => $newPasswordHash,
                'passwordChangedAt' => date('Y-m-d H:i:s'),
                'mustChangePassword' => 0
            ]);

            Response::success(null, 'Password changed successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 请求密码重置
     * POST /api/auth/forgot-password
     */
    public function forgotPassword() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'email' => 'required|email'
        ]);

        $email = $data['email'];
        $user = $this->userModel->findByEmail($email);

        // 验证邮件是否已注册
        if (!$user) {
            Response::error('This email is not registered', 404);
        }

        // 创建重置令牌
        $token = $this->passwordResetModel->createToken($user['id'], $email);

        // 发送密码重置邮件
        require_once __DIR__ . '/../utils/EmailSender.php';
        $emailSender = new EmailSender();

        // 构建重置链接
        $appConfig = require __DIR__ . '/../config/app.php';
        $adminFrontendUrl = $appConfig['admin_frontend_url'] ?? 'http://localhost:9501';

        // 根据前端传递的 useHashMode 参数决定链接格式
        // 如果使用 Hash 模式（Vue Router Hash 模式），需要在链接中添加 #
        $useHashMode = isset($data['useHashMode']) && $data['useHashMode'] === true;
        $hashPrefix = $useHashMode ? '#' : '';
        $resetLink = "{$adminFrontendUrl}{$hashPrefix}/reset-password?token={$token}";

        // 获取用户名称
        $userName = $user['fullName'] ?? $user['username'] ?? $user['email'];

        // 发送邮件
        $emailSent = $emailSender->sendPasswordResetEmail(
            $user['email'],
            $userName,
            $resetLink,
            1  // 1小时有效期
        );

        if (!$emailSent) {
            error_log("Failed to send password reset email to: " . $user['email']);
        }

        Response::success([
            'message' => 'Password reset link has been sent to your email',
            'emailSent' => $emailSent
        ]);
    }

    /**
     * 重置密码
     * POST /api/auth/reset-password
     */
    public function resetPassword() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'token' => 'required',
            'password' => 'required|min:8',
            'confirmPassword' => 'required'
        ]);

        if ($data['password'] !== $data['confirmPassword']) {
            Response::error('Passwords do not match', 400);
        }

        // 验证令牌
        $resetRecord = $this->passwordResetModel->verifyToken($data['token']);

        if (!$resetRecord) {
            Response::error('Invalid or expired reset token', 400);
        }

        // 更新密码
        $newPasswordHash = $this->userModel->hashPassword($data['password']);
        $this->userModel->update($resetRecord['userId'], [
            'passwordHash' => $newPasswordHash,
            'passwordChangedAt' => date('Y-m-d H:i:s')
        ]);

        // 标记令牌为已使用
        $this->passwordResetModel->markAsUsed($resetRecord['id']);

        // 删除用户所有会话（强制重新登录）
        $this->sessionModel->deleteUserSessions($resetRecord['userId']);

        Response::success(null, 'Password reset successfully');
    }

    /**
     * 刷新令牌
     * POST /api/auth/refresh
     */
    public function refresh() {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            Response::unauthorized();
        }

        try {
            $payload = JWT::decode($token);

            // 生成新令牌
            $newToken = JWT::encode([
                'userId' => $payload['userId'],
                'username' => $payload['username'],
                'email' => $payload['email'],
                'roleId' => $payload['roleId'],
                'sessionId' => $payload['sessionId'],
                'type' => 'admin'  // 添加type字段标识为管理员
            ]);

            Response::success(['token' => $newToken], 'Token refreshed');
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token');
        }
    }

}
