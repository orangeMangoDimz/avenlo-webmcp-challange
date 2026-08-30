<?php
/**
 * TradingAccountBalance Model
 * 用于操作trading_account_balances表，存储交易账户余额信息
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/Logger.php';

class TradingAccountBalance extends BaseModel {
    protected $table = 'trading_account_balances';
    protected $primaryKey = 'id';
    protected $fillable = [
        'trading_account_id',
        'trading_platforms_key',
        'trading_login',
        'balance',
        'equity',
        'margin',
        'free_margin',
        'margin_level',
        'currency',
        'currency_name',
        'dpl',
        'upl',
        'total_pnl',
        'total',
        'credit',
        'pnl_rate',
        'updated_at'
    ];

    /**
     * 批量插入或更新余额信息
     * 使用ON DUPLICATE KEY UPDATE实现
     *
     * @param array $balances 余额数据数组
     * @return int 影响的行数
     */
    public function batchUpsert($balances) {
        if (empty($balances)) {
            return 0;
        }

        $now = time();
        $values = [];
        $params = [];

        foreach ($balances as $index => $balance) {
            $baseIndex = $index * 17; // 每个余额约17个字段

            $values[] = "(
                :trading_account_id{$index},
                :trading_platforms_key{$index},
                :trading_login{$index},
                :balance{$index},
                :equity{$index},
                :margin{$index},
                :free_margin{$index},
                :margin_level{$index},
                :currency{$index},
                :currency_name{$index},
                :dpl{$index},
                :upl{$index},
                :total_pnl{$index},
                :total{$index},
                :credit{$index},
                :pnl_rate{$index},
                :updated_at{$index}
            )";

            $params["trading_account_id{$index}"] = isset($balance['trading_account_id']) ? (int)$balance['trading_account_id'] : 0;
            $params["trading_platforms_key{$index}"] = $balance['trading_platforms_key'] ?? '';
            $params["trading_login{$index}"] = $balance['trading_login'] ?? '';
            $params["balance{$index}"] = isset($balance['Balance']) ? (float)$balance['Balance'] : (isset($balance['balance']) ? (float)$balance['balance'] : 0.00);
            $params["equity{$index}"] = isset($balance['Equity']) ? (float)$balance['Equity'] : (isset($balance['equity']) ? (float)$balance['equity'] : 0.00);
            $params["margin{$index}"] = isset($balance['Margin']) ? (float)$balance['Margin'] : (isset($balance['margin']) ? (float)$balance['margin'] : 0.00);
            $params["free_margin{$index}"] = isset($balance['FreeMargin']) ? (float)$balance['FreeMargin'] : (isset($balance['free_margin']) ? (float)$balance['free_margin'] : 0.00);
            $params["margin_level{$index}"] = isset($balance['MarginLevel']) ? (float)$balance['MarginLevel'] : (isset($balance['margin_level']) ? (float)$balance['margin_level'] : 0.00);
            $params["currency{$index}"] = $balance['Currency'] ?? $balance['currency'] ?? '';
            $params["currency_name{$index}"] = $balance['CurrencyName'] ?? $balance['currency_name'] ?? '';
            $params["dpl{$index}"] = isset($balance['Dpl']) && $balance['Dpl'] !== '' ? (float)$balance['Dpl'] : (isset($balance['dpl']) ? (float)$balance['dpl'] : 0.00);
            $params["upl{$index}"] = isset($balance['Upl']) && $balance['Upl'] !== '' ? (float)$balance['Upl'] : (isset($balance['upl']) ? (float)$balance['upl'] : 0.00);
            $params["total_pnl{$index}"] = isset($balance['TotalPnl']) && $balance['TotalPnl'] !== '' ? (float)$balance['TotalPnl'] : (isset($balance['total_pnl']) ? (float)$balance['total_pnl'] : 0.00);
            $params["total{$index}"] = isset($balance['Total']) ? (float)$balance['Total'] : (isset($balance['total']) ? (float)$balance['total'] : 0.00);
            $params["credit{$index}"] = isset($balance['Credit']) && $balance['Credit'] !== '' ? (float)$balance['Credit'] : (isset($balance['credit']) ? (float)$balance['credit'] : null);
            $params["pnl_rate{$index}"] = isset($balance['PnlRate']) && $balance['PnlRate'] !== '' ? (float)$balance['PnlRate'] : (isset($balance['pnl_rate']) ? (float)$balance['pnl_rate'] : 0.00);
            $params["updated_at{$index}"] = $now;
        }

        $sql = "INSERT INTO {$this->table} (
            trading_account_id, trading_platforms_key, trading_login,
            balance, equity, margin, free_margin, margin_level,
            currency, currency_name, dpl, upl, total_pnl, total,
            credit, pnl_rate, updated_at
        ) VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE
            balance = VALUES(balance),
            equity = VALUES(equity),
            margin = VALUES(margin),
            free_margin = VALUES(free_margin),
            margin_level = VALUES(margin_level),
            currency = VALUES(currency),
            currency_name = VALUES(currency_name),
            dpl = VALUES(dpl),
            upl = VALUES(upl),
            total_pnl = VALUES(total_pnl),
            total = VALUES(total),
            credit = VALUES(credit),
            pnl_rate = VALUES(pnl_rate),
            updated_at = VALUES(updated_at)";

        try {
            $this->db->query($sql, $params);
            return count($balances);
        } catch (Exception $e) {
            Logger::error('Batch upsert balances failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 根据交易账户ID获取余额信息
     *
     * @param int $tradingAccountId 交易账户ID
     * @param string $platformKey 平台标识（可选）
     * @return array|null
     */
    public function getByTradingAccountId($tradingAccountId, $platformKey = null) {
        $where = ['trading_account_id = :trading_account_id'];
        $params = ['trading_account_id' => $tradingAccountId];

        if ($platformKey !== null) {
            $where[] = 'trading_platforms_key = :platform_key';
            $params['platform_key'] = $platformKey;
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $result = $this->db->fetchOne($sql, $params);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * 根据交易登录账户获取余额信息
     *
     * @param string $tradingLogin 交易账户登录ID
     * @param string $platformKey 平台标识
     * @return array|null
     */
    public function getByTradingLogin($tradingLogin, $platformKey) {
        $sql = "SELECT * FROM {$this->table}
                WHERE trading_login = :trading_login
                AND trading_platforms_key = :platform_key
                LIMIT 1";
        $result = $this->db->fetchOne($sql, [
            'trading_login' => $tradingLogin,
            'platform_key' => $platformKey
        ]);
        return $result ? $this->hideFields($result) : null;
    }
}
