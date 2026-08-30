<?php
/**
 * Deposit Note Model
 * 对应表: depositNotes
 */

require_once __DIR__ . '/BaseModel.php';

class DepositNote extends BaseModel {
    protected $table = 'depositNotes';
    protected $primaryKey = 'id';
    protected $fillable = [
        'depositId',
        'noteContent',
        'createdBy'
    ];

    /**
     * 获取deposit的所有备注
     */
    public function getDepositNotes($depositId) {
        $sql = "SELECT dn.*, au.fullName as createdByName
                FROM {$this->table} dn
                LEFT JOIN adminUsers au ON dn.createdBy = au.id
                WHERE dn.depositId = :depositId
                ORDER BY dn.createdAt DESC";

        return $this->query($sql, ['depositId' => $depositId]);
    }

    /**
     * 添加备注
     */
    public function addNote($depositId, $noteContent, $createdBy) {
        return $this->create([
            'depositId' => $depositId,
            'noteContent' => $noteContent,
            'createdBy' => $createdBy
        ]);
    }
}
