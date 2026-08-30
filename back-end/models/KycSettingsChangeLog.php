<?php
/**
 * KYC Settings Change Log Model
 * 对应表: kycSettingsChangeLog
 */

require_once __DIR__ . '/BaseModel.php';

class KycSettingsChangeLog extends BaseModel {
    protected $table = 'kycSettingsChangeLog';
    protected $primaryKey = 'id';

    protected $fillable = [
        'settingType',
        'settingId',
        'fieldName',
        'oldValue',
        'newValue',
        'changeReason',
        'changedBy',
        'ipAddress',
        'userAgent'
    ];

    /**
     * 记录设置更改
     */
    public function logChange($settingType, $settingId, $fieldName, $oldValue, $newValue, $changedBy, $changeReason = null) {
        $data = [
            'settingType' => $settingType,
            'settingId' => $settingId,
            'fieldName' => $fieldName,
            'oldValue' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
            'newValue' => is_array($newValue) ? json_encode($newValue) : $newValue,
            'changeReason' => $changeReason,
            'changedBy' => $changedBy,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        return $this->create($data);
    }

    /**
     * 获取特定设置的更改历史
     */
    public function getChangeHistory($settingType, $settingId, $limit = 50) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT cl.*,
                       u.fullName as changedByName,
                       u.email as changedByEmail
                FROM {$this->table} cl
                LEFT JOIN adminUsers u ON cl.changedBy = u.id
                WHERE cl.settingType = :settingType
                  AND cl.settingId = :settingId
                ORDER BY cl.changedAt DESC
                LIMIT {$limit}";

        return $this->query($sql, [
            'settingType' => $settingType,
            'settingId' => $settingId
        ]);
    }

    /**
     * 获取最近的更改记录
     */
    public function getRecentChanges($limit = 100) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT cl.*,
                       u.fullName as changedByName,
                       u.email as changedByEmail
                FROM {$this->table} cl
                LEFT JOIN adminUsers u ON cl.changedBy = u.id
                ORDER BY cl.changedAt DESC
                LIMIT {$limit}";

        return $this->query($sql, []);
    }

    /**
     * 获取特定管理员的更改记录
     */
    public function getChangesByAdmin($adminId, $limit = 50) {
        $limit = max(1, (int)$limit); // 确保是正整数
        $sql = "SELECT cl.*,
                       u.fullName as changedByName
                FROM {$this->table} cl
                LEFT JOIN adminUsers u ON cl.changedBy = u.id
                WHERE cl.changedBy = :adminId
                ORDER BY cl.changedAt DESC
                LIMIT {$limit}";

        return $this->query($sql, [
            'adminId' => $adminId
        ]);
    }

    /**
     * 批量记录更改
     */
    public function logBulkChanges($changes, $changedBy) {
        try {
            $this->db->beginTransaction();

            foreach ($changes as $change) {
                $this->logChange(
                    $change['settingType'],
                    $change['settingId'],
                    $change['fieldName'],
                    $change['oldValue'],
                    $change['newValue'],
                    $changedBy,
                    $change['changeReason'] ?? null
                );
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
