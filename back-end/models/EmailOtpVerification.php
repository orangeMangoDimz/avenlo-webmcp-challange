<?php
/**
 * EmailOtpVerification Model
 * 邮箱验证码模型（通用，支持管理员和客户端）
 */

require_once __DIR__ . '/BaseModel.php';

class EmailOtpVerification extends BaseModel {
    protected $table = 'email_otp_verifications';

    /**
     * 生成或更新OTP（根据userId+userType唯一性，更新现有记录）
     * @param int $userId - 用户ID
     * @param string $userType - 用户类型：'admin' 或 'client'
     * @param string $email - 目标邮箱地址
     * @param int $validityMinutes - 有效期（分钟，默认10分钟）
     * @param string $ipAddress - IP地址
     * @param string $userAgent - User Agent
     * @return array - ['success' => bool, 'otpCode' => string, 'expiresAt' => datetime]
     */
    public function generateOrUpdateOTP($userId, $userType, $email, $validityMinutes = 10, $ipAddress = null, $userAgent = null) {
        try {
            // 生成6位数字OTP
            $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // 计算过期时间
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityMinutes} minutes"));

            // Hash OTP用于存储
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);

            // 检查是否已存在记录（根据userId+userType唯一性）
            $existing = $this->findByUser($userId, $userType);

            if ($existing) {
                // 更新现有记录（重置验证状态和尝试次数）
                $updateData = [
                    'email' => $email,
                    'otp_hash' => $otpHash,
                    'expires_at' => $expiresAt,
                    'is_verified' => 0,
                    'verified_at' => null,
                    'attempts' => 0,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ];

                $this->db->update($this->table, $updateData, 'id = :id', ['id' => $existing['id']]);
                $otpId = $existing['id'];
            } else {
                // 插入新记录
                $insertData = [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'email' => $email,
                    'otp_hash' => $otpHash,
                    'expires_at' => $expiresAt,
                    'is_verified' => 0,
                    'attempts' => 0,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ];

                $otpId = $this->db->insert($this->table, $insertData);
            }

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
            error_log("Generate email OTP error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 验证OTP
     * @param int $userId - 用户ID
     * @param string $userType - 用户类型
     * @param string $otpCode - 用户输入的OTP
     * @param string $email - 目标邮箱（用于验证）
     * @return array - ['success' => bool, 'message' => string]
     */
    public function verifyOTP($userId, $userType, $otpCode, $email) {
        try {
            // 查找用户的OTP记录
            $otp = $this->findByUser($userId, $userType);

            if (!$otp) {
                return [
                    'success' => false,
                    'error' => 'No verification code found. Please request a new code.'
                ];
            }

            // 检查是否已过期
            if (strtotime($otp['expires_at']) < time()) {
                return [
                    'success' => false,
                    'error' => 'Verification code has expired. Please request a new code.'
                ];
            }

            // 检查是否已验证
            if ($otp['is_verified']) {
                return [
                    'success' => false,
                    'error' => 'This verification code has already been used.'
                ];
            }

            // 检查邮箱是否匹配
            if ($otp['email'] !== $email) {
                return [
                    'success' => false,
                    'error' => 'Email does not match the verification code.'
                ];
            }

            // 增加尝试次数
            $this->incrementAttempts($otp['id']);

            // 验证OTP
            if (password_verify($otpCode, $otp['otp_hash'])) {
                // 标记为已验证
                $this->markAsVerified($otp['id']);

                return [
                    'success' => true,
                    'message' => 'Email verified successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Invalid verification code'
                ];
            }
        } catch (Exception $e) {
            error_log("Verify email OTP error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 根据用户ID和类型查找OTP记录
     * @param int $userId
     * @param string $userType
     * @return array|null
     */
    public function findByUser($userId, $userType) {
        try {
            $query = "SELECT * FROM {$this->table}
                     WHERE user_id = :userId AND user_type = :userType
                     LIMIT 1";

            return $this->db->fetchOne($query, [
                'userId' => $userId,
                'userType' => $userType
            ]);
        } catch (Exception $e) {
            error_log("Find email OTP by user error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 检查用户是否有已验证的OTP（在有效期内）
     * @param int $userId
     * @param string $userType
     * @param string $email
     * @param int $validForMinutes - 验证后有效时长（默认10分钟）
     * @return bool
     */
    public function hasValidVerifiedOTP($userId, $userType, $email, $validForMinutes = 10) {
        try {
            $query = "SELECT id FROM {$this->table}
                     WHERE user_id = :userId
                       AND user_type = :userType
                       AND email = :email
                       AND is_verified = 1
                       AND verified_at > DATE_SUB(NOW(), INTERVAL :validMinutes MINUTE)
                     LIMIT 1";

            $result = $this->db->fetchOne($query, [
                'userId' => $userId,
                'userType' => $userType,
                'email' => $email,
                'validMinutes' => $validForMinutes
            ]);

            return !empty($result);
        } catch (Exception $e) {
            error_log("Check valid email OTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 增加验证尝试次数
     * @param int $otpId
     */
    private function incrementAttempts($otpId) {
        try {
            $query = "UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = :id";
            $this->db->query($query, ['id' => $otpId]);
        } catch (Exception $e) {
            error_log("Increment email OTP attempts error: " . $e->getMessage());
        }
    }

    /**
     * 标记OTP为已验证
     * @param int $otpId
     */
    private function markAsVerified($otpId) {
        try {
            $updateData = [
                'is_verified' => 1,
                'verified_at' => date('Y-m-d H:i:s')
            ];
            $this->db->update($this->table, $updateData, 'id = :id', ['id' => $otpId]);
        } catch (Exception $e) {
            error_log("Mark email OTP as verified error: " . $e->getMessage());
        }
    }

    /**
     * 清理过期的OTP记录
     * @param int $daysOld - 保留多少天内的记录（默认7天）
     */
    public function cleanupExpired($daysOld = 7) {
        try {
            $query = "DELETE FROM {$this->table}
                     WHERE expires_at < NOW()
                       AND (is_verified = 0 OR created_at < DATE_SUB(NOW(), INTERVAL :days DAY))";
            $this->db->query($query, ['days' => $daysOld]);
            return true;
        } catch (Exception $e) {
            error_log("Cleanup expired email OTPs error: " . $e->getMessage());
            return false;
        }
    }
}
