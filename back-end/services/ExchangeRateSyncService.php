<?php
/**
 * 汇率同步：以现有 MT5 symbol 为准，从网关拉中间价刷新 currencyExchangeRates 的基准 exchangeRate。
 * 每 5 分钟由 swoole 定时任务触发（myswoole/Service.php 的 sync_exchange_rates），
 * 也可 `php script/sync_exchange_rates.php` 手动跑。
 *
 * 口径：currencyExchangeRates 存 "1 USD = exchangeRate 目标货币"，只刷 type=fiat 且 isActive=1 且 code≠USD 的行。
 * 数据源是 ibCustomSymbols 里已同步的 mt5 交易对（EnabledMark=1）：遍历这些 symbol，按名字里 USD 的位置
 * 识别 USD/法币对——xxxUSD（1 X=price USD → 取倒数）、USDxxx（1 USD=price X → 直取）——只更新我们在跟的法币。
 * 没有对应 symbol 的货币不动；拉不到价的也保留旧值。
 */

require_once __DIR__ . '/../utils/Mt5GatewayApiClient.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/CurrencyExchangeRate.php';
require_once __DIR__ . '/../utils/Logger.php';

class ExchangeRateSyncService
{
    /** exchangeRate 是 decimal(20,8) */
    private const RATE_DECIMALS = 8;

    /** 网关单批 symbol 上限（与 getSymbolPrices 一致），超了分批 */
    private const SYMBOL_BATCH = 500;

    /** 汇率同步默认平台（config exchange_rate_sync.platform 未配置时的兜底） */
    private const PLATFORM_KEY = 'mt5';

    /** @var CurrencyExchangeRate */
    private $rateModel;

    /** @var Mt5GatewayApiClient */
    private $gateway;

    /** @var Database */
    private $db;

    /** 汇率同步用的平台：mt5 | mt4 | financepro（来自 config exchange_rate_sync.platform） */
    private $platform;

    public function __construct(?Mt5GatewayApiClient $gateway = null)
    {
        $this->rateModel = new CurrencyExchangeRate();
        $this->gateway = $gateway ?: new Mt5GatewayApiClient();
        $this->db = Database::getInstance();

        $appConfig = require __DIR__ . '/../config/app.php';
        $this->platform = strtolower(trim((string)($appConfig['exchange_rate_sync']['platform'] ?? self::PLATFORM_KEY)));
    }

    /**
     * @return array ['success'=>bool, 'updated'=>int, 'skipped'=>string[], 'message'=>?string]
     */
    public function syncAll(): array
    {
        // 目前只实现 mt5 的价格源；mt4 / financepro 之后再加对应分支
        if ($this->platform !== 'mt5') {
            return ['success' => true, 'updated' => 0, 'skipped' => [], 'message' => "exchange rate sync not implemented for platform '{$this->platform}'"];
        }

        // 在跟的法币目标行：code(大写) => row，跳过 USD 自身。manual 的行不动（管理员手填值）
        $targets = [];
        foreach ($this->rateModel->findAll(['isActive' => 1, 'type' => 'fiat', 'syncMode' => 'auto'], 'currencyCode ASC') as $row) {
            $code = strtoupper(trim((string)($row['currencyCode'] ?? '')));
            if ($code === '' || $code === 'USD') {
                continue;
            }
            $targets[$code] = $row;
        }
        if (empty($targets)) {
            return ['success' => true, 'updated' => 0, 'skipped' => [], 'message' => 'no active fiat currencies'];
        }

        // 遍历现有 mt5 symbol，挑出对应在跟法币的 USD 对：foreign => ['symbol'=>真实名, 'invert'=>bool]
        $plans = $this->buildPlansFromSymbols(array_keys($targets));
        if (empty($plans)) {
            return ['success' => true, 'updated' => 0, 'skipped' => array_keys($targets), 'message' => 'no matching USD symbols in ibCustomSymbols'];
        }

        $names = [];
        foreach ($plans as $p) {
            $names[$p['symbol']] = true;
        }
        try {
            $priceByName = $this->fetchPrices(array_keys($names));
        } catch (\Throwable $e) {
            Logger::error('汇率同步：拉取网关价格失败', ['error' => $e->getMessage()]);
            return ['success' => false, 'updated' => 0, 'skipped' => array_keys($targets), 'message' => $e->getMessage()];
        }

        // 本轮统一一个时间戳，让同一次同步刷到的行 lastSyncedAt 完全一致，前端好比"谁落后了"
        $syncedAt = date('Y-m-d H:i:s');
        $updated = 0;
        $skipped = [];
        foreach ($targets as $code => $row) {
            if (!isset($plans[$code])) {
                $skipped[] = $code;   // 现有 symbol 里没有这个币的 USD 对
                continue;
            }
            $plan = $plans[$code];
            $price = $priceByName[strtoupper($plan['symbol'])] ?? null;
            if ($price === null || $price <= 0) {
                $skipped[] = $code;   // 无有效报价，保留旧值
                continue;
            }
            $rate = round($plan['invert'] ? (1.0 / $price) : $price, self::RATE_DECIMALS);
            if ($rate <= 0) {
                $skipped[] = $code;
                continue;
            }
            $this->rateModel->update((int)$row['id'], [
                'exchangeRate' => number_format($rate, self::RATE_DECIMALS, '.', ''),
                'lastSyncedAt' => $syncedAt,
                'updatedBy'    => null,   // 系统同步，非某个管理员
            ]);
            $updated++;
        }

        if (!empty($skipped)) {
            Logger::info('汇率同步：部分币种无 symbol/报价，保留旧值', ['skipped' => $skipped]);
        }

        return ['success' => true, 'updated' => $updated, 'skipped' => $skipped, 'message' => null];
    }

