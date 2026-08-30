<?php
/**
 * IB Custom Security 模型
 * 对应表：ibCustomSecurities
 */

require_once __DIR__ . '/BaseModel.php';

class IbCustomSecurity extends BaseModel {
    protected $table = 'ibCustomSecurities';
    protected $primaryKey = 'id';

    protected $fillable = [
        'securityName', 'securityDescription', 'trading_id', 'trading_platforms_key',
        'Description', 'EnabledMark', 'createdBy'
    ];
}
