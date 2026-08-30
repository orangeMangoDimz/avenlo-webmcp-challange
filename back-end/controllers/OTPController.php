<?php
/**
 * OTP Controller
 * OTP验证控制器
 */

require_once __DIR__ . '/../models/OTPVerification.php';
require_once __DIR__ . '/../models/EmailOtpVerification.php';
require_once __DIR__ . '/../models/SecuritySettings.php';
require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/EmailSender.php';

class OTPController {
    private $otpModel;
    private $emailOtpModel;
    private $securityModel;
    private $emailTemplateModel;

    public function __construct() {
        $this->otpModel = new OTPVerification();
        $this->emailOtpModel = new EmailOtpVerification();
        $this->securityModel = new SecuritySettings();
        $this->emailTemplateModel = new EmailTemplate();
    }

    /**
     * 请求出金OTP
     * POST /api/withdrawals/request-otp
     */
    public function requestWithdrawalOTP() {
        try {
            // 验证客户端token
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);

            // 必须是客户端token
            if (isset($payload['type']) && $payload['type'] === 'admin') {
                Response::unauthorized('Client authentication required');
                return;
            }

            $userId = $payload['userId'] ?? null;

            if (!$userId) {
                Response::unauthorized('Invalid user token');
                return;
            }

            // 检查是否启用OTP
            $otpRequired = $this->securityModel->isWithdrawalOtpRequired();

            if (!$otpRequired) {
                Response::success([
                    'message' => 'OTP verification is not enabled',
                    'data' => [
                        'otpRequired' => false
                    ]
                ]);
                return;
            }

            // 获取OTP有效期
            $validityMinutes = $this->securityModel->getOtpValidityMinutes();

            // 获取客户端IP和User Agent
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // 生成OTP
            $result = $this->otpModel->generateOTP($userId, $validityMinutes, $ipAddress, $userAgent);

            if (!$result['success']) {
                Response::error($result['error'] ?? 'Failed to generate OTP', 500);
                return;
            }

            // 获取用户信息用于发送邮件
            $userInfo = $this->getUserInfo($userId);

            if (!$userInfo) {
                Response::error('User not found', 404);
                return;
            }

            // 发送OTP到用户邮箱
            $emailSent = $this->sendOTPEmail(
                $userInfo['email'],
                $userInfo['firstName'],
                $result['otpCode'],
                $validityMinutes
            );
            if ($emailSent) {
                Response::success([
                    'message' => 'OTP has been sent to your registered email',
                    'data' => [
                        'otpRequired' => true,
                        'expiresAt' => $result['expiresAt'],
                        'validityMinutes' => $validityMinutes,
                        'email' => $this->maskEmail($userInfo['email'])
                    ]
                ]);
            } else {
                Response::error('Failed to send OTP email', 500);
            }
        } catch (Exception $e) {
            // Logger::error('Failed to request withdrawal OTP', [
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to send OTP', 500);
        }
    }

    /**
     * 验证出金OTP
     * POST /api/withdrawals/verify-otp
     * Body: { "otpCode": "123456" }
     */
    public function verifyWithdrawalOTP() {
        try {
            // 验证客户端token
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);

            if (isset($payload['type']) && $payload['type'] === 'admin') {
                Response::unauthorized('Client authentication required');
                return;
            }

            $userId = $payload['userId'] ?? null;

            if (!$userId) {
                Response::unauthorized('Invalid user token');
                return;
            }

            // 获取请求数据
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['otpCode']) || empty($data['otpCode'])) {
                Response::badRequest('OTP code is required');
                return;
            }

            $otpCode = trim($data['otpCode']);

            // 验证OTP
            $result = $this->otpModel->verifyOTP($userId, $otpCode);

            if ($result['success']) {
                Response::success([
                    'message' => 'OTP verified successfully',
                    'data' => [
                        'verified' => true,
                        'validFor' => 30 // OTP验证后30分钟内有效
                    ]
                ]);
            } else {
                // Logger::warning('Withdrawal OTP verification failed', [
                //     'user_id' => $userId,
                //     'error' => $result['error']
                // ]);

                Response::error($result['error'], 400);
            }
        } catch (Exception $e) {
            // Logger::error('Failed to verify withdrawal OTP', [
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Verification failed', 500);
        }
    }

    /**
     * 检查用户的OTP验证状态
     * GET /api/withdrawals/otp-status
     */
    public function checkOTPStatus() {
        try {
            // 验证客户端token
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);

            if (isset($payload['type']) && $payload['type'] === 'admin') {
                Response::unauthorized('Client authentication required');
                return;
            }

            $userId = $payload['userId'] ?? null;

            if (!$userId) {
                Response::unauthorized('Invalid user token');
                return;
            }

            // 检查是否启用OTP
            $otpRequired = $this->securityModel->isWithdrawalOtpRequired();

            if (!$otpRequired) {
                Response::success([
                    'data' => [
                        'otpRequired' => false,
                        'verified' => true // 如果不需要OTP，直接视为已验证
                    ]
                ]);
                return;
            }

            // 检查是否有有效的已验证OTP
            $hasValidOTP = $this->otpModel->hasValidVerifiedOTP($userId, 30);

            Response::success([
                'data' => [
                    'otpRequired' => true,
                    'verified' => $hasValidOTP,
                    'requireVerifiedWalletOnly' => $this->securityModel->isVerifiedWalletOnly()
                ]
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to check OTP status', [
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to check OTP status', 500);
        }
    }

    /**
     * 获取用户信息
     * @param int $userId
     * @return array|null
     */
    private function getUserInfo($userId) {
        try {
            $db = Database::getInstance();

            $query = "SELECT id, email, firstName, lastName
                     FROM clientUsers
                     WHERE id = :userId";

            // 使用 Database 类的方法，自动规范化数据类型
            return $db->fetchOne($query, ['userId' => $userId]);
        } catch (Exception $e) {
            Logger::error("Get user info error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 发送邮箱验证码（通用方法，可用于管理员和客户端邮箱验证）
     * POST /api/otp/send-email-code
     */
    public function sendEmailVerificationCode() {
        try {
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);
            $userId = $payload['userId'] ?? null;

            if (!$userId) {
                Response::unauthorized('Invalid user token');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['email']) || empty($data['email'])) {
                Response::badRequest('Email is required');
                return;
            }

            $email = $data['email'];

            // 验证邮箱格式
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Invalid email format');
                return;
            }

            // 确定用户类型
            $userType = ($payload['type'] ?? null) === 'admin' ? 'admin' : 'client';

            // 获取用户信息
            $userInfo = $this->getUserInfoForOTP($userId, $userType);

            if (!$userInfo) {
                Response::error('User not found', 404);
                return;
            }

            // 检查邮箱是否已被其他用户使用（如果是管理员）
            if ($userType === 'admin') {
                require_once __DIR__ . '/../models/AdminUser.php';
                $adminUserModel = new AdminUser();
                $existing = $adminUserModel->findByEmail($email);
                if ($existing && $existing['id'] != $userId) {
                    Response::error('Email already in use', 400);
                    return;
                }
            }

            // 生成或更新OTP（10分钟有效期，根据userId+userType更新现有记录）
            $validityMinutes = 10;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $result = $this->emailOtpModel->generateOrUpdateOTP(
                $userId,
                $userType,
                $email,
                $validityMinutes,
                $ipAddress,
                $userAgent
            );

            if (!$result['success']) {
                Response::error($result['error'] ?? 'Failed to generate OTP', 500);
                return;
            }

            // 用途：决定邮件里描述这次验证是干什么的（改邮箱 / 改交易密码…），默认改邮箱保持兼容
            $purpose = is_string($data['purpose'] ?? null) ? trim($data['purpose']) : 'email';

            // 发送OTP邮件
            $userName = $userInfo['fullName'] ?? $userInfo['firstName'] ?? $userInfo['username'] ?? 'User';
            $emailSent = $this->sendEmailVerificationCodeEmail(
                $email,
                $userName,
                $result['otpCode'],
                $validityMinutes,
                $purpose
            );

            if ($emailSent) {
                Response::success([
                    'message' => 'Verification code has been sent to your email',
                    'data' => [
                        'expiresAt' => $result['expiresAt'],
                        'validityMinutes' => $validityMinutes
                    ]
                ]);
            } else {
                Response::error('Failed to send verification code email', 500);
            }
        } catch (Exception $e) {
//            Logger::error('Failed to send email verification code', [
//                'error' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);
            Response::error('Failed to send verification code', 500);
        }
    }

    /**
     * 验证邮箱验证码（通用方法）
     * POST /api/otp/verify-email-code
     */
    public function verifyEmailVerificationCode() {
        try {
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);
            $userId = $payload['userId'] ?? null;

            if (!$userId) {
                Response::unauthorized('Invalid user token');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['email']) || empty($data['email'])) {
                Response::badRequest('Email is required');
                return;
            }

            if (!isset($data['code']) || empty($data['code'])) {
                Response::badRequest('Verification code is required');
                return;
            }

            $email = $data['email'];
            $code = trim($data['code']);

            // 确定用户类型
            $userType = ($payload['type'] ?? null) === 'admin' ? 'admin' : 'client';

            // 验证OTP
            $result = $this->emailOtpModel->verifyOTP($userId, $userType, $code, $email);

            if ($result['success']) {
                // 验证成功，存储到session（用于后续邮箱更新验证）
                if (!isset($_SESSION)) {
                    session_start();
                }

                $_SESSION['email_verified_' . $userId] = [
                    'email' => $email,
                    'userType' => $userType,
                    'verified_at' => time(),
                    'expires_at' => time() + 600 // 10分钟内有效
                ];

                Response::success([
                    'message' => 'Email verified successfully',
                    'data' => [
                        'verified' => true
                    ]
                ]);
            } else {
                Response::error($result['error'] ?? 'Invalid verification code', 400);
            }
        } catch (Exception $e) {
//            Logger::error('Failed to verify email code', [
//                'error' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);
            Response::error('Verification failed', 500);
        }
    }

    /**
     * 获取用户信息（支持管理员和客户端）
     * @param int $userId
     * @param string $userType - 'admin' 或 'client'
     * @return array|null
     */
    private function getUserInfoForOTP($userId, $userType) {
        try {
            $db = Database::getInstance();

            if ($userType === 'admin') {
                $query = "SELECT id, email, fullName as fullName, username
                         FROM adminUsers
                         WHERE id = :userId AND deletedAt IS NULL";
            } else {
                $query = "SELECT id, email, firstName, lastName, CONCAT(firstName, ' ', lastName) as fullName
                         FROM clientUsers
                         WHERE id = :userId";
            }

            return $db->fetchOne($query, ['userId' => $userId]);
        } catch (Exception $e) {
            Logger::error("Get user info for OTP error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 发送邮箱验证码邮件（用于邮箱验证场景）
     * @param string $email - 收件人邮箱
     * @param string $userName - 用户名
     * @param string $otpCode - OTP代码
     * @param int $validityMinutes - 有效期（分钟）
     * @return bool
     */
    private function sendEmailVerificationCodeEmail($email, $userName, $otpCode, $validityMinutes, $purpose = 'email') {
        try {
            $emailSender = new EmailSender();

            // 获取邮件模板
            $template = $this->emailTemplateModel->getByKey('email_verification_code');

            if (!$template) {
                Logger::error("Email verification code template not found");
                return false;
            }

            // 获取平台名称
            $appConfig = require __DIR__ . '/../config/app.php';
            $platformName = $appConfig['branding']['companyName'] ?? $appConfig['logoname'] ?? 'Trading Platform';

            // 用途文案：模板里用 {{actionText}} 占位，按 purpose 替换成对应的动作描述
            $actionTextMap = [
                'email' => 'You have requested to change your email address.',
                'trading_password' => 'You have requested to change your trading account password.',
            ];
            $actionText = $actionTextMap[$purpose] ?? $actionTextMap['email'];

            // 准备变量
            $variables = [
                'userName' => $userName,
                'otpCode' => $otpCode,
                'validityMinutes' => (string)$validityMinutes,
                'platformName' => $platformName,
                'currentYear' => date('Y'),
                'actionText' => $actionText
            ];

            // 替换邮件主题和内容中的变量
            $subject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
            $htmlBody = EmailTemplate::replaceVariables($template['emailBody'], $variables);

            // 使用 Logger::error() 输出邮件内容（用于调试）
//            Logger::error('Email Verification Code Email Content', [
//                'to' => $email,
//                'subject' => $subject,
//                'body' => $htmlBody,
//                'otpCode' => $otpCode,
//                'validityMinutes' => $validityMinutes,
//                'userName' => $userName
//            ]);

            return $emailSender->send($email, $subject, $htmlBody);
        } catch (Exception $e) {
            Logger::error("Send email verification code error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 发送OTP邮件（用于提现验证）
     * @param string $email - 收件人邮箱
     * @param string $firstName - 用户名
     * @param string $otpCode - OTP代码
     * @param int $validityMinutes - 有效期（分钟）
     * @return bool
     */
    private function sendOTPEmail($email, $firstName, $otpCode, $validityMinutes) {
        try {
            $emailSender = new EmailSender();

            // 获取平台名称
            $appConfig = require __DIR__ . '/../config/app.php';
            $platformName = $appConfig['branding']['companyName'] ?? $appConfig['logoname'] ?? 'Trading Platform';

            // 邮件主题和内容（写死，不再从模板获取）
            $subject = "Your Withdrawal Verification Code";

            $htmlBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #f7fafc; padding: 30px; border-radius: 0 0 8px 8px; }
                    .otp-box { background: white; border: 3px solid #667eea; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
                    .otp-code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 8px; margin: 10px 0; }
                    .warning { background: #fff5f5; border-left: 4px solid #fc8181; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .footer { text-align: center; color: #718096; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔐 Withdrawal Verification</h1>
                    </div>
                    <div class='content'>
                        <p>Hello {$firstName},</p>
                        <p>You have requested to withdraw funds from your trading account. For your security, please use the verification code below:</p>

                        <div class='otp-box'>
                            <p style='margin: 0; color: #718096; font-size: 14px;'>Your Verification Code</p>
                            <div class='otp-code'>{$otpCode}</div>
                            <p style='margin: 0; color: #718096; font-size: 13px;'>Valid for {$validityMinutes} minutes</p>
                        </div>

                        <div class='warning'>
                            <p style='margin: 0;'><strong>⚠️ Security Notice:</strong></p>
                            <p style='margin: 5px 0 0 0;'>Never share this code with anyone. Our team will never ask for your verification code.</p>
                        </div>

                        <p>If you did not request this withdrawal, please contact our support team immediately and change your account password.</p>

                        <div class='footer'>
                            <p>This is an automated message, please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " {$platformName}. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";

            return $emailSender->send($email, $subject, $htmlBody);
        } catch (Exception $e) {
            Logger::error("Send OTP email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 隐藏邮箱地址
     * @param string $email
     * @return string
     */
    private function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $username = $parts[0];
        $domain = $parts[1];

        if (strlen($username) <= 2) {
            return $email;
        }

        $masked = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
        return $masked . '@' . $domain;
    }
}
