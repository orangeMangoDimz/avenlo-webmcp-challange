<?php
/**
 * Trading Platform Model
 * 对应表: tradingPlatforms
 */

require_once __DIR__ . '/BaseModel.php';

class TradingPlatform extends BaseModel {
    protected $table = 'tradingPlatforms';
    protected $primaryKey = 'id';
    protected $fillable = [
        'platformKey',
        'displayName',
        'shortCode',
        'description',
        'features',
        'isEnabled',
        'isRecommended',
        'allowLeverageManagement',
        'accountLimit',
        'passwordMode'
    ];

    /**
     * 获取所有启用的平台
     */
    public function getEnabledPlatforms() {
        return $this->findAll(['isEnabled' => 1], 'displayName ASC');
    }

    /**
     * 根据platformKey查找平台
     */
    public function findByKey($platformKey) {
        return $this->findOne(['platformKey' => $platformKey]);
    }
}
