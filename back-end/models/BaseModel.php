<?php
/**
 * 基础Model类
 * 所有Model继承此类
 */

require_once __DIR__ . '/../utils/Database.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = ['passwordHash', 'twoFactorSecret'];
    private const RANDOM_ALPHANUMERIC_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 查找所有记录
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    // NULL 值需要使用 IS NULL
                    $where[] = "{$key} IS NULL";
                } else {
                    $where[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $results = $this->db->fetchAll($sql, $params);
        return $this->hideFields($results);
    }

    /**
     * 根据ID查找
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $result = $this->db->fetchOne($sql, ['id' => $id]);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * 根据条件查找单条
     */
    public function findOne($conditions) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    // NULL 值需要使用 IS NULL
                    $where[] = "{$key} IS NULL";
                } else {
                    $where[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " LIMIT 1";
        $result = $this->db->fetchOne($sql, $params);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * 创建记录
     */
    public function create($data) {
        $filteredData = $this->filterFillable($data);
        return $this->db->insert($this->table, $filteredData);
    }

    /**
     * 更新记录
     */
    public function update($id, $data) {
        $filteredData = $this->filterFillable($data);
        return $this->db->update(
            $this->table,
            $filteredData,
            "{$this->primaryKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * 删除记录
     */
    public function delete($id) {
        return $this->db->delete(
            $this->table,
            "{$this->primaryKey} = :id",
            ['id' => $id]
        );
    }

    /**
     * 软删除
     */
    public function softDelete($id) {
        return $this->update($id, ['deletedAt' => date('Y-m-d H:i:s')]);
    }

    /**
     * 统计记录数
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    // NULL 值需要使用 IS NULL
                    $where[] = "{$key} IS NULL";
                } else {
                    $where[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result ? (int)$result['count'] : 0;
    }

    /**
     * 分页查询
     */
    public function paginate($page = 1, $perPage = 10, $conditions = [], $orderBy = null) {
        $offset = ($page - 1) * $perPage;
        $items = $this->findAll($conditions, $orderBy, $perPage, $offset);
        $total = $this->count($conditions);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * 过滤可填充字段
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * 生成指定长度的随机字母数字串
     */
    protected function generateRandomAlphaNumeric($length) {
        $length = (int)$length;
        if ($length < 1) {
            $length = 1;
        }

        $characters = self::RANDOM_ALPHANUMERIC_CHARACTERS;
        $maxIndex = strlen($characters) - 1;
        $random = '';

        for ($i = 0; $i < $length; $i++) {
            $random .= $characters[random_int(0, $maxIndex)];
        }

        return $random;
    }

    /**
     * 隐藏敏感字段
     */
    protected function hideFields($data) {
        if (empty($data)) {
            return $data;
        }

        // 单条记录
        if (isset($data[$this->primaryKey])) {
            foreach ($this->hidden as $field) {
                unset($data[$field]);
            }
            $data = $this->normalizeDataTypes($data);
            return $data;
        }

        // 多条记录
        if (is_array($data)) {
            foreach ($data as &$record) {
                foreach ($this->hidden as $field) {
                    unset($record[$field]);
                }
                $record = $this->normalizeDataTypes($record);
            }
            unset($record);
        }

        return $data;
    }

    /**
     * 规范化数据类型
     * 处理 PDO::ATTR_EMULATE_PREPARES => true 可能导致的类型问题
     * - tinyint(1) 字段转换为布尔值
     * - 确保数字字段为正确的数字类型
     */
    protected function normalizeDataTypes($data) {
        if (empty($data) || !is_array($data)) {
            return $data;
        }

        // 获取需要特殊处理的字段配置（子类可以重写此方法）
        $booleanFields = $this->getBooleanFields();
        $numericFields = $this->getNumericFields();

        foreach ($data as $key => &$value) {
            // 跳过 NULL 值
            if ($value === null) {
                continue;
            }

            // 处理布尔字段
            if (in_array($key, $booleanFields) || $this->isBooleanFieldName($key)) {
                // 将 tinyint(1) 值（可能是字符串 "0"/"1" 或整数 0/1）转换为布尔值
                $value = (bool)(int)$value;
                continue;
            }

            // 处理数字字段（如果明确指定）
            if (in_array($key, $numericFields) && is_numeric($value)) {
                // 如果包含小数点，转换为 float，否则转换为 int
                if (strpos((string)$value, '.') !== false) {
                    $value = (float)$value;
                } else {
                    $value = (int)$value;
                }
                continue;
            }
        }
        unset($value);

        return $data;
    }

    /**
     * 获取布尔字段列表（子类可重写此方法指定特定字段）
     */
    protected function getBooleanFields() {
        return [];
    }

    /**
     * 获取数字字段列表（子类可重写此方法指定特定字段）
     */
    protected function getNumericFields() {
        return [];
    }

    /**
     * 根据字段名判断是否为布尔字段
     * 通过常见的命名模式自动识别
     */
    protected function isBooleanFieldName($fieldName) {
        // 常见的布尔字段命名模式
        $booleanPatterns = [
            '/^is[A-Z]/',          // isEnabled, isActive, etc.
            '/^has[A-Z]/',         // hasPermission, hasAccess, etc.
            '/^can[A-Z]/',         // canEdit, canDelete, etc.
            '/^should[A-Z]/',      // shouldNotify, etc.
            '/^will[A-Z]/',        // willExecute, etc.
            '/^was[A-Z]/',         // wasAutoApproved, etc.
            '/^must[A-Z]/',        // mustChangePassword, etc.
            '/^require[A-Z]/',     // requireSignature, etc.
            '/^allow[A-Z]/',       // allowResend, allowChangePin, etc.
            '/^show[A-Z]/',        // showIcon, showActionButton, etc.
            '/^enable[A-Z]/',      // enabledMark, etc.
            '/^disable[A-Z]/',     // disabled, etc.
            '/_enable$/',          // buy_enable, sell_enable, etc.
            '/_enabled$/',         // is_enabled, etc.
            '/_disable$/',         // auto_disable, etc.
            '/_disabled$/',        // is_disabled, etc.
            '/^verified$/',        // emailVerified, isVerified
            '/^active$/',          // isActive (可能直接叫 active)
            '/^enabled$/',         // isEnabled (可能直接叫 enabled)
            '/^locked$/',          // isLocked (可能直接叫 locked)
            '/^read$/',            // isRead (可能直接叫 read)
            '/^used$/',            // used
            '/^required$/',        // isRequired (可能直接叫 required)
            '/^default$/',         // isDefault (可能直接叫 default)
            '/^builtin$/',         // isBuiltin (可能直接叫 builtin)
            '/^system[A-Z]/',      // systemTag, systemRole, etc.
            '/^public$/',          // isPublic (可能直接叫 public)
            '/^editable$/',        // isEditable (可能直接叫 editable)
            '/^granted$/',         // isGranted (可能直接叫 granted)
            '/^completed$/',       // isCompleted (可能直接叫 completed)
            '/^dismissible$/',     // isDismissible (可能直接叫 dismissible)
            '/^expanded$/',        // isExpanded (可能直接叫 expanded)
            '/^important$/',       // isImportant (可能直接叫 important)
            '/^acknowledged$/',    // acknowledged
            '/^recommended$/',     // isRecommended (可能直接叫 recommended)
            // 注意：不要加 '/^thirdParty/'，会把 thirdPartyProvider 这种字符串字段误转成布尔。
            // isThirdPartyEnabled 已经被前面的 /^is[A-Z]/ 匹配到了。
            '/^autoApprove/',      // isAutoApproveEnabled, etc.
            '/^attachment/',       // attachmentRequired, etc.
            '/^chargeToClient$/',  // chargeToClient
            '/^depositEnabled$/',  // isDepositEnabled (可能直接叫)
            '/^withdrawalEnabled$/', // isWithdrawalEnabled (可能直接叫)
            '/^deleteMark$/',      // deleteMark
            '/^readOnly$/',        // isReadOnly (可能直接叫)
        ];

        foreach ($booleanPatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 执行原始SQL查询
     */
    public function query($sql, $params = []) {
        $results = $this->db->fetchAll($sql, $params);
        // 对每条记录应用数据类型规范化
        if (is_array($results) && !empty($results)) {
            foreach ($results as &$record) {
                $record = $this->normalizeDataTypes($record);
            }
            unset($record);
        }
        return $results;
    }

    /**
     * 执行原始SQL（单条）
     */
    public function queryOne($sql, $params = []) {
        $result = $this->db->fetchOne($sql, $params);
        return $result ? $this->normalizeDataTypes($result) : null;
    }
}
