<?php
/**
 * 客户端认证控制器
 * 处理客户登录、注册、密码重置等功能
 */

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/ClientPasswordReset.php';
require_once __DIR__ . '/../models/ClientSession.php';
require_once __DIR__ . '/../models/ClientEmailVerification.php';
require_once __DIR__ . '/../models/ClientActivityLog.php';
require_once __DIR__ . '/../models/PasswordStrengthSettings.php';
require_once __DIR__ . '/../models/EmailVerificationSettings.php';
require_once __DIR__ . '/../models/RegistrationFormField.php';
require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/FinanceProToken.php';
require_once __DIR__ . '/../models/IbInvitation.php';
require_once __DIR__ . '/../models/IbApplication.php';
require_once __DIR__ . '/../models/IbActivityLog.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerStatusHistory.php';
require_once __DIR__ . '/../models/IbTierLevel.php';
require_once __DIR__ . '/../models/SalesReferralSettings.php';
require_once __DIR__ . '/../models/IbReferralSettings.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/ClientIp.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Logger.php';

class ClientAuthController {
    private $userModel;
    private $passwordResetModel;
    private $sessionModel;
    private $emailVerificationModel;
    private $activityLogModel;
    private $passwordStrengthModel;

    public function __construct() {
        $this->userModel = new ClientUser();
        $this->passwordResetModel = new ClientPasswordReset();
        $this->sessionModel = new ClientSession();
        $this->emailVerificationModel = new ClientEmailVerification();
        $this->activityLogModel = new ClientActivityLog();
        $this->passwordStrengthModel = new PasswordStrengthSettings();
    }

    /**
     * 为客户写入销售归属：
     * 1. sales_bind 供 Sales 列表/筛选使用
     * 2. clientUsers.accountManagerId 供负责人字段/通知链路使用
     */
    private function assignSalesToClient($clientId, $salesId) {
        $clientId = (int)$clientId;
        $salesId = (int)$salesId;
        if ($clientId <= 0 || $salesId <= 0) {
            return;
        }

        $db = \Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->query(
            'INSERT IGNORE INTO sales_bind (salesId, clientId, createdAt) VALUES (:salesId, :clientId, :createdAt)',
            ['salesId' => $salesId, 'clientId' => $clientId, 'createdAt' => $now]
        );

        $db->query(
            'UPDATE clientUsers
             SET accountManagerId = :salesId,
                 accountManagerAssignedAt = CASE
                     WHEN accountManagerAssignedAt IS NULL OR accountManagerAssignedAt = \'0000-00-00 00:00:00\' OR accountManagerId != :salesId2
                     THEN :assignedAt
                     ELSE accountManagerAssignedAt
                 END
             WHERE id = :clientId',
            [
                'salesId' => $salesId,
                'salesId2' => $salesId,
                'assignedAt' => $now,
                'clientId' => $clientId,
            ]
        );
    }

    /**
     * 通过 IB 反查其所属 Sales。
     * 优先使用 sales_bind（这是 Sales Dashboard 的主口径），
     * 无绑定时回退到 clientUsers.accountManagerId。
     */
    private function resolveSalesIdByIbPartnerId($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) {
            return 0;
        }

        $db = \Database::getInstance();
        $row = $db->fetchOne(
            "SELECT
                COALESCE(
                    (
                        SELECT sb.salesId
                        FROM sales_bind sb
                        WHERE sb.clientId = ib.userId
                        ORDER BY sb.createdAt ASC, sb.id ASC
                        LIMIT 1
                    ),
                    cu.accountManagerId
                ) AS salesId
             FROM ibPartners ib
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             WHERE ib.id = :ibPartnerId
             LIMIT 1",
            ['ibPartnerId' => $ibPartnerId]
        );

