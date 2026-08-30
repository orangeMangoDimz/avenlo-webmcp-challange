<?php
/**
 * IB 品种汇率扩展配置
 * 对应表：ibSymbolExchangeSettings
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/CurrencyExchangeRate.php';

class IbSymbolExchangeSetting extends BaseModel {
    public const EXCHANGE_RATE_DECIMALS = 5;

    protected $table = 'ibSymbolExchangeSettings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'symbolId', 'targetCurrency', 'baseCurrency', 'exchangeRate',
        'remarks', 'syncMode', 'updatedBy', 'deletedAt'
    ];

    /**
     * 页级汇率模式：由全表未删行推导（与列表筛选无关）
     */
    public function resolveGlobalSyncMode(): string {
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN syncMode = 'auto' THEN 1 ELSE 0 END) AS autoCount
                FROM {$this->table}
                WHERE deletedAt IS NULL";
        $row = $this->db->fetchOne($sql);
        $total = (int)($row['total'] ?? 0);
        if ($total === 0) {
            return 'auto';
        }
        if ((int)($row['autoCount'] ?? 0) > 0) {
            return 'auto';
        }
        return 'manual';
    }

    /**
     * 列表：仅 EnabledMark=1 且未软删的配置
     * auto：关联品种与 currencyExchangeRates；manual：读本表 baseCurrency / exchangeRate
     */
    public function listWithSymbols(?string $search = null, ?string $syncMode = null): array {
        $params = [];
        $where = ['s.EnabledMark = 1', 'e.deletedAt IS NULL'];

        if ($search !== null && trim($search) !== '') {
            $where[] = 's.symbolName LIKE :search';
            $params['search'] = '%' . trim($search) . '%';
        }
        if ($syncMode !== null && in_array($syncMode, ['auto', 'manual'], true)) {
            $where[] = 'e.syncMode = :syncMode';
            $params['syncMode'] = $syncMode;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT e.id, e.symbolId, s.symbolName AS symbol, s.Currency AS symbolCurrency,
                       s.trading_platforms_key AS tradingPlatformKey,
                       e.targetCurrency, e.baseCurrency AS settingBaseCurrency,
                       e.exchangeRate AS settingExchangeRate,
                       e.remarks, e.syncMode, e.updatedAt
                FROM {$this->table} e
                INNER JOIN ibCustomSymbols s ON s.id = e.symbolId
                WHERE {$whereSql}
                ORDER BY s.symbolName ASC, s.trading_platforms_key ASC";

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            self::enrichDisplayRates($row);
        }
        unset($row);

        return $rows;
    }

    public function findDetailById(int $id): ?array {
        $sql = "SELECT e.id, e.symbolId, s.symbolName AS symbol, s.Currency AS symbolCurrency,
                       s.trading_platforms_key AS tradingPlatformKey,
                       e.targetCurrency, e.baseCurrency AS settingBaseCurrency,
                       e.exchangeRate AS settingExchangeRate,
                       e.remarks, e.syncMode, e.updatedAt
                FROM {$this->table} e
                INNER JOIN ibCustomSymbols s ON s.id = e.symbolId AND s.EnabledMark = 1
                WHERE e.id = :id AND e.deletedAt IS NULL
                LIMIT 1";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if (!$row) {
            return null;
        }
        self::enrichDisplayRates($row);
        return $row;
    }

    public function findActiveById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND deletedAt IS NULL LIMIT 1";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row ?: null;
    }

    public function findBySymbolId(int $symbolId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE symbolId = :symbolId LIMIT 1";
        $row = $this->db->fetchOne($sql, ['symbolId' => $symbolId]);
        return $row ?: null;
    }

    public function findActiveBySymbolId(int $symbolId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE symbolId = :symbolId AND deletedAt IS NULL LIMIT 1";
        $row = $this->db->fetchOne($sql, ['symbolId' => $symbolId]);
        return $row ?: null;
    }

    public function softDeleteActiveById(int $id, ?int $updatedBy = null): void {
        $this->update($id, [
            'deletedAt' => date('Y-m-d H:i:s'),
            'updatedBy' => $updatedBy,
        ]);
    }

    public function batchUpdateSyncMode(string $syncMode, ?int $updatedBy = null): void {
        if (!in_array($syncMode, ['auto', 'manual'], true)) {
            throw new InvalidArgumentException('Invalid sync mode');
        }
        $sql = "UPDATE {$this->table}
                SET syncMode = :syncMode, updatedBy = :updatedBy
                WHERE deletedAt IS NULL";
        $this->db->query($sql, ['syncMode' => $syncMode, 'updatedBy' => $updatedBy]);
    }

    /**
     * 输出 baseCurrency / exchangeRate / exchangeRateCurrency 供前端展示
     */
    public static function enrichDisplayRates(array &$row): void {
        $syncMode = $row['syncMode'] ?? 'auto';
        $targetCurrency = $row['targetCurrency'] ?? 'USD';
        $symbolCurrency = $row['symbolCurrency'] ?? null;
        $settingBase = $row['settingBaseCurrency'] ?? null;
        $settingRate = $row['settingExchangeRate'] ?? null;

        if (
            $syncMode === 'manual'
            && $settingBase !== null && $settingBase !== ''
            && $settingRate !== null && $settingRate !== ''
            && (float)$settingRate > 0
        ) {
            $row['baseCurrency'] = strtoupper(trim((string)$settingBase));
            $row['exchangeRate'] = self::roundExchangeRate((float)$settingRate);
            $row['exchangeRateCurrency'] = $row['baseCurrency'];
        } else {
            $row['baseCurrency'] = $symbolCurrency ? strtoupper(trim((string)$symbolCurrency)) : '';
            $displayCurrency = self::resolveDisplayCurrencyCode($symbolCurrency, $targetCurrency);
            $row['exchangeRateCurrency'] = $displayCurrency;
            $row['exchangeRate'] = self::roundExchangeRate(self::resolveUsdExchangeRate($displayCurrency));
        }

        unset($row['symbolCurrency'], $row['settingBaseCurrency'], $row['settingExchangeRate']);
    }

    /**
     * 与交易设置一致：currencyExchangeRates 存的是 1 USD = exchangeRate 该货币
     */
    public static function resolveUsdExchangeRate(?string $currencyCode): float {
        $code = strtoupper(trim((string)$currencyCode));
        if ($code === '' || $code === 'USD') {
            return 1.0;
        }

        $rateModel = new CurrencyExchangeRate();
        $r = $rateModel->getRateByCurrencyCode($code, 'fiat');
        return ($r && (float)$r['exchangeRate'] > 0) ? (float)$r['exchangeRate'] : 1.0;
    }

    /**
     * 列表展示用：优先取品种基准币（非 USD），否则取目标币
     */
    public static function resolveDisplayCurrencyCode(?string $baseCurrency, ?string $targetCurrency): string {
        $base = strtoupper(trim((string)$baseCurrency));
        $target = strtoupper(trim((string)$targetCurrency));
        if ($base !== '' && $base !== 'USD') {
            return $base;
        }
        if ($target !== '' && $target !== 'USD') {
            return $target;
        }
        return 'USD';
    }

    public static function roundExchangeRate($value): float {
        return round((float)$value, self::EXCHANGE_RATE_DECIMALS);
    }

    /**
     * 订单算佣：按品种 trading_id 解析有效汇率（1 USD = rate 基准货币）
     */
    public static function resolveEffectiveExchangeRateForSymbolTradingId(int $tradingId, ?string $platformKey = null): float {
        if ($tradingId <= 0) {
            return 1.0;
        }

        $db = Database::getInstance();
        $params = ['tradingId' => $tradingId];
        $platformSql = '';
        if ($platformKey !== null && trim($platformKey) !== '') {
            $platformSql = ' AND s.trading_platforms_key = :platformKey';
            $params['platformKey'] = trim($platformKey);
        }

        $sql = "SELECT s.Currency AS symbolCurrency,
                       e.targetCurrency, e.baseCurrency AS settingBaseCurrency,
                       e.exchangeRate AS settingExchangeRate, e.syncMode
                FROM ibCustomSymbols s
                LEFT JOIN ibSymbolExchangeSettings e ON e.symbolId = s.id AND e.deletedAt IS NULL
                WHERE s.trading_id = :tradingId AND s.EnabledMark = 1{$platformSql}
                ORDER BY s.id ASC
                LIMIT 1";
        $row = $db->fetchOne($sql, $params);
        if (!$row) {
            return 1.0;
        }

        $displayRow = [
            'syncMode' => $row['syncMode'] ?? 'auto',
            'targetCurrency' => $row['targetCurrency'] ?? 'USD',
            'symbolCurrency' => $row['symbolCurrency'] ?? null,
            'settingBaseCurrency' => $row['settingBaseCurrency'] ?? null,
            'settingExchangeRate' => $row['settingExchangeRate'] ?? null,
        ];
        self::enrichDisplayRates($displayRow);
        $rate = (float)($displayRow['exchangeRate'] ?? 1.0);
        return $rate > 0 ? $rate : 1.0;
    }
}
