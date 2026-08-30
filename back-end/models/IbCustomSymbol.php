<?php
/**
 * IB Custom Symbol 模型
 * 对应表：ibCustomSymbols
 */

require_once __DIR__ . '/BaseModel.php';

class IbCustomSymbol extends BaseModel {
    protected $table = 'ibCustomSymbols';
    protected $primaryKey = 'id';

    protected $fillable = [
        'securityId', 'symbolName', 'symbolDescription', 'trading_id', 'trading_platforms_key',
        'Source', 'Sescription', 'Digits', 'Currency', 'MarginCurrency', 'CommissionType',
        'MinLots', 'MaxLots', 'LotsStep', 'ContractSize', 'InitialMargin', 'Maintenance',
        'Hedged', 'TickSize', 'TickPrice', 'SpreadByDefault', 'LimitStopLevel',
        'Trade', 'Execution', 'EnabledMark', 'createdBy'
    ];
}
