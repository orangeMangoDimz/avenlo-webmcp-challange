<?php
/**
 * 后台操作日志模块开关配置
 * 表: adminOperationLogModuleSettings
 */

require_once __DIR__ . '/BaseModel.php';

class AdminOperationLogModuleSetting extends BaseModel {
    protected $table = 'adminOperationLogModuleSettings';
    protected $primaryKey = 'id';
    protected $fillable = [
        'moduleNameZh',
        'moduleNameEn',
        'modelKey',
        'lastStartStopAt',
        'status',
        'isVisible',
        'sortOrder',
        'updatedBy',
    ];

    public const STATUS_STOPPED = 0;
    public const STATUS_RUNNING = 1;
    public const VISIBLE_NO = 0;
    public const VISIBLE_YES = 1;

    /**
     * 按 modelKey 查询
     */
    public function findByModelKey($modelKey) {
        return $this->findOne(['modelKey' => $modelKey]);
    }

    /**
     * 判断某模块是否开启操作日志记录
     */
    public function isLoggingEnabled($modelKey) {
        $row = $this->findByModelKey($modelKey);
        if (!$row) {
            return false;
        }
        if ((int)($row['isVisible'] ?? 0) !== self::VISIBLE_YES) {
            return false;
        }
        return (int)($row['status'] ?? 0) === self::STATUS_RUNNING;
    }

    /**
     * 列表默认条件：仅显示 isVisible=1 的模块
     */
    public function getVisibleListConditions() {
        return ['isVisible' => self::VISIBLE_YES];
    }

    /**
     * 日志报表 Tab：可见模块（含 log_report，对应侧边栏报表分组下各页面）
     *
     * @return array<int,array<string,mixed>>
     */
    public function findReportTabs() {
        $sql = "SELECT id, moduleNameZh, moduleNameEn, modelKey, status, sortOrder
                FROM {$this->table}
                WHERE isVisible = :visible
                ORDER BY sortOrder ASC, id ASC";
        return $this->db->fetchAll($sql, [
            'visible' => self::VISIBLE_YES,
        ]);
    }

    /**
     * 按 id 列表查询（保持传入顺序无关，由调用方排序）
     *
     * @param int[] $ids
     * @return array<int,array<string,mixed>>
     */
    public function findByIds(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE id IN ({$placeholders})";
        return $this->db->fetchAll($sql, $ids);
    }

    /**
     * 更新单条启停状态
     */
    public function updateStatus($id, $status, $adminId = null) {
        $id = (int)$id;
        $status = (int)$status;
        if ($id <= 0) {
            return false;
        }
        if (!in_array($status, [self::STATUS_STOPPED, self::STATUS_RUNNING], true)) {
            return false;
        }

        $data = [
            'status' => $status,
            'lastStartStopAt' => date('Y-m-d H:i:s'),
            'updatedBy' => $adminId,
        ];

        return $this->update($id, $data);
    }

    /**
     * 批量更新启停状态
     * @return int 影响行数
     */
    public function updateStatusByIds(array $ids, $status, $adminId = null) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return 0;
        }

        $status = (int)$status;
        if (!in_array($status, [self::STATUS_STOPPED, self::STATUS_RUNNING], true)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $now = date('Y-m-d H:i:s');
        $params = array_merge(
            [$status, $now, $adminId],
            $ids
        );

        $sql = "UPDATE {$this->table}
                SET status = ?, lastStartStopAt = ?, updatedBy = ?, updatedAt = NOW()
                WHERE id IN ({$placeholders})";

        $stmt = $this->db->query($sql, $params);
        return (int)$stmt->rowCount();
    }
}
