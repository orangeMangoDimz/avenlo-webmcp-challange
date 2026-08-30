<?php
/**
 * Internal Transfer Note Model
 * 对应表: internalTransferNotes
 */

require_once __DIR__ . '/BaseModel.php';

class InternalTransferNote extends BaseModel {
    protected $table = 'internalTransferNotes';
    protected $primaryKey = 'id';
    protected $fillable = [
        'internalTransferId',
        'noteContent',
        'createdBy'
    ];

    /**
     * 获取内部转账的所有备注
     */
    public function getInternalTransferNotes($internalTransferId) {
        $sql = "SELECT itn.*, au.fullName as createdByName
                FROM {$this->table} itn
                LEFT JOIN adminUsers au ON itn.createdBy = au.id
                WHERE itn.internalTransferId = :internalTransferId
                ORDER BY itn.createdAt DESC";

        return $this->query($sql, ['internalTransferId' => $internalTransferId]);
    }

    /**
     * 添加备注
     */
    public function addNote($internalTransferId, $noteContent, $createdBy) {
        return $this->create([
            'internalTransferId' => $internalTransferId,
            'noteContent' => $noteContent,
            'createdBy' => $createdBy
        ]);
    }
}
