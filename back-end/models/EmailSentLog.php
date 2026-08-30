<?php
/**
 * Outbound email send attempt log (emailSentLogs).
 */

require_once __DIR__ . '/BaseModel.php';

class EmailSentLog extends BaseModel {
    protected $table = 'emailSentLogs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'sender',
        'recipient',
        'subject',
        'content',
        'provider',
        'status',
        'errorMessage',
        'stackTrace',
        'relatedType',
        'relatedId',
        'meta',
        'createdAt',
    ];

    public function getTableName() {
        return $this->table;
    }
}
