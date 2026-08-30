<?php
/**
 * IB Product Sync Service
 * 将交易平台产品（当前为 MT5 Symbols）同步到 ibCustomSecurities / ibCustomSymbols
 */

require_once __DIR__ . '/../models/IbCustomSecurity.php';
require_once __DIR__ . '/../models/IbCustomSymbol.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/CurrencyExchangeRate.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt5GatewayApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';

class IbProductSyncService
{
    private $securityModel;
    private $symbolModel;
    private $platformModel;
    private $exchangeRateModel;

    /** 大写 currencyCode 集（已存在 + 本次已建），null=未加载；用于给缺失的 base currency 建行且不重复查库 */
    private $knownRateCodes = null;

    /** 进度文件目录 */
    private static $progressDir;

    public function __construct()
    {
        $this->securityModel = new IbCustomSecurity();
        $this->symbolModel = new IbCustomSymbol();
        $this->platformModel = new TradingPlatform();
        $this->exchangeRateModel = new CurrencyExchangeRate();
        self::$progressDir = __DIR__ . '/../storage';
    }

    /**
     * symbol 的 base currency 若不在 currencyExchangeRates 里，建一条 fiat=1（占位），
     */
    private function ensureBaseCurrencyRate($baseCurrency): void
    {
        $code = strtoupper(trim((string)$baseCurrency));
        if ($code === '' || $code === 'USD') {
            return;
        }

        if ($this->knownRateCodes === null) {
            $this->knownRateCodes = [];
            foreach (Database::getInstance()->fetchAll("SELECT currencyCode FROM currencyExchangeRates") as $r) {
                $c = strtoupper(trim((string)($r['currencyCode'] ?? '')));
                if ($c !== '') {
                    $this->knownRateCodes[$c] = true;
                }
            }
        }

        if (isset($this->knownRateCodes[$code])) {
            return;
        }

        $this->exchangeRateModel->create([
            'type'         => 'fiat',
            'currencyCode' => $code,
            'exchangeRate' => 1,
            'syncMode'     => 'auto',
            'isActive'     => 1,
        ]);
        $this->knownRateCodes[$code] = true;
    }