        return (int)($row['salesId'] ?? 0);
    }

    /**
     * 为新注册用户添加默认Lead标签
     * @param int $userId 用户ID
     */
    private function addDefaultLeadTag($userId) {
        try {
            require_once __DIR__ . '/../models/LeadTag.php';
            require_once __DIR__ . '/../models/LeadTagAssignment.php';

            $tagModel = new LeadTag();
            $assignmentModel = new LeadTagAssignment();

            // 查找"New Lead"标签
            $defaultTag = $tagModel->findByName('New Lead');

            // 如果不存在，使用"Hot Lead"标签（系统默认标签）
            if (!$defaultTag) {
                $defaultTag = $tagModel->findByName('Hot Lead');
            }

            // 如果有默认标签，分配给用户
            // assignedBy设为null表示系统自动分配
            if ($defaultTag) {
                $assignmentModel->assignTag($userId, $defaultTag['id'], null);
            }
        } catch (Exception $e) {
            // 静默失败 - 标签分配失败不应该影响注册流程
//            Logger::error("Failed to assign default lead tag: " . $e->getMessage());
        }
    }

    /**
     * 为新注册用户创建法律文档签署记录
     * @param int $userId 用户ID
     * @param array $userData 用户数据（姓名、邮箱等）
     */
    private function createLegalDocumentSignatures($userId, $userData) {
        try {
            require_once __DIR__ . '/../models/LegalDocument.php';
            require_once __DIR__ . '/../models/LegalDocumentSignature.php';

            $legalDocModel = new LegalDocument();
            $signatureModel = new LegalDocumentSignature();

            // 需要自动签署的文档类型
            $requiredDocTypes = ['terms_of_service', 'privacy_policy', 'risk_disclosure'];

            // 准备签名信息
            $fullName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
            $email = $userData['email'] ?? '';
            $signatureDate = date('Y-m-d H:i:s');

            foreach ($requiredDocTypes as $docType) {
                // 查找活跃的文档
                $document = $legalDocModel->findOne([
                    'documentType' => $docType,
                    'isActive' => 1
                ]);

                if ($document) {
                    // 创建带签名信息的文档内容
                    $signedContent = $document['content'];

                    // 在文档底部添加签名信息
                    $signedContent .= "
                    <div style='margin-top: 40px; padding-top: 20px; border-top: 2px solid #e2e8f0;'>
                        <h3 style='color: #2d3748; font-size: 18px; margin-bottom: 15px;'>Document Signature</h3>
                        <p style='margin: 8px 0;'><strong>Signed by:</strong> {$fullName}</p>
                        <p style='margin: 8px 0;'><strong>Email:</strong> {$email}</p>
                        <p style='margin: 8px 0;'><strong>User ID:</strong> {$userId}</p>
                        <p style='margin: 8px 0;'><strong>Date & Time:</strong> {$signatureDate}</p>
                        <p style='margin-top: 15px; color: #718096; font-size: 13px;'>
                            By checking the agreement box during registration, you have electronically signed and agreed to this document.
                        </p>
                    </div>";

                    // 记录签署（不保存IP和UserAgent）
                    $signatureModel->recordSignature(
                        $userId,
                        $document['id'],
                        $docType,
                        $document['version'],
                        null, // 不保存IP
                        null  // 不保存UserAgent
                    );
                }
            }
        } catch (Exception $e) {
            // 静默失败 - 文档签署记录失败不应该影响注册流程
//            Logger::error("Failed to create legal document signatures: " . $e->getMessage());
        }
    }

    /**
     * 客户注册
     * POST /api/client/auth/register
     */
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 基本验证
        // Email 必填和格式验证
        if (empty($data['email'])) {
            Response::error('Email is required', 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format', 400);
        }

        // Password 必填和最小长度验证
        if (empty($data['password'])) {
            Response::error('Password is required', 400);
        }

        if (strlen($data['password']) < 6) {
            Response::error('Password must be at least 6 characters long', 400);
        }

        // 验证密码匹配
        if ($data['password'] !== $data['confirmPassword']) {
            Response::error('Passwords do not match', 400);
        }

        // 验证密码强度
        $passwordValidation = $this->passwordStrengthModel->validatePassword($data['password']);
        if (!$passwordValidation['valid']) {
            Response::error('Password does not meet requirements', 400, [
                'errors' => $passwordValidation['errors']
            ]);
        }

        // 姓名只允许字母、数字、空格、连字符、撇号；留空不校验（保持原可选行为）
        $namePattern = "/^[A-Za-z0-9 '-]+$/";
        foreach (['firstName' => 'First name', 'lastName' => 'Last name'] as $nameField => $nameLabel) {
            $nameValue = (string)($data[$nameField] ?? '');
            if ($nameValue !== '' && !preg_match($namePattern, $nameValue)) {
                Response::error($nameLabel . ' can only contain letters, numbers, spaces, hyphens and apostrophes', 400);
            }
        }

        // 检查邮箱是否已存在（不需要密码字段）
        if ($this->userModel->findByEmail($data['email'], false)) {
            Response::error('Email already registered', 400);
        }

        // 开关开启且填写了手机号时，校验手机号是否已被其他客户使用
        $appConfig = require __DIR__ . '/../config/app.php';
        if (!empty($appConfig['security']['check_duplicate_phone'])
            && !empty($data['phone'])
            && $this->userModel->findByPhone($data['phone'])) {
            Response::error('Phone number already registered', 400);
        }

        // 创建用户
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $userId = $this->userModel->create([
            'email' => $data['email'],
            'passwordHash' => $this->userModel->hashPassword($data['password']),
            'firstName' => $data['firstName'] ?? null,
            'lastName' => $data['lastName'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'status' => 'active',
            'registrationIp' => $ipAddress
        ]);

        // 自动添加默认Lead标签
        $this->addDefaultLeadTag($userId);

        // 如果用户同意了条款，自动创建法律文档签署记录
        if (isset($data['agreeTerms']) && $data['agreeTerms']) {
            $this->createLegalDocumentSignatures($userId, [
                'firstName' => $data['firstName'] ?? '',
                'lastName' => $data['lastName'] ?? '',
                'email' => $data['email']
            ]);
        }

        // 记录注册活动
        $this->activityLogModel->logRegistration($userId);

        $resolvedSalesId = 0;
        $resolvedIbPartnerId = 0;

        // Sales 推荐链接：若带 ref 则绑定 Sales 并增加该 Sales 的注册数
        $ref = isset($data['ref']) ? trim((string)$data['ref']) : '';
        if ($ref !== '') {
            $settingsModel = new \SalesReferralSettings();
            $salesId = $settingsModel->getUserIdBySuffix($ref);
            if ($salesId > 0) {
                $settingsModel->ensureSettingsRow($salesId, $ref);
                $this->assignSalesToClient($userId, $salesId);
                $settingsModel->incrementRegistrationsCount($salesId);
                $resolvedSalesId = $salesId;
            }
        }

        // IB 推荐链接：若带 ibRef 则绑定 IB；若当前还没有 Sales，则继承该 IB 自己所属的 Sales
        $ibRef = isset($data['ibRef']) ? trim((string)$data['ibRef']) : '';
        if ($ibRef !== '') {
            $ibReferralSettings = new \IbReferralSettings();
            $ibPartnerId = $ibReferralSettings->getIbPartnerIdBySuffix($ibRef);
            if ($ibPartnerId > 0) {
                $resolvedIbPartnerId = $ibPartnerId;
                $ibReferralSettings->ensureSettingsRow($ibPartnerId, $ibRef);
                $ibPartnerBindModel = new \IbPartnerBind();
                $ibPartnerBindModel->removeClientBindsByClientIds([$userId]);
                $ibPartnerBindModel->addClientBinds($ibPartnerId, [$userId]);
                $ibReferralSettings->incrementRegistrationsCount($ibPartnerId);

                if ($resolvedSalesId <= 0) {
                    $inheritedSalesId = $this->resolveSalesIdByIbPartnerId($ibPartnerId);
                    if ($inheritedSalesId > 0) {
                        $this->assignSalesToClient($userId, $inheritedSalesId);
                        $resolvedSalesId = $inheritedSalesId;
                    }
                }
            }
        }

        // 检查是否需要邮箱验证
        $emailVerificationSettings = new EmailVerificationSettings();
        $settings = $emailVerificationSettings->getSettings();

        if ($settings['isRequired']) {
            // 创建验证令牌
            $token = $this->emailVerificationModel->createToken($userId, $data['email']);

            // 发送验证邮件
            require_once __DIR__ . '/../utils/EmailSender.php';
            $emailSender = new EmailSender();

            // 构建验证链接（前端使用 hash 路由，链接格式为 域名/#/client/verify-email?token=xxx）
            $appConfig = require __DIR__ . '/../config/app.php';
            $clientFrontendUrl = rtrim($appConfig['client_frontend_url'] ?? 'http://localhost:5173', '/');
            $verificationLink = "{$clientFrontendUrl}/#/client/verify-email?token={$token}";

            // 获取用户名称
            $userName = trim(($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? ''));
            if (empty($userName)) {
                $userName = $data['email'];
            }

            // 发送邮件
            $emailSent = $emailSender->sendVerificationEmail(
                $data['email'],
                $userName,
                $verificationLink,
                $settings['verificationLinkExpiryHours']
            );

            if (!$emailSent) {
                // 邮件发送失败，记录错误但不影响注册流程
//                Logger::error("Failed to send verification email to: " . $data['email']);
            }

            Response::success([
                'userId' => $userId,
                'message' => 'Registration successful. Please check your email to verify your account.',
                'emailSent' => $emailSent
            ], 'Registration successful', 201);
        } else {
            // 如果不需要验证，直接激活用户
            $this->userModel->verifyEmail($userId);

            Response::success([
                'userId' => $userId,
                'message' => 'Registration successful'
            ], 'Registration successful', 201);
        }
    }

    /**
     * 客户登录
     * POST /api/client/auth/login
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证输入
        Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $email = $data['email'];
        $password = $data['password'];
        $rememberMe = $data['rememberMe'] ?? false;

        // 查找用户（包含密码哈希用于验证）
        $user = $this->userModel->findByEmail($email, true);

        if (!$user) {
            $this->activityLogModel->logActivity([
                'activityType' => 'login_failed',
                'description' => 'Login attempt with non-existent email: ' . $email
            ]);

            Response::error('No account found for this email address.', 401);
        }

        // 检查账户状态
        if ($user['status'] === 'suspended') {
            Response::error('Account is suspended', 403);
        }

        if ($user['status'] === 'inactive' || $user['status'] === 'closed') {
            Response::error('Account is inactive', 403);
        }

        // 验证密码
        if (!$this->userModel->verifyPassword($password, $user['passwordHash'])) {
            $this->activityLogModel->logActivity([
                'userId' => $user['id'],
                'activityType' => 'login_failed',
                'description' => 'Invalid password attempt'
            ]);

            Response::error('Please enter a valid password.', 401);
        }

        // 检查邮箱是否已验证，未验证则不允许登录
        if (empty($user['emailVerified'])) {
            Response::error('This email address has been registered, please check your email inbox to complete your registration.', 403);
        }

        // 登录成功
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 更新最后登录信息
        $this->userModel->updateLastLogin($user['id'], $ipAddress);

        // 创建会话
        $sessionId = $this->sessionModel->createSession($user['id'], [
            'rememberMe' => $rememberMe
        ]);

        // 记录登录日志
        $this->activityLogModel->logLogin($user['id'], true);

        // 生成JWT令牌
        $token = JWT::encode([
            'userId' => $user['id'],
            'email' => $user['email'],
            'sessionId' => $sessionId,
            'type' => 'client'
        ]);

        // 获取用户信息
        $userInfo = $this->userModel->findById($user['id']);

        // 获取并保存FinancePro Token
        try {
            $financeProClient = new FinanceProApiClient();
            $tokenData = $financeProClient->getToken();

            $tokenModel = new FinanceProToken();
            $tokenModel->saveToken($tokenData);

        } catch (Exception $e) {
            // Token获取失败不影响登录流程，只记录日志
//            Logger::error('Failed to get FinancePro Token on login', [
//                'userId' => $user['id'],
//                'exception' => $e->getMessage()
//            ]);
        }

        // 获取IB状态信息
        $ibStatus = $this->getIbStatusForUser($user['id'], $userInfo['email']);

        Response::success([
            'token' => $token,
            'user' => $userInfo,
            'ibStatus' => $ibStatus
        ], 'Login successful');
    }

    /**
     * 获取用户的IB状态信息（ibPartners + 邀请）
     * - 有 ibPartners 且 status === approved → 已成为了 IB，显示完整 IB 菜单；
     * - 有 ibPartners 且 status !== approved → 申请中，显示 IB Program 带锁；
     * - 无 ibPartners 但有有效邀请（sent/viewed/accepted）→ 通过邀请链接进入，也显示 IB Program 带锁。
     * @param int $userId 用户ID
     * @param string $email 用户邮箱（保留参数兼容，未使用）
     * @return array IB状态信息
     */
    private function getIbStatusForUser($userId, $email) {
        $ibPartnerModel = new IbPartner();
        $ibPartner = $ibPartnerModel->getByClientId($userId);

        $hasIbPartner = !empty($ibPartner);
        $isApproved = $hasIbPartner && isset($ibPartner['status']) && $ibPartner['status'] === IbPartner::STATUS_APPROVED;

        // 有 ibPartners 记录 → 显示 IB 菜单（申请中带锁 / 已通过完整菜单）
        // 或有有效 IB 邀请（通过邀请链接进入、尚未点「同意」）→ 也显示 IB Program 带锁
        $hasInvitation = false;
        if (!$hasIbPartner) {
            $ibInvitationModel = new IbInvitation();
            $row = $ibInvitationModel->queryOne(
                "SELECT id FROM ibInvitations
                 WHERE clientId = :clientId
                 AND invitationStatus IN ('sent', 'viewed', 'accepted')
                 LIMIT 1",
                ['clientId' => $userId]
            );
            $hasInvitation = !empty($row);
        }
        $showIbProgram = $hasIbPartner || $hasInvitation;

        $tierLevel = null;
        if ($isApproved && !empty($ibPartner['tierLevelId'])) {
            $tierLevelModel = new IbTierLevel();
            $tierLevelRow = $tierLevelModel->findById($ibPartner['tierLevelId']);
            if (!empty($tierLevelRow['tierLevel'])) {
                $tierLevel = (int) $tierLevelRow['tierLevel'];
            }
        }

        return [
            'hasAcceptedInvitation' => false,
            'hasApprovedApplication' => $isApproved,
            'isApproved' => $isApproved,
            'showIbProgram' => $showIbProgram,
            'tierLevel' => $tierLevel
        ];
    }

    /**
     * 客户登出
     * POST /api/client/auth/logout
     * 支持预览：X-Preview-Token 仅清理前端状态，不删服务端会话；JWT 时删除会话并记录登出
     */
    public function logout() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId !== null) {
            // 预览或 JWT 已解析出用户：若为 JWT 会有 sessionId，在 decode 分支处理
            $token = JWT::getTokenFromHeader();
            if ($token) {
                try {
                    $payload = JWT::decode($token);
                    if (isset($payload['userId'])) {
                        $this->activityLogModel->logLogout($payload['userId']);
                    }
                    if (isset($payload['sessionId'])) {
                        $this->sessionModel->delete($payload['sessionId']);
                    }
                } catch (Exception $e) {
                    // 忽略解码失败，仍返回成功
                }
            }
            Response::success(null, 'Logout successful');
            return;
        }

        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::success(null, 'Logout successful');
            return;
        }

        try {
            $payload = JWT::decode($token);

            if (isset($payload['userId'])) {
                $this->activityLogModel->logLogout($payload['userId']);
            }
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
     * 用 App 端发起的一次性 handoff code 兑换 client JWT
     * POST /api/client/auth/exchange-handoff
     * Body: { code }
     *
     * 给 WebView 用：App 调 /api/external/web-handoff 拿到 code，WebView 加载
     * /#/auth/handoff?code=... 后立刻调本接口，得到与正常登录同形的 token + user，
     * 写入 localStorage 后 router.replace(redirect) 即进入业务页。
     *
     * 安全约束：
     * - code 单次使用（appWebHandoffs.usedAt 原子置位）
     * - code 短 TTL（默认 60 秒）
     * - 兑换出的 client JWT 与 App accessToken 完全独立，互不影响
     */
    public function exchangeHandoff() {
        require_once __DIR__ . '/../models/AppWebHandoff.php';

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        Validator::make($data, [
            'code' => 'required'
        ]);

        $handoffModel = new AppWebHandoff();
        $handoff = $handoffModel->findUsable((string)$data['code']);
        if (!$handoff) {
            Response::error('Invalid or expired handoff code', 401);
        }

        // 原子标记为已用：抢占失败说明并发请求 / 已被消费
        $consumed = $handoffModel->consume((int)$handoff['id']);
        if ($consumed !== 1) {
            Response::error('Invalid or expired handoff code', 401);
        }

        $userId = (int)$handoff['userId'];
        $user = $this->userModel->findByIdWithPassword($userId);
        if (!$user) {
            Response::error('User not found', 404);
        }

        $status = strtolower((string)($user['status'] ?? ''));
        if ($status === 'suspended') {
            Response::error('Account is suspended', 403);
        }
        if ($status === 'inactive') {
            Response::error('Account is inactive', 403);
        }

        // 走与 login 相同的发 token 流程
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->userModel->updateLastLogin($userId, $ipAddress);

        $sessionId = $this->sessionModel->createSession($userId, [
            'rememberMe' => false  // WebView 一般不长期常驻，短 session 即可
        ]);

        $this->activityLogModel->logActivity([
            'userId' => $userId,
            'activityType' => 'web_handoff_login',
            'description' => 'Logged in via App→WebView handoff'
        ]);

        $token = JWT::encode([
            'userId' => (int)$user['id'],
            'email' => $user['email'],
            'sessionId' => $sessionId,
            'type' => 'client'
        ]);

        $userInfo = $this->userModel->findById($userId);

        Response::success([
            'token' => $token,
            'user' => $userInfo,
            'redirect' => $handoff['redirect'] ?? '/client',
        ], 'Handoff exchanged');
    }

    /**
     * 验证邮箱（验证成功后自动登录）
     * POST /api/client/auth/verify-email
     */
    public function verifyEmail() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'token' => 'required'
        ]);

        // 验证令牌
        $verification = $this->emailVerificationModel->verifyToken($data['token']);

        if (!$verification) {
            Response::error('Invalid or expired verification token', 400);
        }

        // 验证邮箱
        $this->userModel->verifyEmail($verification['userId']);

        // 标记为已验证
        $this->emailVerificationModel->markAsVerified($verification['id']);

        // 记录活动
        $this->activityLogModel->logActivity([
            'userId' => $verification['userId'],
            'activityType' => 'email_verified',
            'description' => 'Email verified successfully'
        ]);

        // 自动登录：创建会话和JWT Token
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 获取用户信息
        $user = $this->userModel->findById($verification['userId']);

        // 更新最后登录信息
        $this->userModel->updateLastLogin($verification['userId'], $ipAddress);

        // 创建会话
        $sessionId = $this->sessionModel->createSession($verification['userId'], [
            'rememberMe' => true  // 验证后默认记住用户
        ]);

        // 记录登录日志
        $this->activityLogModel->logLogin($verification['userId'], true);

        // 生成JWT令牌
        $token = JWT::encode([
            'userId' => $user['id'],
            'email' => $user['email'],
            'sessionId' => $sessionId,
            'type' => 'client'
        ]);

        // 返回token和用户信息，实现自动登录
        Response::success([
            'message' => 'Email verified successfully',
            'token' => $token,
            'user' => $user,
            'autoLogin' => true
        ], 'Email verified and logged in successfully');
    }

    /**
     * 重新发送验证邮件
     * POST /api/client/auth/resend-verification
     */
    public function resendVerification() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'email' => 'required|email'
        ]);

        $user = $this->userModel->findByEmail($data['email'], false);

        if (!$user) {
            // 为了安全，不暴露用户是否存在
            Response::success(null, 'If the email exists, a verification link will be sent');
        }

        // 检查是否已验证
        if ($user['emailVerified']) {
            Response::error('Email is already verified', 400);
        }

        // 检查是否可以重发
        if (!$this->emailVerificationModel->canResend($user['id'])) {
            Response::error('Please wait before requesting another verification email', 429);
        }

        // 创建新令牌
        $token = $this->emailVerificationModel->createToken($user['id'], $user['email']);

        // 发送验证邮件
        require_once __DIR__ . '/../utils/EmailSender.php';
        $emailSender = new EmailSender();

        // 构建验证链接（前端使用 hash 路由，链接格式为 域名/#/client/verify-email?token=xxx）
        $appConfig = require __DIR__ . '/../config/app.php';
        $clientFrontendUrl = rtrim($appConfig['client_frontend_url'] ?? 'http://localhost:5173', '/');
        $verificationLink = "{$clientFrontendUrl}/#/client/verify-email?token={$token}";

        // 获取用户名称
        $userName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if (empty($userName)) {
            $userName = $user['email'];
        }

        // 获取验证设置
        $emailVerificationSettings = new EmailVerificationSettings();
        $settings = $emailVerificationSettings->getSettings();

        // 发送邮件
        $emailSent = $emailSender->sendVerificationEmail(
            $user['email'],
            $userName,
            $verificationLink,
            $settings['verificationLinkExpiryHours']
        );

