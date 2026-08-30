<?php
/**
 * External KYC Template Model
 * 对应表: externalKycTemplates
 * 第三方 KYC 平台上的核验等级/模板的本地缓存
 */

require_once __DIR__ . '/BaseModel.php';

class ExternalKycTemplate extends BaseModel {
    protected $table = 'externalKycTemplates';
    protected $primaryKey = 'id';
    protected $fillable = [
        'gatewayId',
        'externalLevelId',
        'externalLevelName',
        'displayName',
        'description',
        'applicantType',
        'docTypesSummary',
        'externalUpdatedAt',
        'isActive',
        'lastSyncedAt',
        'deletedAt',
    ];

    const NOT_DELETED_CONDITION = 'deletedAt IS NULL';

    protected function notDeletedCondition() {
        return self::NOT_DELETED_CONDITION;
    }

    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND {$this->notDeletedCondition()} LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->hideFields($result) : null;
    }

    public function findOne($conditions) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findOne($conditions);
    }

    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findAll($conditions, $orderBy, $limit, $offset);
    }

    public function update($id, $data) {
        $filteredData = $this->filterFillable($data);
        if (empty($filteredData)) {
            return false;
        }

        return $this->db->update(
            $this->table,
            $filteredData,
            "{$this->primaryKey} = :id AND {$this->notDeletedCondition()}",
            ['id' => $id]
        );
    }

    /**
     * Sync 时按 (gatewayId, externalLevelId) 做 upsert。
     * 目前只跑 Sumsub，假定 externalLevelId 一定存在（Sumsub 全局唯一）。
     * 以后接没有 id 的平台再扩。
     *
     * 关键点：
     *   - 查找时**不过滤 deletedAt**，软删过的等级如果远端又冒出来要"复活"而非另插一行
     *   - 用 $this->db->update 直接更新，绕开 model 自带的 "deletedAt IS NULL" 限制
     */
    public function upsertFromSync($gatewayId, $externalLevelId, $data) {
        $sql = "SELECT * FROM {$this->table}
                WHERE gatewayId = :gatewayId AND externalLevelId = :externalLevelId
                LIMIT 1";
        $existing = $this->db->fetchOne($sql, [
            'gatewayId' => $gatewayId,
            'externalLevelId' => $externalLevelId,
        ]);

        $payload = array_merge($data, [
            'gatewayId' => $gatewayId,
            'externalLevelId' => $externalLevelId,
            'lastSyncedAt' => date('Y-m-d H:i:s'),
            // 远端又出现了 → 自动取消软删
            'deletedAt' => null,
        ]);

        if ($existing) {
            $filtered = $this->filterFillable($payload);
            $this->db->update($this->table, $filtered, "{$this->primaryKey} = :id", ['id' => $existing['id']]);
            $action = !empty($existing['deletedAt']) ? 'restored' : 'updated';
            return ['id' => (int)$existing['id'], 'action' => $action];
        }

        $newId = $this->create($payload);
        return ['id' => (int)$newId, 'action' => 'created'];
    }

    /**
     * 把指定 gateway 下"本次 sync 没看到的"行批量软删。
     * Sumsub 那边删掉的等级在我们这边会被标软删，
     * 但行不会真消失，从而保护 kycTemplates.externalTemplateId 这条 FK 不会失效。
     *
     * @param int   $gatewayId 网关 ID
     * @param int[] $seenIds   本次 sync 实际命中的本地 row id 列表
     * @return int 受影响行数
     */
    public function softDeleteMissing($gatewayId, array $seenIds) {
        // 如果一条都没同步到，整个 gateway 下未软删的都标软删
        if (empty($seenIds)) {
            $sql = "UPDATE {$this->table}
                    SET deletedAt = NOW()
                    WHERE gatewayId = :gatewayId AND deletedAt IS NULL";
            $stmt = $this->db->query($sql, ['gatewayId' => $gatewayId]);
            return $stmt ? $stmt->rowCount() : 0;
        }

        // 构造命名占位符 :id0, :id1, ...
        $params = ['gatewayId' => $gatewayId];
        $placeholders = [];
        foreach (array_values($seenIds) as $i => $id) {
            $key = "id{$i}";
            $params[$key] = (int)$id;
            $placeholders[] = ":{$key}";
        }
        $sql = "UPDATE {$this->table}
                SET deletedAt = NOW()
                WHERE gatewayId = :gatewayId
                  AND id NOT IN (" . implode(',', $placeholders) . ")
                  AND deletedAt IS NULL";
        $stmt = $this->db->query($sql, $params);
        return $stmt ? $stmt->rowCount() : 0;
    }
}
