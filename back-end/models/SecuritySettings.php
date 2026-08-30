<?php
/**
 * SecuritySettings Model
 * 交易安全设置模型
 */

require_once __DIR__ . '/../utils/Database.php';

class SecuritySettings {
    private $conn;
    private $table = 'transactionSecuritySettings';

    // 可填充字段
    protected $fillable = [
        'settingKey',
        'settingValue',
        'settingType',
        'description',
        'updatedBy'
    ];

    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }

    /**
     * 获取所有安全设置
     * @return array - 格式化的设置数组
     */
    public function getAll() {
        try {
            $query = "SELECT settingKey, settingValue, settingType, description, updatedAt
                     FROM {$this->table}
                     ORDER BY id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // 根据类型转换值
                $value = $this->castValue($row['settingValue'], $row['settingType']);
                $settings[$row['settingKey']] = $value;
            }

            $appConfig = require __DIR__ . '/../config/app.php';
            if (($appConfig['env'] ?? '') !== 'production') {
                $settings['withdrawalOtpRequired'] = false;
            }

            return $settings;
        } catch (PDOException $e) {
            error_log("Get security settings error: " . $e->getMessage());
            throw new Exception("Failed to retrieve security settings");
        }
    }

    /**
     * 获取单个设置
     * @param string $key - 设置键
     * @return mixed - 设置值
     */
    public function getSetting($key) {
        try {
            $query = "SELECT settingValue, settingType
                     FROM {$this->table}
                     WHERE settingKey = :key";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $this->castValue($row['settingValue'], $row['settingType']);
            }

            return null;
        } catch (PDOException $e) {
            error_log("Get security setting error: " . $e->getMessage());
            throw new Exception("Failed to retrieve security setting");
        }
    }

    /**
     * 更新单个设置
     * @param string $key - 设置键
     * @param mixed $value - 设置值
     * @param int $adminId - 管理员ID
     * @return bool
     */
    public function updateSetting($key, $value, $adminId = null) {
        try {
            // 先获取设置类型
            $query = "SELECT settingType FROM {$this->table} WHERE settingKey = :key";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return false;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $settingType = $row['settingType'];

            // 转换值为字符串存储
            $valueStr = $this->valueToString($value, $settingType);

            // 更新设置
            $query = "UPDATE {$this->table}
                     SET settingValue = :value,
                         updatedBy = :adminId,
                         updatedAt = NOW()
                     WHERE settingKey = :key";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':value', $valueStr);
            $stmt->bindParam(':adminId', $adminId);
            $stmt->bindParam(':key', $key);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Update security setting error: " . $e->getMessage());
            throw new Exception("Failed to update security setting");
        }
    }

    /**
     * 批量更新设置
     * @param array $settings - 设置数组 ['key' => 'value']
     * @param int $adminId - 管理员ID
     * @return bool
     */
    public function updateBatch($settings, $adminId = null) {
        try {
            $this->conn->beginTransaction();

            foreach ($settings as $key => $value) {
                $this->updateSetting($key, $value, $adminId);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Batch update security settings error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 根据类型转换值
     * @param string $value - 字符串值
     * @param string $type - 数据类型
     * @return mixed
     */
    private function castValue($value, $type) {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                return $value === '1' || $value === 'true';
            case 'number':
                return is_numeric($value) ? floatval($value) : null;
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }

    /**
     * 将值转换为字符串存储
     * @param mixed $value - 值
     * @param string $type - 数据类型
     * @return string
     */
    private function valueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'number':
                return (string)$value;
            case 'json':
                return is_string($value) ? $value : json_encode($value);
            case 'string':
            default:
                return (string)$value;
        }
    }

    /**
     * 检查是否启用Sales Manager通知
     * @return bool
     */
    public function isSalesManagerNotificationsEnabled() {
        return $this->getSetting('salesManagerNotifications') === true;
    }

    /**
     * 检查是否启用出金OTP
     * @return bool
     */
    public function isWithdrawalOtpRequired() {
        $appConfig = require __DIR__ . '/../config/app.php';
        if (($appConfig['env'] ?? '') !== 'production') {
            return false;
        }
        return $this->getSetting('withdrawalOtpRequired') === true;
    }

    /**
     * 获取OTP有效期（分钟）
     * @return int
     */
    public function getOtpValidityMinutes() {
        $value = $this->getSetting('otpValidityMinutes');
        return $value ? (int)$value : 10;
    }

    /**
     * 检查是否仅允许已验证钱包
     * @return bool
     */
    public function isVerifiedWalletOnly() {
        return $this->getSetting('requireVerifiedWalletOnly') === true;
    }

    /**
     * 检查是否需要出金账户验证
     * @return bool
     */
    public function isWithdrawalVerificationRequired() {
        return $this->getSetting('requireWithdrawalVerification') === true;
    }

    /**
     * 获取验证文件最大大小（MB）
     * @return int
     */
    public function getVerificationMaxFileSize() {
        $value = $this->getSetting('verificationMaxFileSize');
        return $value ? (int)$value : 5;
    }

    /**
     * 检查是否自动拒绝未验证账户
     * @return bool
     */
    public function isAutoRejectUnverified() {
        return $this->getSetting('autoRejectUnverified') === true;
    }
}
