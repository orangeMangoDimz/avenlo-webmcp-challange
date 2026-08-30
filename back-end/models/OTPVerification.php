<?php
/**
 * OTPVerification Model
 * OTP验证模型
 */

require_once __DIR__ . '/../utils/Database.php';

class OTPVerification {
    private $db;
    private $table = 'withdrawalOtpVerifications';

    // 可填充字段
    protected $fillable = [
        'userId',
        'otpCode',
        'otpHash',
        'expiresAt',
        'ipAddress',
        'userAgent',
        'maxAttempts'
    ];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 生成并存储新的OTP
     * @param int $userId - 用户ID
     * @param int $validityMinutes - 有效期（分钟）
     * @param string $ipAddress - IP地址
     * @param string $userAgent - User Agent
     * @return array - ['success' => bool, 'otpCode' => string, 'expiresAt' => datetime]
     */
    public function generateOTP($userId, $validityMinutes = 10, $ipAddress = null, $userAgent = null) {
        try {
            // 生成6位数字OTP
            $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // 计算过期时间
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityMinutes} minutes"));

            // Hash OTP用于存储
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);

            // 使旧的未验证OTP失效（通过删除或标记过期）
            $this->invalidateOldOTPs($userId);

            // 插入新OTP
            $insertData = [
                'userId' => $userId,
                'otpCode' => $otpCode,
                'otpHash' => $otpHash,
                'expiresAt' => $expiresAt,
                'ipAddress' => $ipAddress,
                'userAgent' => $userAgent,
                'isVerified' => 0,
                'attempts' => 0,
                'maxAttempts' => 5,
                'createdAt' => date('Y-m-d H:i:s')
            ];

            $otpId = $this->db->insert($this->table, $insertData);

            if ($otpId) {
                return [
                    'success' => true,
                    'otpCode' => $otpCode,
                    'expiresAt' => $expiresAt,
                    'otpId' => $otpId
                ];
            }

            return ['success' => false, 'error' => 'Failed to generate OTP'];
        } catch (Exception $e) {
            error_log("Generate OTP error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 验证OTP
     * @param int $userId - 用户ID
     * @param string $otpCode - 用户输入的OTP
     * @return array - ['success' => bool, 'message' => string]
     */
    public function verifyOTP($userId, $otpCode) {
        try {
            // 查找最新的未验证OTP
            $query = "SELECT id, otpHash, expiresAt, attempts, maxAttempts
                     FROM {$this->table}
                     WHERE userId = :userId
                       AND isVerified = 0
                       AND expiresAt > NOW()
                     ORDER BY createdAt DESC
                     LIMIT 1";

            // 使用 Database 类的方法，自动规范化数据类型
            $otp = $this->db->fetchOne($query, ['userId' => $userId]);

            if (!$otp) {
                return [
                    'success' => false,
                    'error' => 'No valid OTP found or OTP has expired'
                ];
            }

            // 检查尝试次数
            if ($otp['attempts'] >= $otp['maxAttempts']) {
                return [
                    'success' => false,
                    'error' => 'Maximum verification attempts exceeded'
                ];
            }

            // 增加尝试次数
            $this->incrementAttempts($otp['id']);

            // 验证OTP
            if (password_verify($otpCode, $otp['otpHash'])) {
                // 标记为已验证
                $this->markAsVerified($otp['id']);

                return [
                    'success' => true,
                    'message' => 'OTP verified successfully'
                ];
            } else {
                $remainingAttempts = $otp['maxAttempts'] - ($otp['attempts'] + 1);
                return [
                    'success' => false,
                    'error' => 'Invalid OTP code',
                    'remainingAttempts' => $remainingAttempts
                ];
            }
        } catch (Exception $e) {
            error_log("Verify OTP error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查用户是否有已验证的OTP
     * @param int $userId - 用户ID
     * @param int $validForMinutes - OTP验证后有效时长（默认30分钟）
     * @return bool
     */
    public function hasValidVerifiedOTP($userId, $validForMinutes = 30) {
        try {
            $query = "SELECT id
                     FROM {$this->table}
                     WHERE userId = :userId
                       AND isVerified = 1
                       AND verifiedAt > DATE_SUB(NOW(), INTERVAL :validMinutes MINUTE)
                     LIMIT 1";

            $result = $this->db->fetchOne($query, [
                'userId' => $userId,
                'validMinutes' => $validForMinutes
            ]);

            return !empty($result);
        } catch (Exception $e) {
            error_log("Check valid OTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 使旧的未验证OTP失效
     * @param int $userId - 用户ID
     */
    private function invalidateOldOTPs($userId) {
        try {
            // 删除旧的未验证OTP
            $this->db->delete($this->table, 'userId = :userId AND isVerified = 0', ['userId' => $userId]);
        } catch (Exception $e) {
            error_log("Invalidate old OTPs error: " . $e->getMessage());
        }
    }

    /**
     * 增加验证尝试次数
     * @param int $otpId - OTP ID
     */
    private function incrementAttempts($otpId) {
        try {
            // 使用原生 SQL 因为需要 attempts = attempts + 1
            $query = "UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = :id";
            $this->db->query($query, ['id' => $otpId]);
        } catch (Exception $e) {
            error_log("Increment OTP attempts error: " . $e->getMessage());
        }
    }

    /**
     * 标记OTP为已验证
     * @param int $otpId - OTP ID
     */
    private function markAsVerified($otpId) {
        try {
            $updateData = [
                'isVerified' => 1,
                'verifiedAt' => date('Y-m-d H:i:s')
            ];
            $this->db->update($this->table, $updateData, 'id = :id', ['id' => $otpId]);
        } catch (Exception $e) {
            error_log("Mark OTP as verified error: " . $e->getMessage());
        }
    }

    /**
     * 清理过期的OTP记录
     * @param int $daysOld - 保留多少天内的记录（默认30天）
     */
    public function cleanupExpired($daysOld = 30) {
        try {
            // 使用原生 SQL 因为使用了 DATE_SUB 函数
            $query = "DELETE FROM {$this->table} WHERE createdAt < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $this->db->query($query, ['days' => $daysOld]);
            return true;
        } catch (Exception $e) {
            error_log("Cleanup expired OTPs error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取用户的OTP历史
     * @param int $userId - 用户ID
     * @param int $limit - 限制数量
     * @return array
     */
    public function getUserOTPHistory($userId, $limit = 10) {
        try {
            $limit = max(1, (int)$limit); // 确保是正整数
            $query = "SELECT id, isVerified, verifiedAt, expiresAt, attempts, maxAttempts,
                            ipAddress, createdAt
                     FROM {$this->table}
                     WHERE userId = :userId
                     ORDER BY createdAt DESC
                     LIMIT {$limit}";

            // 使用 Database 类的方法，自动规范化数据类型
            return $this->db->fetchAll($query, ['userId' => $userId]);
        } catch (Exception $e) {
            error_log("Get user OTP history error: " . $e->getMessage());
            return [];
        }
    }
}