//        if (!$emailSent) {
//            Logger::error("Failed to send verification email to: " . $user['email']);
//        }

        Response::success([
            'message' => 'Verification email sent',
            'emailSent' => $emailSent
        ]);
    }

    /**
     * 请求密码重置
     * POST /api/client/auth/forgot-password
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
        $token = $this->passwordResetModel->createToken($email);

        // 记录活动
        $this->activityLogModel->logActivity([
            'userId' => $user['id'],
            'activityType' => 'password_reset_requested',
            'description' => 'Password reset requested'
        ]);

        // 发送密码重置邮件
        require_once __DIR__ . '/../utils/EmailSender.php';
        $emailSender = new EmailSender();

        // 构建重置链接
        $appConfig = require __DIR__ . '/../config/app.php';
        $clientFrontendUrl = $appConfig['client_frontend_url'] ?? 'http://localhost:5173';

        // 根据前端传递的 useHashMode 参数决定链接格式
        // 如果使用 Hash 模式（Vue Router Hash 模式），需要在链接中添加 #
        $useHashMode = isset($data['useHashMode']) && $data['useHashMode'] === true;
        $hashPrefix = $useHashMode ? '#' : '';
        $resetLink = "{$clientFrontendUrl}{$hashPrefix}/client/reset-password?token={$token}";

        // 获取用户名称
        $userName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        if (empty($userName)) {
            $userName = $user['email'];
        }

        // 发送邮件
        $emailSent = $emailSender->sendPasswordResetEmail(
            $user['email'],
            $userName,
            $resetLink,
            1  // 1小时有效期
        );

//        if (!$emailSent) {
//            Logger::error("Failed to send password reset email to: " . $user['email']);
//        }

        Response::success([
            'message' => 'Password reset link has been sent to your email',
            'emailSent' => $emailSent
        ]);
    }

    /**
     * 重置密码
     * POST /api/client/auth/reset-password
     */
    public function resetPassword() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'token' => 'required',
            'password' => 'required|min:6',
            'confirmPassword' => 'required'
        ]);

        if ($data['password'] !== $data['confirmPassword']) {
            Response::error('Passwords do not match', 400);
        }

        // 验证密码强度
        $passwordValidation = $this->passwordStrengthModel->validatePassword($data['password']);
        if (!$passwordValidation['valid']) {
            Response::error('Password does not meet requirements', 400, [
                'errors' => $passwordValidation['errors']
            ]);
        }

        // 验证令牌
        $resetRecord = $this->passwordResetModel->verifyToken($data['token']);

        if (!$resetRecord) {
            Response::error('Invalid or expired reset token', 400);
        }

        // 获取用户
        $user = $this->userModel->findByEmail($resetRecord['email']);

        if (!$user) {
            Response::error('User not found', 404);
        }

        // 更新密码
        $newPasswordHash = $this->userModel->hashPassword($data['password']);
        $this->userModel->update($user['id'], [
            'passwordHash' => $newPasswordHash
        ]);

        // 标记令牌为已使用
        $this->passwordResetModel->markAsUsed($resetRecord['id']);

        // 删除用户所有会话（强制重新登录）
        $this->sessionModel->deleteUserSessions($user['id']);

        // 记录活动
        $this->activityLogModel->logPasswordReset($user['id']);

        Response::success(null, 'Password reset successfully');
    }

    /**
     * 解析当前 client 用户 ID。
     * 优先 ClientAuthContext（兼容 X-Preview-Token 预览模式与 client/app JWT），
     * 兜底再单独解一次 Bearer JWT。任意分支拿不到都直接 401。
     */
    private function resolveCurrentClientUserIdOrFail() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId === null) {
            $token = JWT::getTokenFromHeader();
            if (!$token) {
                Response::unauthorized();
            }
            try {
                $payload = JWT::decode($token);
                $userId = isset($payload['userId']) ? (int) $payload['userId'] : null;
            } catch (Exception $e) {
                Response::unauthorized('Invalid or expired token');
            }
        }
        if ($userId === null) {
            Response::unauthorized();
        }
        return (int)$userId;
    }

    /**
     * 构建 KYC 状态摘要（me / meKycOnly 共用，避免业务规则被分别复制）。
     * 没有提交记录时只回退到 user 表上的 kycStatus；有提交时合并最新一条 submission 字段。
     */
    private function buildKycSummary($userId, array $userInfo) {
        $kycSummary = [
            'status' => $userInfo['kycStatus'] ?? 'not_started',
            'submissionStatus' => $userInfo['kycStatus'] ?? 'not_started',
            'isApproved' => ($userInfo['kycStatus'] ?? '') === 'approved',
            'hasSubmission' => false
        ];

        $kycSubmissionModel = new ClientKycSubmission();
        $latestSubmission = $kycSubmissionModel->getLatestSubmission($userId);
        if ($latestSubmission) {
            $kycSummary = array_merge($kycSummary, [
                'hasSubmission' => true,
                'submissionId' => (int)$latestSubmission['id'],
                'templateId' => $latestSubmission['templateId'],
                'submissionStatus' => $latestSubmission['submissionStatus'],
                'submittedAt' => $latestSubmission['submittedAt'],
                'reviewedAt' => $latestSubmission['reviewedAt'],
                'rejectionReason' => $latestSubmission['rejectionReason']
            ]);
        }
        return $kycSummary;
    }

    /**
     * 获取当前用户信息（含 kyc / ib 聚合）
     * GET /api/client/auth/me
     * 支持 X-Preview-Token 头（View as client 预览模式）与 JWT，统一用 ClientAuthContext 取当前客户 ID
     */
    public function me() {
        $userId = $this->resolveCurrentClientUserIdOrFail();

        $userInfo = $this->userModel->findById($userId);
        if (!$userInfo) {
            Response::notFound('User not found');
        }

        $ibStatus = $this->getIbStatusForUser($userId, $userInfo['email'] ?? '');
        Response::success([
            'user' => $userInfo,
            'kycStatus' => $this->buildKycSummary($userId, $userInfo),
            'ibStatus' => $ibStatus
        ]);
    }

    /**
     * 仅返回当前用户基础信息（App 端 GET /api/external/me 使用）
     * 把原 me 的聚合响应拆开，让 App 端按需各取所需，减少不必要的字段下发。
     */
    public function meUserOnly() {
        $userId = $this->resolveCurrentClientUserIdOrFail();

        $userInfo = $this->userModel->findById($userId);
        if (!$userInfo) {
            Response::notFound('User not found');
        }
        $userInfo['hasAccount'] = (new TradingAccount())->count(['userId' => (int)$userId]) > 0;

        Response::success(['user' => $userInfo]);
    }

    /**
     * 仅返回当前用户 KYC 状态摘要（App 端 GET /api/external/me/kyc 使用）
     */
    public function meKycOnly() {
        $userId = $this->resolveCurrentClientUserIdOrFail();

        $userInfo = $this->userModel->findById($userId);
        if (!$userInfo) {
            Response::notFound('User not found');
        }

        Response::success(['kycStatus' => $this->buildKycSummary($userId, $userInfo)]);
    }

    /**
     * 获取当前用户的IB申请状态
     * GET /api/client/auth/ib-status（支持预览 X-Preview-Token 与 JWT）
     */
    public function getIbStatus() {
        try {
            $userId = ClientAuthContext::getCurrentClientUserId();
            if ($userId === null) {
                Response::unauthorized('Authentication required');
            }

            // 获取用户信息
            $userModel = new ClientUser();
            $userInfo = $userModel->findById($userId);

            if (!$userInfo) {
                Response::notFound('User not found');
            }

            // 仅依据 ibPartners：有记录且 status !== approved 为申请中，只有 status === approved 才是已成为了 IB
            $ibPartnerModel = new IbPartner();
            $ibPartner = $ibPartnerModel->getByClientId($userId);

            if (!$ibPartner) {
                Response::success([
                    'hasApplication' => false,
                    'applicationStatus' => null,
                    'application' => null,
                    'timeline' => [],
                    'showIbProgram' => false,
                    'isApproved' => false
                ]);
            }

            $status = $ibPartner['status'] ?? '';
            $isApproved = ($status === IbPartner::STATUS_APPROVED);
            $statusDisplay = IbPartner::statusToDisplay($status);

            $partnerId = (int) ($ibPartner['id'] ?? 0);
            $statusTimeline = [];
            if ($partnerId > 0) {
                $partnerStatusHistoryModel = new IbPartnerStatusHistory();
                $statusHistory = $partnerStatusHistoryModel->getStatusHistory($partnerId);
                if (!empty($statusHistory)) {
                    $statusTimeline = $this->buildTimelineFromStatusHistory(
                        $statusHistory,
                        [
                            'applicationStatus' => $status,
                            'applicationDate' => $ibPartner['registrationDate'] ?? null
                        ]
                    );
                }
            }

            Response::success([
                'hasApplication' => true,
                'applicationStatus' => $status,
                'application' => [
                    'id' => $ibPartner['id'],
                    'applicantName' => $ibPartner['companyName'] ?? $ibPartner['contactPerson'] ?? '',
                    'applicantEmail' => $ibPartner['contactEmail'] ?? $userInfo['email'],
                    'applicationDate' => $ibPartner['registrationDate'] ?? null,
                    'applicationStatus' => $status,
                    'statusDisplay' => $statusDisplay
                ],
                'timeline' => $statusTimeline,
                'showIbProgram' => true,
                'isApproved' => $isApproved
            ]);

        } catch (Exception $e) {
            Response::error('Failed to get IB status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 从状态历史构建时间线
     */
    private function buildTimelineFromStatusHistory($statusHistory, $application) {
        $timeline = [];

        // 申请提交（如果没有在历史中，添加初始提交记录）
        $hasSubmitted = false;
        foreach ($statusHistory as $history) {
            if ($history['previousStatus'] === null || $history['previousStatus'] === '') {
                $hasSubmitted = true;
                break;
            }
        }

        if (!$hasSubmitted && $application['applicationDate']) {
            $timeline[] = [
                'id' => 'submitted',
                'title' => 'Application Submitted',
                'description' => 'Your IB application has been submitted successfully',
                'date' => $application['applicationDate'],
                'completed' => true,
                'current' => false
            ];
        }

        // 处理状态历史记录（优先用数据库存的 title、description）
        foreach ($statusHistory as $history) {
            $status = $history['newStatus'];
            $previousStatus = $history['previousStatus'];
            $changedBy = $history['changedByName'] ?? ($history['changedByUsername'] ?? 'System');
            $notes = $history['notes'] ?? '';
            $date = $history['createdAt'];

            if (isset($history['title']) && $history['title'] !== '' && isset($history['description'])) {
                $title = $history['title'];
                $description = (string) $history['description'];
            } else {
                if ($previousStatus === null || $previousStatus === '') {
                    $title = 'IB Registration Submitted';
                    $description = 'Your IB registration has been submitted and is pending review.';
                } else {
                    $title = $this->getStatusTitle($status);
                    $description = $this->getStatusDescription($status, $changedBy, $notes);
                }
            }

            // 判断是否是当前状态
            $isCurrent = ($status === $application['applicationStatus']);

            $timeline[] = [
                'id' => $status . '_' . $history['id'],
                'title' => $title,
                'description' => $description,
                'date' => $date,
                'completed' => !$isCurrent,
                'current' => $isCurrent,
                'status' => $status
            ];
        }

        // 按日期排序（最新的在前）
        usort($timeline, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // 反转顺序，使最早的在前
        $timeline = array_reverse($timeline);

        return $timeline;
    }

    /**
     * 获取状态标题
     */
    private function getStatusTitle($status) {
        $titles = [
            'pending' => 'Pending Review',
            'in_review' => 'Under Review',
            'approved' => 'Application Approved',
            'rejected' => 'Application Rejected',
            'more_info_requested' => 'More Information Requested',
            'pending_initial_review' => 'Initial Review',
            'pending_risk_review' => 'Risk Review',
            'pending_final_review' => 'Final Review'
        ];
        return $titles[$status] ?? 'Status Updated';
    }

    /**
     * 获取状态描述
     */
    private function getStatusDescription($status, $changedBy, $notes) {
        $descriptions = [
            'pending' => 'Your application is pending review',
            'in_review' => 'Your application is being reviewed by ' . $changedBy,
            'approved' => 'Your application has been approved by ' . $changedBy,
            'rejected' => 'Your application has been rejected by ' . $changedBy,
            'more_info_requested' => 'Additional information has been requested',
            'pending_initial_review' => 'Your application is in initial review' . ($changedBy ? '. Reverted by ' . $changedBy : ''),
            'pending_risk_review' => 'Initial review completed. Now in risk review' . ($changedBy ? ' by ' . $changedBy : ''),
            'pending_final_review' => 'Risk review completed. Now in final review' . ($changedBy ? ' by ' . $changedBy : '')
        ];

        $description = $descriptions[$status] ?? 'Status updated by ' . $changedBy;

        if ($notes) {
            $description .= '. ' . $notes;
        }

        return $description;
    }

    /**
     * 获取活动标题
     */
    private function getActivityTitle($activityType) {
        $titles = [
            'application_submitted' => 'Application Submitted',
            'application_reviewed' => 'Application Under Review',
            'application_approved' => 'Application Approved',
            'application_rejected' => 'Application Rejected',
            'more_info_requested' => 'More Information Requested',
            'reviewer_assigned' => 'Reviewer Assigned',
            'tier_assigned' => 'Tier Level Assigned'
        ];
        return $titles[$activityType] ?? 'Status Updated';
    }

    /**
     * 构建状态时间线（兼容旧数据）
     */
    private function getStatusTimeline($status, $application, $activities) {
        $timeline = [];

        // 申请提交
        if ($application['applicationDate']) {
            $timeline[] = [
                'id' => 'submitted',
                'title' => 'Application Submitted',
                'description' => 'Your IB application has been submitted successfully',
                'date' => $application['applicationDate'],
                'completed' => true,
                'current' => false
            ];
        }

        // 根据状态添加后续步骤
        switch ($status) {
            case 'pending':
                $timeline[] = [
                    'id' => 'pending',
                    'title' => 'Pending Review',
                    'description' => 'Your application is waiting to be reviewed by our team',
                    'date' => null,
                    'completed' => false,
                    'current' => true
                ];
                break;

            case 'in_review':
                if ($application['reviewStartDate']) {
                    $timeline[] = [
                        'id' => 'review_started',
                        'title' => 'Review Started',
                        'description' => 'Our team has started reviewing your application',
                        'date' => $application['reviewStartDate'],
                        'completed' => true,
                        'current' => false
                    ];
                }
                $timeline[] = [
                    'id' => 'in_review',
                    'title' => 'Under Review',
                    'description' => 'Our team is currently reviewing your application',
                    'date' => null,
                    'completed' => false,
                    'current' => true
                ];
                break;

            case 'more_info_requested':
                $timeline[] = [
                    'id' => 'more_info',
                    'title' => 'More Information Requested',
                    'description' => $application['additionalInfoRequest'] ?? 'Please provide additional information',
                    'date' => null,
                    'completed' => false,
                    'current' => true
                ];
                break;

            case 'approved':
                if ($application['reviewStartDate']) {
                    $timeline[] = [
                        'id' => 'review_started',
                        'title' => 'Review Started',
                        'description' => 'Our team started reviewing your application',
                        'date' => $application['reviewStartDate'],
                        'completed' => true,
                        'current' => false
                    ];
                }
                $timeline[] = [
                    'id' => 'approved',
                    'title' => 'Application Approved',
                    'description' => 'Congratulations! Your IB application has been approved',
                    'date' => $application['reviewCompletedDate'] ?? null,
                    'completed' => true,
                    'current' => false
                ];
                break;

            case 'rejected':
                if ($application['reviewStartDate']) {
                    $timeline[] = [
                        'id' => 'review_started',
                        'title' => 'Review Started',
                        'description' => 'Our team started reviewing your application',
                        'date' => $application['reviewStartDate'],
                        'completed' => true,
                        'current' => false
                    ];
                }
                $timeline[] = [
                    'id' => 'rejected',
                    'title' => 'Application Rejected',
                    'description' => $application['rejectionReason'] ?? 'Your application has been rejected',
                    'date' => $application['reviewCompletedDate'] ?? null,
                    'completed' => true,
                    'current' => false
                ];
                break;
        }

        return $timeline;
    }

    /**
     * 获取当前用户签署的法律文档
     * GET /api/client/auth/my-documents（支持预览：X-Preview-Token 或 JWT）
     */
    public function myDocuments() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId === null) {
            Response::unauthorized();
        }

        try {
            // 加载 LegalDocument 模型
            require_once __DIR__ . '/../models/LegalDocument.php';
            require_once __DIR__ . '/../models/LegalDocumentSignature.php';

            $signatureModel = new LegalDocumentSignature();
            $legalDocModel = new LegalDocument();

            // 获取用户签署的所有文档签名记录
            $signatures = $signatureModel->findAll(['leadId' => $userId]);

            $documents = [];
            foreach ($signatures as $signature) {
                $document = $legalDocModel->findById($signature['documentId']);

                if ($document) {
                    $documents[] = [
                        'id' => $signature['id'],
                        'documentId' => $signature['documentId'],
                        'documentType' => $signature['documentType'],
                        'title' => $document['title'],
                        'content' => $document['content'],
                        'version' => $signature['documentVersion'] ?? $document['version'],
                        'signedAt' => $signature['signedAt'],
                        'languageCode' => $document['languageCode'] ?? 'en'
                    ];
                }
            }

            // IB 确认的文档（与后台 Signed Documents 一致的数据源：ibPartnerDocumentAcknowledgements）
            $ibDocs = $this->userModel->query(
                "SELECT
                    pda.id,
                    pda.documentTemplateId,
                    pda.acknowledgedAt AS signedAt,
                    dt.documentTitle AS title,
                    dt.documentContent AS content,
                    COALESCE(dt.version, '1.0') AS version,
                    pda.ipAddress
                 FROM ibPartners ip
                 INNER JOIN ibPartnerDocumentAcknowledgements pda ON pda.ibPartnerId = ip.id AND pda.acknowledged = 1
                 INNER JOIN ibDocumentTemplates dt ON pda.documentTemplateId = dt.id
                 WHERE ip.userId = :clientId
                 ORDER BY pda.acknowledgedAt ASC",
                ['clientId' => $userId]
            );
            foreach ($ibDocs as $doc) {
                $documents[] = [
                    'id' => 'ib_' . $doc['id'],
                    'documentId' => $doc['documentTemplateId'],
                    'documentType' => 'ib_agreement',
                    'title' => $doc['title'],
                    'content' => $doc['content'],
                    'version' => $doc['version'],
                    'signedAt' => $doc['signedAt'],
                    'languageCode' => 'en'
                ];
            }

            Response::success($documents);
        } catch (Exception $e) {
            Response::error('Failed to retrieve documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 修改密码
     * POST /api/client/auth/change-password（支持预览时由前端拦截，后端统一用 ClientAuthContext）
     */
    public function changePassword() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId === null) {
            Response::unauthorized();
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // 验证输入
            Validator::make($data, [
                'currentPassword' => 'required',
                'newPassword' => 'required|min:6',
                'confirmPassword' => 'required'
            ]);

            if ($data['newPassword'] !== $data['confirmPassword']) {
                Response::error('New password and confirm password do not match', 400);
            }

            // 验证密码强度
            $passwordValidation = $this->passwordStrengthModel->validatePassword($data['newPassword']);
            if (!$passwordValidation['valid']) {
                Response::error('Password does not meet requirements', 400, [
                    'errors' => $passwordValidation['errors']
                ]);
            }

            // 获取包含密码哈希的用户
            $userWithPassword = $this->userModel->findByIdWithPassword($userId);

            if (!$userWithPassword) {
                Response::notFound('User not found');
            }

            // 验证当前密码
            if (!$this->userModel->verifyPassword($data['currentPassword'], $userWithPassword['passwordHash'])) {
                Response::error('Current password is incorrect', 400);
            }

            // 更新密码
            $newPasswordHash = $this->userModel->hashPassword($data['newPassword']);
            $this->userModel->update($userId, [
                'passwordHash' => $newPasswordHash
            ]);

            // 记录活动
            $this->activityLogModel->logActivity([
                'userId' => $userId,
                'activityType' => 'password_changed',
                'description' => 'Password changed by user'
            ]);

            Response::success(null, 'Password changed successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * 更新个人资料
     * PUT /api/client/auth/update-profile（支持预览时由前端拦截，后端统一用 ClientAuthContext）
     */
    public function updateProfile() {
        $userId = ClientAuthContext::getCurrentClientUserId();
        if ($userId === null) {
            Response::unauthorized();
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            // 必填字段列表
            $requiredFields = ['firstName', 'lastName', 'phone', 'country'];
            $fieldLabels = [
                'firstName' => 'First name',
                'lastName' => 'Last name',
                'phone' => 'Phone number',
                'country' => 'Country'
            ];
            $updateData = [];
            $errors = [];

            // 验证必填字段
            foreach ($requiredFields as $field) {
                $value = $data[$field] ?? null;

                if (is_string($value)) {
                    $value = trim($value);
                }

                // 检查是否为空
                if ($value === null || $value === '') {
                    $errors[$field] = ($fieldLabels[$field] ?? ucfirst($field)) . ' is required';
                } else {
                    // 验证字段长度
                    if (in_array($field, ['firstName', 'lastName'])) {
                        if (strlen($value) > 100) {
                            $errors[$field] = ($fieldLabels[$field] ?? ucfirst($field)) . ' must not exceed 100 characters';
                        } else {
                            $updateData[$field] = $value;
                        }
                    } elseif ($field === 'phone') {
                        if (strlen($value) > 30) {
                            $errors[$field] = 'Phone must not exceed 30 characters';
                        } else {
                            $updateData[$field] = $value;
                        }
                    } elseif ($field === 'country') {
                        if (strlen($value) > 100) {
                            $errors[$field] = 'Country must not exceed 100 characters';
                        } else {
                            $updateData[$field] = $value;
                        }
                    }
                }
            }

            // 如果有验证错误，返回错误信息
            if (!empty($errors)) {
                Response::validationError($errors);
            }

            // 开关开启时，校验手机号是否已被其他客户使用（排除自己）
            $appConfig = require __DIR__ . '/../config/app.php';
            if (!empty($appConfig['security']['check_duplicate_phone'])
                && !empty($updateData['phone'])
                && $this->userModel->findByPhone($updateData['phone'], $userId)) {
                Response::validationError([
                    'phone' => 'This phone number is already in use'
                ]);
            }

            // 计算本次真正变化的资料字段（增量同步用；这些是必填字段每次都提交，必须跟旧值比对）
            require_once __DIR__ . '/../services/TradingAccountProfileSyncService.php';
            $currentUser = $this->userModel->findById($userId);
            $nameChanged = (string)($updateData['firstName'] ?? '') !== (string)($currentUser['firstName'] ?? '')
                        || (string)($updateData['lastName'] ?? '') !== (string)($currentUser['lastName'] ?? '');
            $phoneChanged = isset($updateData['phone']) && (string)$updateData['phone'] !== (string)($currentUser['phone'] ?? '');
            $countryChanged = isset($updateData['country']) && (string)$updateData['country'] !== (string)($currentUser['country'] ?? '');

            // 只含变化字段的增量集，交给 swoole 异步同步（只同步 MT4/MT5，FP 不同步资料）
            $syncFields = [];
            if ($nameChanged)    $syncFields['name'] = trim(($updateData['firstName'] ?? '') . ' ' . ($updateData['lastName'] ?? ''));
            if ($phoneChanged)   $syncFields['phone'] = $updateData['phone'] ?? null;
            if ($countryChanged) $syncFields['country'] = $updateData['country'] ?? null;

            $this->userModel->update($userId, $updateData);
            $updatedUser = $this->userModel->findById($userId);

            $this->activityLogModel->logActivity([
                'userId' => $userId,
                'activityType' => 'profile_updated',
                'description' => 'Profile updated by client'
            ]);

            // 资料同步到 MT4/MT5：走 swoole 异步（best-effort）。$syncFields 已是增量集。FP 不同步资料。
            if (!empty($syncFields)) {
                TradingAccountProfileSyncService::dispatch($userId, $syncFields);
            }

            Response::success([
                'user' => $updatedUser
            ], 'Profile updated successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