    /**
     * 遍历已同步的 mt5 symbol，按名字里 USD 的位置识别 USD/法币对与方向。
     * 只保留 foreign 在在跟法币里的（金属 XAUUSD、加密 BTCUSD 等自动排除）。同一 foreign 先到先得。
     *
     * @param string[] $fiatCodes 大写法币代码
     * @return array foreign(大写) => ['symbol'=>真实名, 'invert'=>bool]
     */
    private function buildPlansFromSymbols(array $fiatCodes): array
    {
        $fiatSet = array_flip($fiatCodes);
        $rows = $this->db->fetchAll(
            "SELECT symbolName FROM ibCustomSymbols
             WHERE trading_platforms_key = :pk AND EnabledMark = 1 AND symbolName IS NOT NULL
             ORDER BY symbolName ASC",
            ['pk' => $this->platform]
        );

        $plans = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['symbolName'] ?? ''));
            if ($name === '') {
                continue;
            }
            [$foreign, $invert] = $this->classifyUsdPair(strtoupper($name));
            if ($foreign === null || !isset($fiatSet[$foreign])) {
                continue;
            }
            if (!isset($plans[$foreign])) {
                $plans[$foreign] = ['symbol' => $name, 'invert' => $invert];
            }
        }
        return $plans;
    }

    /**
     * 按名字里 USD 的位置判 USD/法币对：xxxUSD→(foreign=xxx, 取倒数)；USDxxx→(foreign=xxx, 直取)。
     * 非 USD 对返回 [null,false]。USD 恒 3 位，按 3 字母切分。
     */
    private function classifyUsdPair(string $name): array
    {
        $len = strlen($name);
        if ($len <= 3) {
            return [null, false];
        }
        if (substr($name, -3) === 'USD') {
            return [substr($name, 0, $len - 3), true];   // xxxUSD：1 X = price USD → 取倒数
        }
        if (substr($name, 0, 3) === 'USD') {
            return [substr($name, 3), false];            // USDxxx：1 USD = price X → 直取
        }
        return [null, false];
    }

    /**
     * 分批拉价，返回 大写symbol名 => 中间价(float, >0)。带 error 的元素没有 price，跳过。
     */
    private function fetchPrices(array $symbols): array
    {
        $out = [];
        foreach (array_chunk($symbols, self::SYMBOL_BATCH) as $chunk) {
            foreach ($this->gateway->getSymbolPrices($chunk) as $item) {
                if (!is_array($item) || !isset($item['name']) || !isset($item['price'])) {
                    continue;
                }
                $price = (float)$item['price'];
                if ($price > 0) {
                    $out[strtoupper((string)$item['name'])] = $price;
                }
            }
        }
        return $out;
    }
}
