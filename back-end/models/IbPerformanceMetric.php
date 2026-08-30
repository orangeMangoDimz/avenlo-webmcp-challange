<?php
/**
 * IB Performance Metric 模型
 * 对应表：ibPerformanceMetrics
 */

require_once __DIR__ . '/BaseModel.php';

class IbPerformanceMetric extends BaseModel {
    protected $table = 'ibPerformanceMetrics';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ibPartnerId', 'metricPeriod', 'totalClients', 'activeClients',
        'newClients', 'totalTradingVolume', 'numberOfTrades',
        'commissionEarned', 'averageClientValue', 'clientRetentionRate'
    ];

    /**
     * 获取IB的性能指标历史
     */
    public function getMetricsHistory($ibPartnerId, $months = 12) {
        $sql = "SELECT * FROM {$this->table}
                WHERE ibPartnerId = :ibPartnerId
                ORDER BY metricPeriod DESC
                LIMIT {$months}";

        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }
}
