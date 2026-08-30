<?php
/**
 * Client Payment Account Model
 * 对应表: client_payment_accounts
 * 用于管理客户端支付账户信息（用于出金转账）
 * 支持软删除：deletedAt 为 NULL 表示未删除，有值表示删除时间；所有查询默认排除已删除记录。
 */

require_once __DIR__ . '/BaseModel.php';

class ClientPaymentAccount extends BaseModel {
    protected $table = 'client_payment_accounts';
    protected $primaryKey = 'id';
    protected $fillable = [
        'userId',
        'legalName',
        'bsb',
        'accountNumber',
        'isDefault',
        'deletedAt'
    ];

    /** 软删除条件：仅查未删除 */
    const NOT_DELETED_CONDITION = '(deletedAt IS NULL OR deletedAt = 0)';

    /**
     * 软删除条件 SQL 片段（用于拼接 WHERE）
     */
    protected function notDeletedCondition() {
        return self::NOT_DELETED_CONDITION;
    }

    /**
     * 根据ID查找（仅未删除）
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND " . $this->notDeletedCondition() . " LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * 根据条件查找单条（仅未删除）
     */
    public function findOne($conditions) {
        $conditions['deletedAt'] = null;
        return parent::findOne($conditions);
    }

    /**
     * 查找所有（仅未删除）；conditions 可含 deletedAt 以查已删除
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        if (!array_key_exists('deletedAt', $conditions)) {
            $conditions['deletedAt'] = null;
        }
        return parent::findAll($conditions, $orderBy, $limit, $offset);
    }

    /**
     * 获取用户的所有支付账户（仅未删除）
     * @param int $userId 用户ID
     * @return array
     */
    public function getByUserId($userId) {
        $c = $this->notDeletedCondition();
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? AND {$c} ORDER BY isDefault DESC, createdAt DESC";
        return $this->query($sql, [$userId]);
    }

    /**
     * 获取用户的默认支付账户（仅未删除）
     * @param int $userId 用户ID
     * @return array|null
     */
    public function getDefaultByUserId($userId) {
        $c = $this->notDeletedCondition();
        $sql = "SELECT * FROM {$this->table} WHERE userId = ? AND isDefault = 1 AND {$c} LIMIT 1";
        $result = $this->query($sql, [$userId]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * 设置默认账户（仅操作未删除记录）
     * 当设置某个账户为默认时，自动取消该用户其他未删除账户的默认状态
     * @param int $id 账户ID
     * @param int $userId 用户ID
     * @return bool
     */
    public function setDefault($id, $userId) {
        $db = Database::getInstance();
        $c = $this->notDeletedCondition();
        $db->beginTransaction();
        try {
            $sql1 = "UPDATE {$this->table} SET isDefault = 0 WHERE userId = ? AND {$c}";
            $db->query($sql1, [$userId]);
            $sql2 = "UPDATE {$this->table} SET isDefault = 1 WHERE id = ? AND userId = ? AND {$c}";
            $db->query($sql2, [$id, $userId]);
            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 创建账户
     * @param array $data
     * @return int 新创建的账户ID
     */
    public function createAccount($data) {
        if (!empty($data['isDefault'])) {
            $db = Database::getInstance();
            $c = $this->notDeletedCondition();
            $sql = "UPDATE {$this->table} SET isDefault = 0 WHERE userId = ? AND {$c}";
            $db->query($sql, [$data['userId']]);
        }
        return $this->create($data);
    }

    /**
     * 更新账户（仅未删除记录可更新）
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateAccount($id, $data) {
        if (!empty($data['isDefault'])) {
            $existing = $this->findById($id);
            if ($existing && $existing['userId']) {
                $db = Database::getInstance();
                $c = $this->notDeletedCondition();
                $sql = "UPDATE {$this->table} SET isDefault = 0 WHERE userId = ? AND id != ? AND {$c}";
                $db->query($sql, [$existing['userId'], $id]);
            }
        }
        return $this->update($id, $data);
    }

    /**
     * 更新记录（仅未删除记录可被更新）
     */
    public function update($id, $data) {
        $filteredData = $this->filterFillable($data);
        $c = $this->notDeletedCondition();
        $set = [];
        $params = ['id' => $id];
        foreach (array_keys($filteredData) as $key) {
            $set[] = "{$key} = :{$key}";
            $params[$key] = $filteredData[$key];
        }
        $setStr = implode(', ', $set);
        $sql = "UPDATE {$this->table} SET {$setStr} WHERE {$this->primaryKey} = :id AND {$c}";
        $this->db->query($sql, $params);
        return true;
    }

    /**
     * 软删除：将 deletedAt 设为当前时间，不物理删除
     * @param int $id
     * @return bool
     */
    public function softDelete($id) {
        return $this->update($id, ['deletedAt' => date('Y-m-d H:i:s')]);
    }

    /**
     * 验证 BSB 格式（6位数字）
     * @param string $bsb
     * @return bool
     */
    public static function validateBSB($bsb) {
        return preg_match('/^\d{6}$/', $bsb);
    }

    /**
     * 验证账户号码格式（数字字符串）
     * @param string $accountNumber
     * @return bool
     */
    public static function validateAccountNumber($accountNumber) {
        return preg_match('/^\d+$/', $accountNumber) && strlen($accountNumber) >= 6 && strlen($accountNumber) <= 50;
    }
}
