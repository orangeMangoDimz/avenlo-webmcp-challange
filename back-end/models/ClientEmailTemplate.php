<?php
/**
 * 客户邮件模板模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientEmailTemplate extends BaseModel {
    protected $table = 'clientEmailTemplates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'templateKey',
        'name',
        'subject',
        'body',
        'isActive',
        'createdAt',
        'updatedAt'
    ];

    /**
     * 获取启用状态的模板
     */
    public function getActiveTemplates() {
        $sql = "SELECT id, templateKey, name, subject, body
                FROM {$this->table}
                WHERE isActive = 1
                ORDER BY name ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 根据模板键查找模板
     */
    public function findByKey($key) {
        $sql = "SELECT * FROM {$this->table}
                WHERE templateKey = :templateKey
                LIMIT 1";

        return $this->db->fetchOne($sql, ['templateKey' => $key]);
    }
}