    /**
     * 获取同步进度
     */
    public static function getProgress(string $platformKey): ?array
    {
        $dir = self::$progressDir ?? (__DIR__ . '/../storage');
        $file = $dir . '/sync_progress_' . $platformKey . '.json';
        if (!file_exists($file)) {
            return null;
        }
        $data = @json_decode(@file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * 写入同步进度
     */
    private function writeProgress(string $platformKey, array $data): void
    {
        $file = self::$progressDir . '/sync_progress_' . $platformKey . '.json';
        $data['updatedAt'] = date('Y-m-d H:i:s');
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * 清除进度文件
     */
    private function clearProgress(string $platformKey): void
    {
        $file = self::$progressDir . '/sync_progress_' . $platformKey . '.json';
        @unlink($file);
    }

    /**
     * 同步 MT5 products（异步任务入口）
     *
     * @param int|null $adminId
     * @param bool $logIbSettings 是否写入 IB 设置操作日志
     * @return array
     * @throws Exception
     */
    public function syncMt5Products(?int $adminId = null, bool $logIbSettings = false): array
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $tradingPlatforms = $appConfig['trading_platforms'] ?? [];
        if (empty($tradingPlatforms['mt5'] ?? false)) {
            throw new Exception('Platform is not enabled in config: mt5');
        }

        $platform = $this->platformModel->findByKey('mt5');
        if (!$platform) {
            throw new Exception('Trading platform not found for: mt5');
        }

        $tradingPlatformsKey = $platform['platformKey'];

        $this->writeProgress('mt5', ['status' => 'running', 'message' => 'Connecting to MT5 server...']);

        // 默认走只读网关；group_symbol_sync_use_webapi=true 时这两类同步回退到 WebAPI 二进制。
        // 两个客户端的 getSymbols() 出参都是 PascalCase，下面的入库映射不用区分来源。
        $useWebapi = !empty($appConfig['integrations']['mt5']['group_symbol_sync_use_webapi']);

        try {
            $mt5Client = $useWebapi
                ? new Mt5ApiClient($appConfig['integrations']['mt5'] ?? [])
                : new Mt5GatewayApiClient($appConfig['integrations']['mt5'] ?? []);
            $this->writeProgress('mt5', ['status' => 'running', 'message' => 'Fetching symbols from MT5...']);
            $symbolsList = $mt5Client->getSymbols();
        } finally {
            if (isset($mt5Client)) {
                $mt5Client->disconnect();
            }
        }

        if (!is_array($symbolsList)) {
            $symbolsList = [];
        }

        $total = count($symbolsList);
        $this->writeProgress('mt5', ['status' => 'running', 'message' => "Fetched {$total} symbols, syncing to database..."]);

        $stats = $this->syncMt5ProductsToCustomTables($symbolsList, $tradingPlatformsKey, $adminId);

        $securitiesDisabled = $stats['securitiesDisabled'] ?? 0;
        $symbolsDisabled = $stats['symbolsDisabled'] ?? 0;
        $this->writeProgress('mt5', [
            'status' => 'done',
            'message' => "Sync completed. {$stats['securitiesCount']} securities, {$stats['symbolsCount']} symbols. Disabled: {$securitiesDisabled} securities, {$symbolsDisabled} symbols.",
        ]);

        if ($logIbSettings && $adminId !== null && (int) $adminId > 0) {
            require_once __DIR__ . '/OperationLog/IbSettingsOperationLog.php';
            IbSettingsOperationLog::logSyncSuccessForOperator(
                (int) $adminId,
                'mt5',
                (int) ($stats['securitiesCount'] ?? 0),
                (int) ($stats['symbolsCount'] ?? 0)
            );
        }

        return [
            'success' => true,
            'platformKey' => 'mt5',
            'securitiesCount' => $stats['securitiesCount'],
            'symbolsCount' => $stats['symbolsCount'],
        ];
    }

    /**
     * 同步 MT4 products（异步任务入口），结构对齐 syncMt5Products。
     *
     * @param int|null $adminId
     * @param bool $logIbSettings 是否写入 IB 设置操作日志
     * @return array
     * @throws Exception
     */
    public function syncMt4Products(?int $adminId = null, bool $logIbSettings = false): array
    {
        $appConfig = require __DIR__ . '/../config/app.php';
        $tradingPlatforms = $appConfig['trading_platforms'] ?? [];
        if (empty($tradingPlatforms['mt4'] ?? false)) {
            throw new Exception('Platform is not enabled in config: mt4');
        }

        $platform = $this->platformModel->findByKey('mt4');
        if (!$platform) {
            throw new Exception('Trading platform not found for: mt4');
        }

        $tradingPlatformsKey = $platform['platformKey'];

        $this->writeProgress('mt4', ['status' => 'running', 'message' => 'Connecting to MT4 gateway...']);

        $mt4Client = new Mt4ApiClient($appConfig['integrations']['mt4'] ?? []);
        try {
            $this->writeProgress('mt4', ['status' => 'running', 'message' => 'Fetching symbols from MT4...']);
            $symbolsList = $mt4Client->getSymbols();
        } finally {
            $mt4Client->disconnect();
        }

        if (!is_array($symbolsList)) {
            $symbolsList = [];
        }

        $total = count($symbolsList);
        $this->writeProgress('mt4', ['status' => 'running', 'message' => "Fetched {$total} symbols, syncing to database..."]);

        $stats = $this->syncMt4ProductsToCustomTables($symbolsList, $tradingPlatformsKey, $adminId);

        $securitiesDisabled = $stats['securitiesDisabled'] ?? 0;
        $symbolsDisabled = $stats['symbolsDisabled'] ?? 0;
        $this->writeProgress('mt4', [
            'status' => 'done',
            'message' => "Sync completed. {$stats['securitiesCount']} securities, {$stats['symbolsCount']} symbols. Disabled: {$securitiesDisabled} securities, {$symbolsDisabled} symbols.",
        ]);

        if ($logIbSettings && $adminId !== null && (int) $adminId > 0) {
            require_once __DIR__ . '/OperationLog/IbSettingsOperationLog.php';
            IbSettingsOperationLog::logSyncSuccessForOperator(
                (int) $adminId,
                'mt4',
                (int) ($stats['securitiesCount'] ?? 0),
                (int) ($stats['symbolsCount'] ?? 0)
            );
        }

        return [
            'success' => true,
            'platformKey' => 'mt4',
            'securitiesCount' => $stats['securitiesCount'],
            'symbolsCount' => $stats['symbolsCount'],
        ];
    }

    /**
     * MT4 products 同步到 ibCustomSecurities / ibCustomSymbols（按平台 upsert）。
     *
     * MT4 gateway /symbols 不提供 path/分类，也不提供 min/max/step lots，
     * 因此所有 symbol 归到单一 security "MT4"，缺失字段留 null（不臆造）。
     */
    private function syncMt4ProductsToCustomTables(array $symbolsList, string $tradingPlatformsKey, ?int $adminId): array
    {
        $securitiesSynced = 0;
        $symbolsSynced = 0;
        $securitiesDisabled = 0;
        $symbolsDisabled = 0;
        $seenSecurityTradingIds = [];
        $seenSymbolTradingIds = [];

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // MT4 无分类信息，统一归到一个 security
            $securityName = 'MT4';
            $securityTradingId = $this->allocateStableTradingId($tradingPlatformsKey, $securityName, 'security');

            $securityRow = [
                'securityName' => $securityName,
                'securityDescription' => $securityName,
                'trading_id' => $securityTradingId,
                'trading_platforms_key' => $tradingPlatformsKey,
                'Description' => $securityName,
                'EnabledMark' => 1,
                'createdBy' => $adminId,
            ];
            $existingSecurity = $this->securityModel->findOne([
                'trading_platforms_key' => $tradingPlatformsKey,
                'trading_id' => $securityTradingId,
            ]);
            if ($existingSecurity) {
                $this->securityModel->update((int)$existingSecurity['id'], $securityRow);
                $securityLocalId = (int)$existingSecurity['id'];
            } else {
                $securityLocalId = (int)$this->securityModel->create($securityRow);
            }
            $securitiesSynced++;
            $seenSecurityTradingIds[] = $securityTradingId;

            foreach ($symbolsList as $item) {
                $symbolName = isset($item['name']) ? strtoupper(trim((string)$item['name'])) : '';
                if ($symbolName === '') {
                    continue;
                }

                $symbolTradingId = $this->allocateStableTradingId($tradingPlatformsKey, $symbolName, 'symbol');
                // 记录本次同步返回的品种，循环结束后据此关闭已消失的品种
                $seenSymbolTradingIds[] = $symbolTradingId;

                $symbolDescription = isset($item['description']) ? trim((string)$item['description']) : '';
                if ($symbolDescription === '') {
                    $symbolDescription = $symbolName;
                }

                $row = [
                    'securityId' => $securityLocalId,
                    'symbolName' => $symbolName,
                    'symbolDescription' => $symbolDescription,
                    'trading_id' => $symbolTradingId,
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'Source' => $symbolName,
                    'Sescription' => $symbolDescription,
                    'Digits' => isset($item['digits']) ? (string)$item['digits'] : null,
                    // v0.2 MT4 symbol 只给 currency_margin（不区分 base/profit），Currency / MarginCurrency 都取它
                    'Currency' => isset($item['currency_margin']) ? trim((string)$item['currency_margin']) : null,
                    'MarginCurrency' => isset($item['currency_margin']) ? trim((string)$item['currency_margin']) : null,
                    'CommissionType' => null,
                    // MT4 gateway 不返回 lots 区间，留空
                    'MinLots' => null,
                    'MaxLots' => null,
                    'LotsStep' => null,
                    'ContractSize' => $item['contract_size'] ?? null,
                    'InitialMargin' => $item['margin_initial'] ?? null,
                    'Maintenance' => $item['margin_maintenance'] ?? null,
                    'Hedged' => $item['margin_hedged'] ?? null,
                    'TickSize' => $item['tick_size'] ?? null,
                    'TickPrice' => $item['tick_value'] ?? null,
                    'SpreadByDefault' => $item['spread'] ?? null,
                    'LimitStopLevel' => $item['stops_level'] ?? null,
                    // trade / execution 在 MT4 gateway 是枚举字符串，本地列存 int，无确定映射时留空
                    'Trade' => null,
                    'Execution' => null,
                    'EnabledMark' => 1,
                    'createdBy' => $adminId,
                ];

                $existingSymbol = $this->symbolModel->findOne([
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'trading_id' => $symbolTradingId,
                ]);
                if ($existingSymbol) {
                    $this->symbolModel->update((int)$existingSymbol['id'], $row);
                } else {
                    $this->symbolModel->create($row);
                }
                $symbolsSynced++;
            }

            // 平台本次未再返回的 security/symbol：标记 EnabledMark=0，不删除以保留本地数据
            $securitiesDisabled = $this->disableProductsNotInSync('ibCustomSecurities', $tradingPlatformsKey, $seenSecurityTradingIds);
            $symbolsDisabled = $this->disableProductsNotInSync('ibCustomSymbols', $tradingPlatformsKey, $seenSymbolTradingIds);

            self::ensureExchangeSettingsForPlatform($tradingPlatformsKey, $adminId);

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

        return [
            'securitiesCount' => $securitiesSynced,
            'symbolsCount' => $symbolsSynced,
            'securitiesDisabled' => $securitiesDisabled,
            'symbolsDisabled' => $symbolsDisabled,
        ];
    }

    /**
     * MT5 products 同步到 ibCustomSecurities / ibCustomSymbols（按平台 upsert）
     */
    private function syncMt5ProductsToCustomTables(array $symbolsList, string $tradingPlatformsKey, ?int $adminId): array
    {
        $securityLocalIdMap = [];
        $securitiesSynced = 0;
        $symbolsSynced = 0;
        $securitiesDisabled = 0;
        $symbolsDisabled = 0;
        $seenSecurityTradingIds = [];
        $seenSymbolTradingIds = [];
        $total = count($symbolsList);
        $processed = 0;

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach ($symbolsList as $item) {
                $processed++;

                $symbolName = isset($item['Symbol']) ? strtoupper(trim((string)$item['Symbol'])) : '';
                if ($symbolName === '') {
                    continue;
                }

                // base currency 缺行则建 fiat=1，供汇率同步刷（gateway getSymbols 带 CurrencyBase；WebAPI 路径无则跳过）
                $this->ensureBaseCurrencyRate($item['CurrencyBase'] ?? null);

                $path = isset($item['Path']) ? trim((string)$item['Path']) : '';
                $securityName = $this->deriveMt5SecurityName($path, $symbolName);

                // MT5 symbol 无稳定 numeric id，使用 deterministic hash 生成 trading_id（按平台唯一）
                $securityTradingId = $this->allocateStableTradingId($tradingPlatformsKey, $securityName, 'security');
                $symbolTradingId = $this->allocateStableTradingId($tradingPlatformsKey, $symbolName, 'symbol');
                // 记录本次同步返回的分类/品种，循环结束后据此关闭已消失的
                $seenSecurityTradingIds[] = $securityTradingId;
                $seenSymbolTradingIds[] = $symbolTradingId;

                $securityMapKey = $securityName . '|' . $securityTradingId;
                if (!isset($securityLocalIdMap[$securityMapKey])) {
                    $securityRow = [
                        'securityName' => $securityName,
                        'securityDescription' => $securityName,
                        'trading_id' => $securityTradingId,
                        'trading_platforms_key' => $tradingPlatformsKey,
                        'Description' => $securityName,
                        'EnabledMark' => 1,
                        'createdBy' => $adminId
                    ];

                    $existingSecurity = $this->securityModel->findOne([
                        'trading_platforms_key' => $tradingPlatformsKey,
                        'trading_id' => $securityTradingId
                    ]);
                    if ($existingSecurity) {
                        $this->securityModel->update((int)$existingSecurity['id'], $securityRow);
                        $securityLocalIdMap[$securityMapKey] = (int)$existingSecurity['id'];
                    } else {
                        $securityLocalIdMap[$securityMapKey] = (int)$this->securityModel->create($securityRow);
                    }
                    $securitiesSynced++;
                }

                $securityLocalId = $securityLocalIdMap[$securityMapKey];

                $symbolDescription = isset($item['Description']) ? trim((string)$item['Description']) : '';
                if ($symbolDescription === '') {
                    $symbolDescription = $symbolName;
                }

                $row = [
                    'securityId' => $securityLocalId,
                    'symbolName' => $symbolName,
                    'symbolDescription' => $symbolDescription,
                    'trading_id' => $symbolTradingId,
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'Source' => isset($item['Source']) ? trim((string)$item['Source']) : null,
                    'Sescription' => $symbolDescription,
                    'Digits' => isset($item['Digits']) ? (string)$item['Digits'] : null,
                    'Currency' => isset($item['CurrencyProfit']) ? trim((string)$item['CurrencyProfit']) : null,
                    'MarginCurrency' => isset($item['CurrencyMargin']) ? trim((string)$item['CurrencyMargin']) : null,
                    'CommissionType' => null,
                    'MinLots' => $this->normalizeMt5Lots($item['VolumeMin'] ?? null, $item['VolumeMinExt'] ?? null),
                    'MaxLots' => $this->normalizeMt5Lots($item['VolumeMax'] ?? null, $item['VolumeMaxExt'] ?? null),
                    'LotsStep' => $this->normalizeMt5Lots($item['VolumeStep'] ?? null, $item['VolumeStepExt'] ?? null),
                    'ContractSize' => isset($item['ContractSize']) ? $item['ContractSize'] : null,
                    'InitialMargin' => isset($item['MarginInitial']) ? $item['MarginInitial'] : null,
                    'Maintenance' => isset($item['MarginMaintenance']) ? $item['MarginMaintenance'] : null,
                    'Hedged' => isset($item['MarginHedged']) ? $item['MarginHedged'] : null,
                    'TickSize' => isset($item['TickSize']) ? $item['TickSize'] : null,
                    'TickPrice' => isset($item['TickValue']) ? $item['TickValue'] : null,
                    'SpreadByDefault' => isset($item['Spread']) ? $item['Spread'] : null,
                    'LimitStopLevel' => isset($item['StopsLevel']) ? $item['StopsLevel'] : null,
                    'Trade' => isset($item['TradeMode']) ? (int)$item['TradeMode'] : null,
                    'Execution' => isset($item['ExecMode']) ? (int)$item['ExecMode'] : null,
                    'EnabledMark' => 1,
                    'createdBy' => $adminId
                ];

                $existingSymbol = $this->symbolModel->findOne([
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'trading_id' => $symbolTradingId
                ]);
                if ($existingSymbol) {
                    $this->symbolModel->update((int)$existingSymbol['id'], $row);
                } else {
                    $this->symbolModel->create($row);
                }
                $symbolsSynced++;
            }

            // 平台本次未再返回的 security/symbol：标记 EnabledMark=0，不删除以保留本地数据
            $securitiesDisabled = $this->disableProductsNotInSync('ibCustomSecurities', $tradingPlatformsKey, $seenSecurityTradingIds);
            $symbolsDisabled = $this->disableProductsNotInSync('ibCustomSymbols', $tradingPlatformsKey, $seenSymbolTradingIds);

            self::ensureExchangeSettingsForPlatform($tradingPlatformsKey, $adminId);

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

        return [
            'securitiesCount' => $securitiesSynced,
            'symbolsCount' => $symbolsSynced,
            'securitiesDisabled' => $securitiesDisabled,
            'symbolsDisabled' => $symbolsDisabled
        ];
    }

    /**
     * 平台同步 Symbol 时维护 ibSymbolExchangeSettings：
     * - 禁用品种：软删配置
     * - 再启用：恢复已软删行（保留删前配置）
     * - 从未有配置：INSERT 默认 auto + USD
     */
    public static function ensureExchangeSettingsForPlatform(string $platformKey, ?int $adminId = null): void {
        $db = Database::getInstance();
        $params = [
            'adminId' => $adminId,
            'platformKey' => $platformKey,
        ];

        $db->query(
            "UPDATE ibSymbolExchangeSettings e
             INNER JOIN ibCustomSymbols s ON s.id = e.symbolId
             SET e.deletedAt = CURRENT_TIMESTAMP, e.updatedBy = :adminId
             WHERE s.trading_platforms_key = :platformKey
               AND s.EnabledMark = 0
               AND e.deletedAt IS NULL",
            $params
        );

        $db->query(
            "UPDATE ibSymbolExchangeSettings e
             INNER JOIN ibCustomSymbols s ON s.id = e.symbolId
             SET e.deletedAt = NULL, e.updatedBy = :adminId
             WHERE s.trading_platforms_key = :platformKey
               AND s.EnabledMark = 1
               AND e.deletedAt IS NOT NULL",
            $params
        );

        $db->query(
            "INSERT INTO ibSymbolExchangeSettings (symbolId, targetCurrency, syncMode, updatedBy)
             SELECT s.id, 'USD', 'auto', :adminId
             FROM ibCustomSymbols s
             LEFT JOIN ibSymbolExchangeSettings e ON e.symbolId = s.id
             WHERE s.trading_platforms_key = :platformKey
               AND s.EnabledMark = 1
               AND e.id IS NULL",
            $params
        );
    }

    /**
     * 同步后把指定平台下、本次未返回的 trading_id 标记为不可用（EnabledMark=0），不删除。
     * 逻辑与 IbSettingsController::disableFinanceProProductsNotInSync 对齐。
     *
     * @param string $table            ibCustomSecurities / ibCustomSymbols
     * @param string $platformKey      平台标识（mt4/mt5）
     * @param int[]  $syncedTradingIds 本次同步到的 trading_id
     * @return int 本次被置为不可用的行数
     */
    private function disableProductsNotInSync(string $table, string $platformKey, array $syncedTradingIds): int
    {
        $syncedTradingIds = array_values(array_unique(array_filter(array_map('intval', $syncedTradingIds), static function ($id) {
            return $id > 0;
        })));
        // 本次没同步到任何 id 时跳过，避免网关异常返回空把整个平台的数据全部关掉
        if (empty($syncedTradingIds)) {
            return 0;
        }

        $db = Database::getInstance();
        $placeholders = [];
        $params = ['platform' => $platformKey];
        foreach ($syncedTradingIds as $index => $tradingId) {
            $paramKey = 'tid' . $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $tradingId;
        }

        $inList = implode(', ', $placeholders);
        $sql = "UPDATE {$table}
                SET EnabledMark = 0
                WHERE trading_platforms_key = :platform
                  AND trading_id IS NOT NULL
                  AND trading_id NOT IN ({$inList})";
        $stmt = $db->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 从 Path 提取 security 分类名
     * 例：Utrada Global Standard\Forex_M\USDJPY → Forex_M
     *     Forex\EURUSD → Forex
     *     USDJPY → MT5（无目录层级时兜底）
     *
     * 规则：最后一段通常是 symbol 本身，取倒数第二段作为 security 类别
     */
    private function deriveMt5SecurityName(string $path, string $symbolName): string
    {
        if ($path === '') {
            return 'MT5';
        }
        $path = str_replace('\\', '/', $path);
        $parts = array_values(array_filter(explode('/', $path), static function ($s) {
            return $s !== '';
        }));
        if (empty($parts)) {
            return 'MT5';
        }
        // 去掉最后一段（symbol 本身），取剩余的最后一段作为 security
        if (count($parts) >= 2) {
            $candidate = $parts[count($parts) - 2];
        } else {
            // 只有一段且就是 symbol 名本身，无分类信息
            $candidate = (strcasecmp($parts[0], $symbolName) === 0) ? 'MT5' : $parts[0];
        }
        return trim($candidate) !== '' ? trim($candidate) : 'MT5';
    }

    private function allocateStableTradingId(string $platformKey, string $name, string $kind): int
    {
        $seed = strtolower($platformKey . ':' . $kind . ':' . $name);
        $id = (int)sprintf('%u', crc32($seed));
        return ($id % 2000000000) + 1;
    }

    private function normalizeMt5Lots($oldVolume, $extVolume): ?float
    {
        if ($extVolume !== null && $extVolume !== '' && is_numeric($extVolume)) {
            return round(((float)$extVolume) / 100000000.0, 8);
        }
        if ($oldVolume !== null && $oldVolume !== '' && is_numeric($oldVolume)) {
            return round(((float)$oldVolume) / 10000.0, 8);
        }
        return null;
    }
}
