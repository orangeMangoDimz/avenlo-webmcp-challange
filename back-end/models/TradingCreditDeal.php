<?php
/**
 * TradingCreditDeal Model
 *
 * 用于操作 trading_credit_deals 表，存储交易账户的赠金/信用流水。
 *
 * external_id 用 "{provider}_{ticket}"（例如 mt5_28950）做幂等键，
 * 同步任务对同一条 deal 反复触发不会重复写入。
 */

require_once __DIR__ . '/BaseModel.php';

class TradingCreditDeal extends BaseModel {
    protected $table = 'trading_credit_deals';
    protected $primaryKey = 'id';
    protected $fillable = [
        'trading_account_id',
        'provider_key',
        'external_id',
        'action',
        'direction',
        'amount',
        'comment',
        'deal_time',
    ];

    // direction 字段取值
    const DIRECTION_IN = 1;   // 发放（credit in / 入）
    const DIRECTION_OUT = 2;  // 撤回（credit out / 出）

    /**
     * 按 external_id 做幂等插入：已存在则跳过，靠 uk_external_id 唯一索引保证。
     *
     * @param array $data {
     *   trading_account_id, provider_key, external_id, action,
     *   direction(1=in/2=out), amount(正数),
     *   comment(可空), deal_time(datetime 字符串)
     * }
     * @return bool true=成功（不区分新插入 / 已存在）
     */
    public function insertIgnore(array $data): bool {
        $sql = "INSERT IGNORE INTO `{$this->table}`
                (`trading_account_id`, `provider_key`, `external_id`,
                 `action`, `direction`, `amount`, `comment`, `deal_time`)
                VALUES
                (:trading_account_id, :provider_key, :external_id,
                 :action, :direction, :amount, :comment, :deal_time)";
        try {
            $this->db->query($sql, [
                'trading_account_id' => (int)($data['trading_account_id'] ?? 0),
                'provider_key' => $data['provider_key'] ?? 'mt5',
                'external_id' => $data['external_id'] ?? '',
                'action' => $data['action'] ?? 'credit',
                'direction' => (int)($data['direction'] ?? self::DIRECTION_IN),
                'amount' => $data['amount'] ?? 0,
                'comment' => $data['comment'] ?? null,
                'deal_time' => $data['deal_time'] ?? date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Insert trading_credit_deals failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 按 tradingAccountId 拉取流水（倒序）。
     *
     * @param int $tradingAccountId
     * @param int $limit
     * @return array
     */
    public function listByTradingAccount(int $tradingAccountId, int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $sql = "SELECT * FROM `{$this->table}`
                WHERE `trading_account_id` = :id
                ORDER BY `deal_time` DESC, `id` DESC
                LIMIT {$limit}";
        try {
            $rows = $this->db->fetchAll($sql, ['id' => $tradingAccountId]);
            return is_array($rows) ? $rows : [];
        } catch (Exception $e) {
            error_log('List trading_credit_deals failed: ' . $e->getMessage());
            return [];
        }
    }
}
