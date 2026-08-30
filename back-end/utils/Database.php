<?php
/**
 * 数据库连接类
 * 单例模式管理PDO连接
 */

class Database {
    private static $instance = null;
    private $connection = null;
    private $config = null;
    private $transactionLevel = 0;

    /**
     * 私有构造函数，防止直接实例化
     */
    private function __construct() {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }

    /**
     * 建立数据库连接
     */
    private function connect() {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * 获取数据库实例（单例模式）
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 获取PDO连接
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * 获取当前事务状态
     */
    public function inTransaction() {
        $this->syncTransactionLevel();
        return $this->transactionLevel > 0;
    }

    /**
     * 执行查询
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    /**
     * 获取单行数据
     * 自动规范化数据类型（处理 tinyint(1) 布尔字段等）
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();

        // 规范化数据类型
        if ($result !== false && is_array($result)) {
            $result = $this->normalizeDataTypes($result);
        }

        return $result;
    }

    /**
     * 获取多行数据
     * 自动规范化数据类型（处理 tinyint(1) 布尔字段等）
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $results = $stmt->fetchAll();

        // 规范化每条记录的数据类型
        if (!empty($results) && is_array($results)) {
            foreach ($results as &$record) {
                if (is_array($record)) {
                    $record = $this->normalizeDataTypes($record);
                }
            }
            unset($record);
        }

        return $results;
    }

    /**
     * 规范化数据类型
     * 处理 PDO::ATTR_EMULATE_PREPARES => true 可能导致的类型问题
     * - tinyint(1) 字段转换为布尔值
     *
     * @param array $data 单条记录
     * @return array 规范化后的数据
     */
    private function normalizeDataTypes($data) {
        if (empty($data) || !is_array($data)) {
            return $data;
        }

        foreach ($data as $key => &$value) {
            // 跳过 NULL 值
            if ($value === null) {
                continue;
            }

            // 自动识别并转换布尔字段
            if ($this->isBooleanFieldName($key)) {
                // 将 tinyint(1) 值（可能是字符串 "0"/"1" 或整数 0/1）转换为布尔值
                $value = (bool)(int)$value;
                continue;
            }
        }
        unset($value);

        return $data;
    }

    /**
     * 根据字段名判断是否为布尔字段
     * 通过常见的命名模式自动识别
     */
    private function isBooleanFieldName($fieldName) {
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
     * 插入数据
     */
    public function insert($table, $data) {
        // 检查空数据（保持兼容性：如果数据为空，原方法也会报错，但错误类型不同）
        if (empty($data)) {
            throw new Exception("Cannot insert empty data");
        }

        $columns = array_keys($data);
        $columnList = implode(', ', $columns);
        $placeholderList = ':' . implode(', :', $columns);

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholderList})";

        // 处理参数：确保数据类型正确
        // 注意：保持原有行为，不强制转换空字符串为null，除非明确需要
        $params = [];
        foreach ($data as $key => $value) {
            // 保持原有值，不做类型转换，避免破坏现有代码
            // 如果确实需要将空字符串转为null，应该在调用前处理
            if ($value === null) {
                $params[$key] = null;
            } else {
                $params[$key] = $value;
            }
        }

        $this->query($sql, $params);

        return $this->connection->lastInsertId();
    }

    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setString = implode(', ', $set);

        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        $params = array_merge($data, $whereParams);

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 开始事务
     */
    public function beginTransaction() {
        $this->syncTransactionLevel();

        if ($this->transactionLevel === 0) {
            $started = $this->connection->beginTransaction();
            if ($started) {
                $this->transactionLevel = 1;
            }

            return $started;
        }

        $this->connection->exec('SAVEPOINT ' . $this->getSavepointName($this->transactionLevel + 1));
        $this->transactionLevel++;

        return true;
    }

    /**
     * 提交事务
     */
    public function commit() {
        $this->syncTransactionLevel();

        if ($this->transactionLevel === 0) {
            throw new Exception('There is no active transaction');
        }

        if ($this->transactionLevel === 1) {
            $committed = $this->connection->commit();
            if ($committed) {
                $this->transactionLevel = 0;
            }

            return $committed;
        }

        $this->connection->exec('RELEASE SAVEPOINT ' . $this->getSavepointName($this->transactionLevel));
        $this->transactionLevel--;

        return true;
    }

    /**
     * 回滚事务
     */
    public function rollback() {
        $this->syncTransactionLevel();

        if ($this->transactionLevel === 0) {
            throw new Exception('There is no active transaction');
        }

        if ($this->transactionLevel === 1) {
            $rolledBack = $this->connection->rollBack();
            if ($rolledBack) {
                $this->transactionLevel = 0;
            }

            return $rolledBack;
        }

        $savepoint = $this->getSavepointName($this->transactionLevel);
        $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
        $this->transactionLevel--;

        return true;
    }

    /**
     * 同步包装器事务层级与 PDO 真实状态
     */
    private function syncTransactionLevel() {
        $pdoInTransaction = $this->connection->inTransaction();

        if (!$pdoInTransaction) {
            $this->transactionLevel = 0;
            return;
        }

        if ($this->transactionLevel === 0) {
            $this->transactionLevel = 1;
        }
    }

    /**
     * 生成 savepoint 名称
     */
    private function getSavepointName($level) {
        return 'crm_sp_' . (int)$level;
    }

    /**
     * 防止克隆
     */
    private function __clone() {}

    /**
     * 防止反序列化
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
