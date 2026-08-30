<?php
/**
 * SecuritySettings Controller
 * 交易安全设置控制器
 */

require_once __DIR__ . '/../models/SecuritySettings.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../services/OperationLog/TransactionSettingsOperationLog.php';

class SecuritySettingsController {
    private $securityModel;

    public function __construct() {
        $this->securityModel = new SecuritySettings();
    }

    /**
     * 获取所有安全设置
     * GET /api/transaction-settings/security-settings
     */
    public function index() {
        try {
            $settings = $this->securityModel->getAll();

            // 直接返回设置数据，而不是嵌套在 data 中
            Response::success($settings);
        } catch (Exception $e) {
            // Logger::error('Failed to get security settings', ['error' => $e->getMessage()]);
            Response::error('Failed to load security settings', 500);
        }
    }

    /**
     * 获取单个安全设置
     * GET /api/transaction-settings/security-settings/{key}
     */
    public function show($key) {
        try {
            $value = $this->securityModel->getSetting($key);

            if ($value === null) {
                Response::notFound('Setting not found');
                return;
            }

            Response::success(['data' => [$key => $value]]);
        } catch (Exception $e) {
            // Logger::error('Failed to get security setting', [
            //     'key' => $key,
            //     'error' => $e->getMessage()
            // ]);
            Response::error('Failed to load security setting', 500);
        }
    }

    /**
     * 批量更新安全设置
     * PUT /api/transaction-settings/security-settings
     *
     * Body: {
     *   "salesManagerNotifications": true,
     *   "withdrawalOtpRequired": true,
     *   "otpValidityMinutes": 10,
     *   "requireVerifiedWalletOnly": true
     * }
     */
    public function update() {
        $input = json_decode(file_get_contents('php://input'), true);
        try {
            // 验证管理员权限
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);

            // 检查是否是管理员
            if (!isset($payload['type']) || $payload['type'] !== 'admin') {
                TransactionSettingsOperationLog::logFailure(
                    is_array($input) ? $input : [],
                    'edit',
                    'transactionSettingsSecurityFailure',
                    'Admin authentication required'
                );
                Response::unauthorized('Admin authentication required');
                return;
            }

            $adminId = $payload['userId'] ?? null;

            if (!$input) {
                TransactionSettingsOperationLog::logFailure(
                    [],
                    'edit',
                    'transactionSettingsSecurityFailure',
                    'Invalid request data'
                );
                Response::badRequest('Invalid request data');
                return;
            }

            // 验证数据
            $validatedData = $this->validateSettings($input);

            if (!$validatedData['valid']) {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsSecurityFailure',
                    $validatedData['error']
                );
                Response::badRequest($validatedData['error']);
                return;
            }

            $before = $this->securityModel->getAll();
            list($changesZh, $changesEn) = TransactionSettingsOperationLog::detectSecurityChanges(
                is_array($before) ? $before : [],
                $input
            );

            // 批量更新设置
            $result = $this->securityModel->updateBatch($input, $adminId);

            if ($result) {
                TransactionSettingsOperationLog::logSecurityChanges($input, $changesZh, $changesEn);
                Response::success([
                    'message' => 'Security settings updated successfully',
                    'data' => $this->securityModel->getAll()
                ]);
            } else {
                TransactionSettingsOperationLog::logFailure(
                    $input,
                    'edit',
                    'transactionSettingsSecurityFailure',
                    'Failed to update security settings'
                );
                Response::error('Failed to update security settings', 500);
            }
        } catch (Exception $e) {
            TransactionSettingsOperationLog::logFailure(
                is_array($input) ? $input : [],
                'edit',
                'transactionSettingsSecurityFailure',
                'Failed to update security settings: ' . $e->getMessage()
            );
            Response::error('Failed to update security settings', 500);
        }
    }

    /**
     * 验证设置数据
     * @param array $data - 设置数据
     * @return array
     */
    private function validateSettings($data) {
        // 验证 otpValidityMinutes
        if (isset($data['otpValidityMinutes'])) {
            $minutes = $data['otpValidityMinutes'];
            if (!is_numeric($minutes) || $minutes < 1 || $minutes > 60) {
                return [
                    'valid' => false,
                    'error' => 'OTP validity period must be between 1 and 60 minutes'
                ];
            }
        }

        // 验证 verificationMaxFileSize
        if (isset($data['verificationMaxFileSize'])) {
            $size = $data['verificationMaxFileSize'];
            if (!is_numeric($size) || $size < 1 || $size > 20) {
                return [
                    'valid' => false,
                    'error' => 'Verification file size must be between 1 and 20 MB'
                ];
            }
        }

        // 验证 boolean 字段
        $booleanFields = [
            'salesManagerNotifications',
            'withdrawalOtpRequired',
            'requireVerifiedWalletOnly',
            'requireWithdrawalVerification',
            'autoRejectUnverified'
        ];
        foreach ($booleanFields as $field) {
            if (isset($data[$field]) && !is_bool($data[$field])) {
                return [
                    'valid' => false,
                    'error' => "{$field} must be a boolean value"
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * 恢复默认设置
     * POST /api/transaction-settings/security-settings/restore-defaults
     */
    public function restoreDefaults() {
        try {
            // 验证管理员权限
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);

            if (!isset($payload['type']) || $payload['type'] !== 'admin') {
                Response::unauthorized('Admin authentication required');
                return;
            }

            $adminId = $payload['userId'] ?? null;

            $defaultSettings = [
                'salesManagerNotifications' => false,
                'withdrawalOtpRequired' => false,
                'otpValidityMinutes' => 10,
                'requireVerifiedWalletOnly' => true,
                'requireWithdrawalVerification' => false,
                'verificationMaxFileSize' => 5,
                'autoRejectUnverified' => false
            ];

            $result = $this->securityModel->updateBatch($defaultSettings, $adminId);

            if ($result) {
                Response::success([
                    'message' => 'Security settings restored to defaults',
                    'data' => $this->securityModel->getAll()
                ]);
            } else {
                Response::error('Failed to restore default settings', 500);
            }
        } catch (Exception $e) {
            // Logger::error('Failed to restore default settings', ['error' => $e->getMessage()]);
            Response::error('Failed to restore default settings', 500);
        }
    }

    /**
     * 获取安全设置统计
     * GET /api/transaction-settings/security-stats
     */
    public function stats() {
        try {
            $settings = $this->securityModel->getAll();

            // 获取OTP使用统计
            $database = Database::getInstance();
            $query = "SELECT
                        COUNT(*) as totalOtpSent,
                        SUM(CASE WHEN isVerified = 1 THEN 1 ELSE 0 END) as totalOtpVerified,
                        SUM(CASE WHEN attempts >= maxAttempts AND isVerified = 0 THEN 1 ELSE 0 END) as totalOtpFailed,
                        AVG(attempts) as avgAttempts
                     FROM withdrawalOtpVerifications
                     WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

            // 使用 Database 类的方法，自动规范化数据类型
            $otpStats = $database->fetchOne($query, []);

            Response::success([
                'data' => [
                    'settings' => $settings,
                    'otpStats' => $otpStats
                ]
            ]);
        } catch (Exception $e) {
            // Logger::error('Failed to get security stats', ['error' => $e->getMessage()]);
            Response::error('Failed to load security statistics', 500);
        }
    }
}
