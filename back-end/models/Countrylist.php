<?php
/**
 * 国家模型
 */

require_once __DIR__ . '/BaseModel.php';

class Countrylist extends BaseModel {
    protected $table = 'countryList';
    protected $primaryKey = 'id';

    protected $fillable = [
        'code',
        'name',
        'phoneCode',
        'isActive',
        'displayOrder',
        'createdAt',
        'updatedAt'
    ];

    /**
     * 获取所有启用的国家列表
     */
    public function getActiveCountries($includeAll = true) {
        $sql = "SELECT code, name, phoneCode, isActive, displayOrder
                FROM {$this->table}
                WHERE isActive = 1";

        $params = [];
        if (!$includeAll) {
            $sql .= " AND code <> :allCode";
            $params['allCode'] = 'ALL';
        }

        $sql .= " ORDER BY displayOrder ASC, name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * 按启用状态获取国家 code 列表（排除 ALL 项）
     * - true  → 启用国家
     * - false → 禁用国家（App 注册下拉用作 blocked countries 屏蔽）
     */
    public function getCountryCodesByActive(bool $isActive) {
        $sql = "SELECT code
                FROM {$this->table}
                WHERE isActive = :isActive AND code <> 'ALL'
                ORDER BY displayOrder ASC, name ASC";

        $rows = $this->db->fetchAll($sql, ['isActive' => $isActive ? 1 : 0]);
        return array_column($rows, 'code');
    }

    /**
     * 获取所有国家列表
     */
    public function getAllCountries() {
        $sql = "SELECT id, code, name, phoneCode, isActive, displayOrder
                FROM {$this->table}
                WHERE code <> 'ALL'
                ORDER BY displayOrder ASC, name ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 批量更新国家的启用状态
     * @param array|int $ids - 国家ID数组或单个ID
     * @param bool $isActive - 启用状态
     * @return int - 受影响的行数
     */
    public function updateCountryStatus($ids, bool $isActive) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return 0;
        }

        $placeholders = [];
        $params = ['isActive' => (int)$isActive];
        foreach ($ids as $index => $id) {
            $key = "id{$index}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }

        $sql = "UPDATE {$this->table}
                SET isActive = :isActive, updatedAt = NOW()
                WHERE id IN (" . implode(',', $placeholders) . ")";

        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
}
