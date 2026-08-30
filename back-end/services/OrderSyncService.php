<?php
/**
 * Order Sync Service
 * 定时任务：批量同步订单和余额信息
 * 使用 client_balance_and_trade 接口批量查询
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5GatewayApiClient.php';
require_once __DIR__ . '/../utils/Mt4ApiClient.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingCreditDeal.php';
require_once __DIR__ . '/../models/TradingGroup.php';
require_once __DIR__ . '/../services/CommissionOrderService.php';
require_once __DIR__ . '/../services/DeveloperSettingsService.php';
require_once __DIR__ . '/../config/app.php';

class OrderSyncService {
    private $db;
    private $orderModel;
    private $tradingAccountModel;
    private $externalAccountModel;
    private $creditDealModel;
    private $tradingGroupModel;
    private $platformClients; // 存储各平台的客户端实例
    private $config;
    private $tradingPlatformsConfig;
    private $integrationsConfig;
    private $developerSettings;
    private $symbolNameIdMapByPlatform;
    private $symbolNameTradingIdMapByPlatform;
    private $symbolTradingIdMapByPlatform;

    // 首次同步日期
    const FIRST_SYNC_DATE = '2025-01-01';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->orderModel = new Order();
        $this->tradingAccountModel = new TradingAccount();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->creditDealModel = new TradingCreditDeal();
        $this->tradingGroupModel = new TradingGroup();
        $this->config = require __DIR__ . '/../config/app.php';

        // 获取平台配置
        $this->tradingPlatformsConfig = isset($this->config['trading_platforms']) ? $this->config['trading_platforms'] : [];
        $this->integrationsConfig = isset($this->config['integrations']) ? $this->config['integrations'] : [];
        $this->developerSettings = new DeveloperSettingsService();
        $this->platformClients = [];
        $this->symbolNameIdMapByPlatform = [];
        $this->symbolNameTradingIdMapByPlatform = [];
        $this->symbolTradingIdMapByPlatform = [];

        // 根据配置动态初始化各平台的客户端
        $this->initializePlatformClients();
    }

    /**
     * 根据配置动态初始化各平台的客户端
     */
    private function initializePlatformClients() {
        // FinancePro 平台
        if ($this->isPlatformEnabled('financepro')) {
            $financeProConfig = isset($this->integrationsConfig['finance_pro']) ? $this->integrationsConfig['finance_pro'] : [];
            if (!empty($financeProConfig)) {
                $this->platformClients['financepro'] = new FinanceProApiClient($financeProConfig);
            }
        }

        // MT5 平台：订单 / 余额 / 赠金读全部走只读网关（不再走 WebAPI 二进制）
        if ($this->isSyncEnabledForPlatform('mt5')) {
            $mt5Config = isset($this->integrationsConfig['mt5']) ? $this->integrationsConfig['mt5'] : [];
            if (!empty($mt5Config)) {
                $this->platformClients['mt5'] = new Mt5GatewayApiClient($mt5Config);
            }
        }

        // MT4 平台
        if ($this->isSyncEnabledForPlatform('mt4')) {
            $mt4Config = isset($this->integrationsConfig['mt4']) ? $this->integrationsConfig['mt4'] : [];
            if (!empty($mt4Config)) {
                $this->platformClients['mt4'] = new Mt4ApiClient($mt4Config);
            }
        }
    }

    /**
     * 检查平台是否已对接
     *
     * @param string $platformKey 平台标识（mt4, mt5, financepro）
     * @return bool
     */
    private function isPlatformEnabled($platformKey) {
        return isset($this->tradingPlatformsConfig[$platformKey]) && $this->tradingPlatformsConfig[$platformKey] === true;
    }

    private function isSyncEnabledForPlatform($platformKey) {
        if (!$this->isPlatformEnabled($platformKey)) {
            return false;
        }
        if ($platformKey === 'mt4') {
            return $this->developerSettings->isMt4SyncEnabled();
        }
        if ($platformKey === 'mt5') {
            return $this->developerSettings->isMt5SyncEnabled();
        }
        return true;
    }

    /**
     * 执行同步任务（主入口）
     * 批量查询所有账户的订单和余额
     * 每个平台独立执行一次批量查询
     *
     * 支持的平台：
     * - financepro: 已实现，使用 client_balance_and_trade 接口批量查询
     * - mt4: 待实现（未来扩展）
     * - mt5: 待实现（未来扩展）
     */
    public function syncAll() {
        $startTime = time();
        $log = [];

        try {
            // 1. 获取所有活跃交易账户（根据配置动态获取所有启用的平台）
            $accounts = $this->getAllActiveAccounts();

            if (empty($accounts)) {
                $log[] = 'No active trading accounts found for enabled platforms';
                return $this->formatResult($log, 0, 0, 0, time() - $startTime);
            }

            $log[] = "Found " . count($accounts) . " active trading accounts";

            // 2. 按平台分组
            $accountsByPlatform = $this->groupAccountsByPlatform($accounts);

            $totalSuccess = 0;
            $totalFailed = 0;
            $totalOrders = 0;
            $totalBalances = 0;

            // 3. 按平台批量同步（每个平台独立执行一次）
            foreach ($accountsByPlatform as $platformKey => $platformAccounts) {
                if (!$this->isSyncEnabledForPlatform($platformKey)) {
                    $log[] = "Platform {$platformKey} is not enabled in config, skipping";
                    continue;
                }

                $log[] = "Starting sync for platform: {$platformKey}";
                $result = $this->syncPlatformAccounts($platformKey, $platformAccounts);
                $totalSuccess += $result['success'];
                $totalFailed += $result['failed'];
                $totalOrders += $result['orders'];
                $totalBalances += $result['balances'];
                $log = array_merge($log, $result['log']);
                $log[] = "Completed sync for platform: {$platformKey}";
            }

            $log[] = "Sync completed: Success={$totalSuccess}, Failed={$totalFailed}, Orders={$totalOrders}, Balances={$totalBalances}";

            // 判断整体是否成功：如果有账户失败，或者处理的账户数为0，则认为失败
            $overallSuccess = ($totalFailed === 0 && $totalSuccess > 0);
            if ($totalSuccess === 0 && $totalFailed === 0) {
                $overallSuccess = true;
            }

            return $this->formatResult($log, $totalSuccess, $totalFailed, count($accounts), time() - $startTime, $overallSuccess);

        } catch (Exception $e) {
            $log[] = "Sync failed: " . $e->getMessage();
//            Logger::error("OrderSyncService::syncAll failed: " . $e->getMessage());
            return $this->formatResult($log, 0, 0, 0, time() - $startTime, false);
        }
    }

    /**
     * 按平台分组账户
     */
    private function groupAccountsByPlatform($accounts) {
        $grouped = [];
        foreach ($accounts as $account) {
            $platformKey = $account['platformKey'];
            if (!isset($grouped[$platformKey])) {
                $grouped[$platformKey] = [];
            }
            $grouped[$platformKey][] = $account;
        }
        return $grouped;
    }

    /**
     * 同步指定平台的所有账户
     * 根据平台标识动态调用对应的同步方法
     *
     * 支持的平台：
     * - financepro: 使用 client_balance_and_trade 接口批量查询
     * - mt4: 未来可扩展（待实现）
     * - mt5: 未来可扩展（待实现）
     */
    private function syncPlatformAccounts($platformKey, $accounts) {
        $log = [];

        // 根据平台标识动态调用对应的同步方法
        switch ($platformKey) {
            case 'financepro':
                return $this->syncFinanceProAccounts($platformKey, $accounts);

            case 'mt4':
                return $this->syncMt4Accounts($platformKey, $accounts);
            case 'mt5':
                return $this->syncMt5Accounts($platformKey, $accounts);

            default:
                $log[] = "Platform {$platformKey} is not supported yet";
                return ['success' => 0, 'failed' => count($accounts), 'orders' => 0, 'balances' => 0, 'log' => $log];
        }
    }

    /**
     * 同步 FinancePro 平台的账户
     * 使用 client_balance_and_trade 接口批量查询订单和余额
     * 注意：每个平台独立执行一次批量查询
     *
     * @param string $platformKey 平台标识
     * @param array $accounts 该平台的账户列表
     */
    private function syncFinanceProAccounts($platformKey, $accounts) {
        $log = [];
        $success = 0;
        $failed = 0;
        $totalOrders = 0;
        $totalBalances = 0;

        // 获取 FinancePro 客户端
        if (!isset($this->platformClients[$platformKey]) || empty($this->platformClients[$platformKey])) {
            $log[] = "FinancePro client not initialized for platform: {$platformKey}";
            return ['success' => 0, 'failed' => count($accounts), 'orders' => 0, 'balances' => 0, 'log' => $log];
        }

        $client = $this->platformClients[$platformKey];

        // 获取 FinancePro 配置
        $platformConfig = isset($this->integrationsConfig['finance_pro']) ? $this->integrationsConfig['finance_pro'] : [];

        // 检查接口配置
        if (!isset($platformConfig['client_balance_and_trade']) || empty($platformConfig['client_balance_and_trade'])) {
            $log[] = "client_balance_and_trade endpoint not configured for platform: {$platformKey}";
            return ['success' => 0, 'failed' => count($accounts), 'orders' => 0, 'balances' => 0, 'log' => $log];
        }

        try {
            // 1. 获取所有历史订单中最大的 closetime 作为 TimeFrom（统一时间，不按用户分组）
            $timeFrom = $this->getLastSyncTime($platformKey);

            // 2. 获取所有账户的 providerAccountId，批量查询所有账户
            $accountIds = array_map(function($acc) {
                return $acc['providerAccountId'];
            }, $accounts);

            $accountIdsStr = implode(',', $accountIds);

            $log[] = "Syncing " . count($accounts) . " {$platformKey} accounts with TimeFrom={$timeFrom}";

            // 3. 批量查询所有账户的订单和余额（每个平台独立调用一次接口）
            try {
                $result = $this->fetchBatchOrdersAndBalances($platformKey, $client, $platformConfig, $accountIdsStr, $timeFrom);

                if ($result['success']) {
                    // 处理返回的数据，需要 client + config 以便 credit 变化时回查 GetCreditHistory
                    $processResult = $this->processBatchResult($result['data'], $accounts, $platformKey, $client, $platformConfig);

                    $success += $processResult['success'];
                    $failed += $processResult['failed'];
                    $totalOrders += $processResult['orders'];
                    $totalBalances += $processResult['balances'];

                    $log[] = "Batch sync result for {$platformKey}: Success={$processResult['success']}, Failed={$processResult['failed']}, Orders={$processResult['orders']}, Balances={$processResult['balances']}";
                } else {
                    $failed += count($accounts);
                    $log[] = "Batch API call failed for {$platformKey}: " . ($result['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $failed += count($accounts);
                $log[] = "Batch sync exception for {$platformKey}: " . $e->getMessage();
                Logger::error("OrderSyncService::syncFinanceProAccounts batch exception: " . $e->getMessage());
            }

        } catch (Exception $e) {
            $failed = count($accounts);
            $log[] = "{$platformKey} sync failed: " . $e->getMessage();
            Logger::error("OrderSyncService::syncFinanceProAccounts failed: " . $e->getMessage());
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'orders' => $totalOrders,
            'balances' => $totalBalances,
            'log' => $log
        ];
    }

    /**
     * 同步 MT5 平台的账户（余额 + 持仓 + 挂单 + 历史）
     */
    private function syncMt5Accounts($platformKey, $accounts) {
        $log = [];
        $success = 0;
        $failed = 0;
        $totalOrders = 0;
        $totalBalances = 0;

        if (!isset($this->platformClients[$platformKey]) || empty($this->platformClients[$platformKey])) {
            $log[] = "MT5 client not initialized for platform: {$platformKey}";
            return ['success' => 0, 'failed' => count($accounts), 'orders' => 0, 'balances' => 0, 'log' => $log];
        }

        $client = $this->platformClients[$platformKey];

        try {
            $log[] = "Syncing " . count($accounts) . " {$platformKey} accounts balances and orders";

            // 历史单增量同步窗口（考虑 MT5 服务器时间偏移与重叠回看，避免漏单）
            $mt5Config = isset($this->integrationsConfig['mt5']) ? $this->integrationsConfig['mt5'] : [];
            $serverOffsetMinutes = isset($mt5Config['server_time_offset_minutes']) ? (int)$mt5Config['server_time_offset_minutes'] : 0;
            $overlapMinutes = isset($mt5Config['history_overlap_minutes']) ? max(0, (int)$mt5Config['history_overlap_minutes']) : 180;
            $batchSize = isset($mt5Config['snapshot_batch_size']) ? max(1, min(500, (int)$mt5Config['snapshot_batch_size'])) : 100;

            $historyFromTs = strtotime($this->getLastSyncTime($platformKey));
            if ($historyFromTs === false || $historyFromTs <= 0) {
                $historyFromTs = strtotime(self::FIRST_SYNC_DATE);
            }
            // 回看 overlapMinutes，配合 upsert/幂等可安全重拉，减少边界漏单
            $historyFromTs = max(
                strtotime(self::FIRST_SYNC_DATE),
                (int)$historyFromTs - ($overlapMinutes * 60)
            );
            $historyToTs = time() + ($serverOffsetMinutes * 60);

            $log[] = "MT5 history window: from=" . date('Y-m-d H:i:s', $historyFromTs)
                . ", to=" . date('Y-m-d H:i:s', $historyToTs)
                . ", server_offset_minutes={$serverOffsetMinutes}, overlap_minutes={$overlapMinutes}";

            // 网关批量快照单次 ≤500 login，每批数量走 snapshot_batch_size 配置
            foreach (array_chunk($accounts, $batchSize) as $chunk) {
                $loginMap = [];
                foreach ($chunk as $account) {
                    $login = isset($account['providerAccountId']) ? (string)$account['providerAccountId'] : '';
                    if ($login === '') {
                        $failed++;
                        continue;
                    }
                    $loginMap[$login] = $account;
                }
                if (empty($loginMap)) {
                    continue;
                }

                try {
                    $snapshots = $client->getAccountOrdersBalanceSnapshots(array_keys($loginMap), $historyFromTs, $historyToTs);
                } catch (Exception $e) {
                    // 整批失败（网关断连等），本批全部记失败，继续下一批
                    $failed += count($loginMap);
                    $log[] = "MT5 batch snapshot failed for " . count($loginMap) . " accounts: " . $e->getMessage();
                    continue;
                }

                foreach ($loginMap as $login => $account) {
                    $snap = $snapshots[$login] ?? null;
                    // 网关侧该账号查不到 / 出错：返回 {login, error}，跳过不影响其它账号
                    if (!is_array($snap) || isset($snap['error'])) {
                        $failed++;
                        $log[] = "Failed MT5 sync for account {$login}: " . (is_array($snap) ? ($snap['error'] ?? 'no data') : 'no data');
                        continue;
                    }

                    try {
                        $balance = isset($snap['balance']) ? (float)$snap['balance'] : null;
                        if ($balance === null) {
                            throw new Exception('Missing balance in MT5 snapshot');
                        }
                        $credit = array_key_exists('credit', $snap) && $snap['credit'] !== null
                            ? (float)$snap['credit']
                            : null;

                        // credit 余额有变化才扫描 credits 流水，避免每次同步都全量拉历史。
                        if ($credit !== null) {
                            $storedCredit = $this->externalAccountModel->getStoredCredit($login);
                            if ($this->isCreditChanged($storedCredit, $credit)) {
                                try {
                                    $this->syncMt5CreditDeals($client, $login, $historyFromTs, $historyToTs);
                                } catch (Exception $e) {
                                    $log[] = "Failed MT5 credit deal sync for account {$login}: " . $e->getMessage();
                                }
                            }
                        }

                        $this->externalAccountModel->updateBalanceAndCredit($login, $balance, $credit);
                        $totalBalances++;

                        // 资料同步：批量快照的 user 块带 group/leverage/name 等（小写键），够刷杠杆和组，
                        try {
                            $user = isset($snap['user']) && is_array($snap['user']) ? $snap['user'] : [];
                            if (!empty($user)) {
                                $groupName = trim((string)($user['group'] ?? ''));
                                $profilePayload = [
                                    'leverage' => $user['leverage'] ?? null,
                                    'name' => $user['name'] ?? null,
                                    'rawResponse' => json_encode([
                                        'syncedAt' => date('Y-m-d H:i:s'),
                                        'user' => $user,
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                ];
                                // email 不一定在批量返回里，有值才写，缺失不覆盖原值
                                if (isset($user['email']) && $user['email'] !== '') {
                                    $profilePayload['email'] = $user['email'];
                                }
                                $resolvedGroupId = $this->resolveMt5LocalGroupId($groupName);
                                if ($resolvedGroupId !== null) {
                                    $profilePayload['groupId'] = $resolvedGroupId;
                                }
                                $this->externalAccountModel->updateProfileByProviderAccountId($login, $profilePayload);
                                if ($groupName !== '') {
                                    $this->syncMt5TradingAccountType($login, $groupName);
                                }
                            }
                        } catch (Exception $e) {
                            $log[] = "Failed MT5 user profile sync for account {$login}: " . $e->getMessage();
                        }

                        // 1) 持仓（trading_status=0）
                        $positions = isset($snap['positions']) && is_array($snap['positions']) ? $snap['positions'] : [];
                        $positions = $this->normalizeOrdersWithLocalSymbolId($positions, $platformKey);
                        if (!empty($positions)) {
                            $this->orderModel->batchUpsertActiveOrders(0, $positions, $login, $platformKey);
                            $totalOrders += count($positions);
                        }
                        // 平仓后 MT5 positions 不再返回，需要把旧的 trading_status=0 行清掉；
                        // 列表为空也要执行（例如账户已全部平仓）。
                        $this->orderModel->pruneStaleActiveOrders(0, $login, $platformKey, $this->extractTradingIds($positions));

                        // 2) 挂单（trading_status=1）
                        $pendingOrders = isset($snap['pendingOrders']) && is_array($snap['pendingOrders']) ? $snap['pendingOrders'] : [];
                        $pendingOrders = $this->normalizeOrdersWithLocalSymbolId($pendingOrders, $platformKey);
                        if (!empty($pendingOrders)) {
                            $this->orderModel->batchUpsertActiveOrders(1, $pendingOrders, $login, $platformKey);
                            $totalOrders += count($pendingOrders);
                        }
                        // 挂单被取消/成交/过期后 MT5 不再返回，同样清理残留行。
                        $this->orderModel->pruneStaleActiveOrders(1, $login, $platformKey, $this->extractTradingIds($pendingOrders));

                        // 3) 历史（trading_status=2）：网关快照已把区间内 closed_orders 映射成 historyOrders
                        $historyOrders = isset($snap['historyOrders']) && is_array($snap['historyOrders']) ? $snap['historyOrders'] : [];
                        $historyOrders = $this->normalizeOrdersWithLocalSymbolId($historyOrders, $platformKey);
                        if (!empty($historyOrders)) {
                            $this->orderModel->batchInsertHistoryOrders($historyOrders, $login, $platformKey);
                            $totalOrders += count($historyOrders);
                            $this->triggerCommissionOrdersFromHistory($historyOrders, $login, $platformKey);
                        }

                        $success++;
                    } catch (Exception $e) {
                        $failed++;
                        $log[] = "Failed MT5 sync for account {$login}: " . $e->getMessage();
                    }
                }
            }
        } finally {
            $client->disconnect();
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'orders' => $totalOrders,
            'balances' => $totalBalances,
            'log' => $log
        ];
    }

    /**
     * 同步 MT4 平台的账户（余额 + 持仓/挂单/历史订单）。
     * 结构与 syncMt5Accounts 对齐；MT4 网关无独立 credit 流水接口，跳过 credit deal 扫描。
     *
     * @param string $platformKey 平台标识（mt4）
     * @param array $accounts 该平台的账户列表
     */
    private function syncMt4Accounts($platformKey, $accounts) {
        $log = [];
        $success = 0;
        $failed = 0;
        $totalOrders = 0;
        $totalBalances = 0;

        if (!isset($this->platformClients[$platformKey]) || empty($this->platformClients[$platformKey])) {
            $log[] = "MT4 client not initialized for platform: {$platformKey}";
            return ['success' => 0, 'failed' => count($accounts), 'orders' => 0, 'balances' => 0, 'log' => $log];
        }

        $client = $this->platformClients[$platformKey];

        try {
            $log[] = "Syncing " . count($accounts) . " {$platformKey} accounts balances and orders";

            // 历史单窗口：起点用平台级水位（对齐 FinancePro），回看 overlap 防边界漏单。
            // 批量接口窗口上限 30 天，超出裁到 30 天（冷启动只补最近 30 天历史，之后增量足够小）。
            $mt4Config = isset($this->integrationsConfig['mt4']) ? $this->integrationsConfig['mt4'] : [];
            $serverOffsetMinutes = isset($mt4Config['server_time_offset_minutes']) ? (int)$mt4Config['server_time_offset_minutes'] : 0;
            $overlapMinutes = isset($mt4Config['history_overlap_minutes']) ? max(0, (int)$mt4Config['history_overlap_minutes']) : 180;
            $batchSize = isset($mt4Config['snapshot_batch_size']) ? max(1, min(500, (int)$mt4Config['snapshot_batch_size'])) : 100;

            // to 再往前留一段 overlap：server_time_offset 只是估计值，实际服务器超前量若略大于配置值，
            // 紧贴 now 的新单 open_time 会刚好越过 to 被漏掉；向前多留 overlap 覆盖这点漂移。
            $historyToTs = time() + ($serverOffsetMinutes * 60) + ($overlapMinutes * 60);
            $historyFromTs = strtotime($this->getLastSyncTime($platformKey));
            if ($historyFromTs === false || $historyFromTs <= 0) {
                $historyFromTs = strtotime(self::FIRST_SYNC_DATE);
            }
            $historyFromTs = max(strtotime(self::FIRST_SYNC_DATE), $historyFromTs - ($overlapMinutes * 60));
            if ($historyToTs - $historyFromTs > 30 * 86400) {
                $historyFromTs = $historyToTs - 30 * 86400;
            }

            $log[] = "MT4 history window: from=" . date('Y-m-d H:i:s', $historyFromTs)
                . ", to=" . date('Y-m-d H:i:s', $historyToTs)
                . ", server_offset_minutes={$serverOffsetMinutes}, overlap_minutes={$overlapMinutes}";

            foreach (array_chunk($accounts, $batchSize) as $chunk) {
                $loginMap = [];
                foreach ($chunk as $account) {
                    $login = isset($account['providerAccountId']) ? (string)$account['providerAccountId'] : '';
                    if ($login === '') {
                        $failed++;
                        continue;
                    }
                    $loginMap[$login] = $account;
                }
                if (empty($loginMap)) {
                    continue;
                }

                try {
                    $snapshots = $client->getAccountOrdersBalanceSnapshots(array_keys($loginMap), $historyFromTs, $historyToTs);
                } catch (Exception $e) {
                    // 整批失败（网关断连等），本批全部记失败，继续下一批
                    $failed += count($loginMap);
                    $log[] = "MT4 batch snapshot failed for " . count($loginMap) . " accounts: " . $e->getMessage();
                    continue;
                }

                foreach ($loginMap as $login => $account) {
                    $snap = $snapshots[$login] ?? null;
                    // 网关侧该账号查不到 / 出错：返回 {login, error}，跳过不影响其它账号
                    if (!is_array($snap) || isset($snap['error'])) {
                        $failed++;
                        $log[] = "Failed MT4 sync for account {$login}: " . (is_array($snap) ? ($snap['error'] ?? 'no data') : 'no data');
                        continue;
                    }

                    try {
                        $balance = isset($snap['balance']) ? (float)$snap['balance'] : null;
                        if ($balance === null) {
                            throw new Exception('Missing balance in MT4 snapshot');
                        }

                        $user = isset($snap['user']) && is_array($snap['user']) ? $snap['user'] : [];

                        // credit 在快照 margin 块里（网关必带）
                        $credit = array_key_exists('credit', $snap) && $snap['credit'] !== null
                            ? (float)$snap['credit']
                            : null;

                        // credit 余额有变化才去拉 /credits 流水（对齐 MT5/FP，避免每轮重复拉历史）。
                        // 必须用「更新前」的 storedCredit 比对，所以放在 updateBalanceAndCredit 之前。
                        if ($credit !== null) {
                            $storedCredit = $this->externalAccountModel->getStoredCredit($login);
                            if ($this->isCreditChanged($storedCredit, $credit)) {
                                try {
                                    $this->syncMt4CreditDeals($client, $login, $historyFromTs, $historyToTs);
                                } catch (Exception $e) {
                                    $log[] = "Failed MT4 credit deal sync for account {$login}: " . $e->getMessage();
                                }
                            }
                        }

                        // credit 为 null 时 updateBalanceAndCredit 只更新 balance、不覆盖既有 credit
                        $this->externalAccountModel->updateBalanceAndCredit($login, $balance, $credit);
                        $totalBalances++;

                        // 资料同步：批量快照的 user 块带 group/leverage/name，够刷杠杆和组；
                        // email/phone 等不在批量返回里，不传就不会覆盖原值。
                        $groupName = trim((string)($user['group'] ?? ''));
                        $profilePayload = [
                            'leverage' => $user['leverage'] ?? null,
                            'name' => $user['name'] ?? null,
                            'rawResponse' => json_encode([
                                'syncedAt' => date('Y-m-d H:i:s'),
                                'user' => $user,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ];
                        $resolvedGroupId = $this->resolveLocalGroupId($platformKey, $groupName);
                        if ($resolvedGroupId !== null) {
                            $profilePayload['groupId'] = $resolvedGroupId;
                        }
                        $this->externalAccountModel->updateProfileByProviderAccountId($login, $profilePayload);
                        if ($groupName !== '') {
                            $this->syncMt5TradingAccountType($login, $groupName);
                        }

                        // 1) 持仓（trading_status=0）
                        $positions = isset($snap['positions']) && is_array($snap['positions']) ? $snap['positions'] : [];
                        $positions = $this->normalizeOrdersWithLocalSymbolId($positions, $platformKey);
                        if (!empty($positions)) {
                            $this->orderModel->batchUpsertActiveOrders(0, $positions, $login, $platformKey);
                            $totalOrders += count($positions);
                        }
                        $this->orderModel->pruneStaleActiveOrders(0, $login, $platformKey, $this->extractTradingIds($positions));

                        // 2) 挂单（trading_status=1）
                        $pendingOrders = isset($snap['pendingOrders']) && is_array($snap['pendingOrders']) ? $snap['pendingOrders'] : [];
                        $pendingOrders = $this->normalizeOrdersWithLocalSymbolId($pendingOrders, $platformKey);
                        if (!empty($pendingOrders)) {
                            $this->orderModel->batchUpsertActiveOrders(1, $pendingOrders, $login, $platformKey);
                            $totalOrders += count($pendingOrders);
                        }
                        $this->orderModel->pruneStaleActiveOrders(1, $login, $platformKey, $this->extractTradingIds($pendingOrders));

                        // 3) 历史（trading_status=2）
                        $historyOrders = isset($snap['historyOrders']) && is_array($snap['historyOrders']) ? $snap['historyOrders'] : [];
                        // Cmd 6=balance / 7=credit 不是真实交易单，credit 已单独写 trading_credit_deals，这里过滤掉不污染 orders
                        $historyOrders = array_values(array_filter($historyOrders, static function ($o) {
                            $cmd = (int)($o['Cmd'] ?? -1);
                            return $cmd !== 6 && $cmd !== 7;
                        }));
                        $historyOrders = $this->normalizeOrdersWithLocalSymbolId($historyOrders, $platformKey);
                        if (!empty($historyOrders)) {
                            $this->orderModel->batchInsertHistoryOrders($historyOrders, $login, $platformKey);
                            $totalOrders += count($historyOrders);
                            $this->triggerCommissionOrdersFromHistory($historyOrders, $login, $platformKey);
                        }

                        $success++;
                    } catch (Exception $e) {
                        $failed++;
                        $log[] = "Failed MT4 sync for account {$login}: " . $e->getMessage();
                    }
                }
            }
        } finally {
            $client->disconnect();
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'orders' => $totalOrders,
            'balances' => $totalBalances,
            'log' => $log
        ];
    }

    /**
     * 根据平台 + 组名查找本地 trading_group.id（平台无关版本，给 MT4 复用）。
     */
    private function resolveLocalGroupId(string $platformKey, $groupName) {
        $groupName = trim((string)$groupName);
        if ($groupName === '') {
            return null;
        }
        $group = $this->tradingGroupModel->findByPlatformAndName($platformKey, $groupName);
        if (!$group || !isset($group['id'])) {
            return null;
        }
        $groupId = (int)$group['id'];
        return $groupId > 0 ? $groupId : null;
    }

    /**
     * 根据 MT5 最新组名查找本地 trading_group.id。
     */
    private function resolveMt5LocalGroupId($groupName) {
        $groupName = trim((string)$groupName);
        if ($groupName === '') {
            return null;
        }

        $group = $this->tradingGroupModel->findByPlatformAndName('mt5', $groupName);
        if (!$group || !isset($group['id'])) {
            return null;
        }

        $groupId = (int)$group['id'];
        return $groupId > 0 ? $groupId : null;
    }

    /**
     * 将 MT5 返回的组名同步到 tradingAccounts.accountType。
     */
    private function syncMt5TradingAccountType($providerAccountId, $groupName) {
        $providerAccountId = trim((string)$providerAccountId);
        $groupName = trim((string)$groupName);
        if ($providerAccountId === '' || $groupName === '') {
            return;
        }

        $externalAccount = $this->externalAccountModel->findByProviderAccountId($providerAccountId);
        if (!$externalAccount || empty($externalAccount['tradingAccountId'])) {
            return;
        }

        $tradingAccountId = (int)$externalAccount['tradingAccountId'];
        if ($tradingAccountId <= 0) {
            return;
        }

        $tradingAccount = $this->tradingAccountModel->findById($tradingAccountId);
        if (!$tradingAccount) {
            return;
        }

        $currentAccountType = trim((string)($tradingAccount['accountType'] ?? ''));
        if ($currentAccountType === $groupName) {
            return;
        }

        $this->tradingAccountModel->update($tradingAccountId, [
            'accountType' => $groupName
        ]);
    }

    /**
     * 判断 credit 是否变化，避免浮点小幅误差触发不必要的 deal 扫描。
     * - storedCredit=null 只会在 providerAccountId 在外部账户表里找不到时出现（异常情况），
     *   保险起见按"非 0 即视为有变化"处理
     */
    private function isCreditChanged($storedCredit, $currentCredit) {
        if ($storedCredit === null) {
            return abs($currentCredit) > 0.005;
        }
        return abs($currentCredit - $storedCredit) > 0.005;
    }

    /**
     * 从 MT5 网关拉取指定账户的赠金流水（GET /mt5/users/{login}/credits），幂等写入 trading_credit_deals。
     *
     * - 网关 credits 只返赠金（cmd 7），金额在 profit；出入金不在此接口
     * - 时间窗口复用历史订单同步的 from/to（已应用 server_time_offset 偏移）
     * - external_id 用 "mt5_{order}" 配合 UNIQUE 约束做幂等，重复扫描不会写脏
     */
    private function syncMt5CreditDeals($client, $providerAccountId, $fromTs, $toTs) {
        $tradingAccountId = $this->externalAccountModel->getTradingAccountIdByProviderAccountId($providerAccountId);
        if ($tradingAccountId === null) {
            return;
        }

        $credits = $client->getCredits($providerAccountId, $fromTs, $toTs);
        foreach ($credits as $deal) {
            $order = isset($deal['order']) ? (string)$deal['order'] : '';
            if ($order === '' || $order === '0') {
                continue;
            }

            // 赠金金额在 profit，带符号：正=发放(Credit In)，负=撤回(Credit Out)；CRM amount 统一存正数，方向单独存
            $rawAmount = (float)($deal['profit'] ?? 0);
            $direction = $rawAmount < 0
                ? TradingCreditDeal::DIRECTION_OUT
                : TradingCreditDeal::DIRECTION_IN;

            $openTime = isset($deal['open_time']) ? (int)$deal['open_time'] : 0;
            $dealTime = $openTime > 0 ? date('Y-m-d H:i:s', $openTime) : date('Y-m-d H:i:s');

            $this->creditDealModel->insertIgnore([
                'trading_account_id' => $tradingAccountId,
                'provider_key' => 'mt5',
                'external_id' => 'mt5_' . $order,
                'action' => 'credit',
                'direction' => $direction,
                'amount' => abs($rawAmount),
                'comment' => isset($deal['comment']) ? (string)$deal['comment'] : null,
                'deal_time' => $dealTime,
            ]);
        }
    }

    /**
     * 从 FinancePro 拉取指定账户的 credit 流水，幂等写入 trading_credit_deals。
     *
     * 调用 GetCreditHistory?login=xxx；接口一次返回该账户全部 credit 历史。
     * external_id 使用 "financepro_{ResData.Id}" 保证幂等。
     */
    private function syncFinanceProCreditDeals($client, $endpoint, $providerAccountId) {
        $tradingAccountId = $this->externalAccountModel->getTradingAccountIdByProviderAccountId($providerAccountId);
        if ($tradingAccountId === null) {
            return;
        }

        $response = $client->request(
            $endpoint,
            'GET',
            ['login' => $providerAccountId],
            ['expect_success_key' => 'Success', 'success_value' => true]
        );

        $items = isset($response['ResData']) && is_array($response['ResData']) ? $response['ResData'] : [];
        foreach ($items as $deal) {
            $dealId = isset($deal['Id']) ? (string)$deal['Id'] : '';
            if ($dealId === '' || $dealId === '0') {
                continue;
            }

            // FP 时间字段是毫秒（13 位），先归一化为秒再格式化
            $openTime = isset($deal['OpenTime']) ? (int)$deal['OpenTime'] : 0;
            if ($openTime > 9999999999) {
                $openTime = (int)($openTime / 1000);
            }
            $dealTime = $openTime > 0 ? date('Y-m-d H:i:s', $openTime) : date('Y-m-d H:i:s');

            // FP Type="Credit Out" 时 Amount 自带负号，方向直接看 Type 字段；
            // CRM 表 amount 统一存正数，direction 单独存
            $type = isset($deal['Type']) ? (string)$deal['Type'] : '';
            $direction = stripos($type, 'Out') !== false
                ? TradingCreditDeal::DIRECTION_OUT
                : TradingCreditDeal::DIRECTION_IN;

            $this->creditDealModel->insertIgnore([
                'trading_account_id' => $tradingAccountId,
                'provider_key' => 'financepro',
                'external_id' => 'financepro_' . $dealId,
                'action' => 'credit',
                'direction' => $direction,
                'amount' => abs((float)($deal['Amount'] ?? 0)),
                'comment' => isset($deal['Comment']) ? (string)$deal['Comment'] : null,
                'deal_time' => $dealTime,
            ]);
        }
    }

    /**
     * 从 MT4 网关拉取指定账户 credit 流水，幂等写入 trading_credit_deals。
     *
     * 调用 GET /users/{login}/credits?from=&to=；external_id 用 "mt4_{order}" 保证幂等。
     * profit 带符号：正=发放(Credit In)，负=撤回(Credit Out)；CRM amount 统一存正数，方向单独存。
     */
    private function syncMt4CreditDeals($client, $providerAccountId, $fromTs, $toTs) {
        $tradingAccountId = $this->externalAccountModel->getTradingAccountIdByProviderAccountId($providerAccountId);
        if ($tradingAccountId === null) {
            return;
        }

        $credits = $client->getCredits($providerAccountId, $fromTs, $toTs);
        foreach ($credits as $deal) {
            $order = isset($deal['order']) ? (string)$deal['order'] : '';
            if ($order === '' || $order === '0') {
                continue;
            }

            $rawAmount = (float)($deal['profit'] ?? 0);
            $direction = $rawAmount < 0
                ? TradingCreditDeal::DIRECTION_OUT
                : TradingCreditDeal::DIRECTION_IN;

            $openTime = isset($deal['open_time']) ? (int)$deal['open_time'] : 0;
            $dealTime = $openTime > 0 ? date('Y-m-d H:i:s', $openTime) : date('Y-m-d H:i:s');

            $this->creditDealModel->insertIgnore([
                'trading_account_id' => $tradingAccountId,
                'provider_key' => 'mt4',
                'external_id' => 'mt4_' . $order,
                'action' => 'credit',
                'direction' => $direction,
                'amount' => abs($rawAmount),
                'comment' => isset($deal['comment']) ? (string)$deal['comment'] : null,
                'deal_time' => $dealTime,
            ]);
        }
    }

    /**
     * 将 MT5 持仓结构映射为 orders 表可入库结构（trading_status=0）
     */


    /**
     * 获取最后同步时间（统一时间，不按用户分组）
     * 从 orders 表中查询所有历史订单（trading_status=2）中最大的 closetime
     * 注意：只查询历史订单，开单（trading_status=0）和挂单（trading_status=1）不在这里同步
     *
     * @param string $platformKey 平台标识
     * @return string 日期字符串（Y-m-d格式），如果没有历史订单则返回 FIRST_SYNC_DATE
     */
    private function getLastSyncTime($platformKey) {
        // 查询该平台所有历史订单中最大的 closetime（只查询 trading_status=2 的历史订单）
        $sql = "SELECT MAX(closetime) as lastCloseTime
                FROM orders
                WHERE trading_platforms_key = :platform_key
                AND trading_status = 2
                AND closetime IS NOT NULL
                AND closetime > 0";

        $result = $this->db->fetchOne($sql, [
            'platform_key' => $platformKey
        ]);

        if ($result && $result['lastCloseTime'] && $result['lastCloseTime'] > 0) {
            // 将时间戳转换为日期（Y-m-d格式）
            return date('Y-m-d', $result['lastCloseTime']);
        } else {
            // 首次同步，使用默认日期
            return self::FIRST_SYNC_DATE;
        }
    }

    /**
     * 批量查询订单和余额
     *
     * @param string $platformKey 平台标识
     * @param object $client 平台客户端实例
     * @param array $platformConfig 平台配置
     * @param string $accountIds 账户ID列表（逗号分隔）
     * @param string $timeFrom 起始时间（Y-m-d格式）
     */
    private function fetchBatchOrdersAndBalances($platformKey, $client, $platformConfig, $accountIds, $timeFrom) {
        try {
            $payload = [
                'AccountIds' => $accountIds,
                'TimeFrom' => $timeFrom
            ];

            $endpoint = $platformConfig['client_balance_and_trade'];

            $response = $client->request(
                $endpoint,
                'POST',
                $payload,
                ['expect_success_key' => 'Success', 'success_value' => true]
            );

            if (isset($response['Success']) && $response['Success'] === true && isset($response['Result'])) {
                return [
                    'success' => true,
                    'data' => $response['Result']
                ];
            }

            return [
                'success' => false,
                'error' => $response['ErrMsg'] ?? 'Invalid response format'
            ];

        } catch (Exception $e) {
            // Logger::error("Failed to fetch batch orders and balances for {$platformKey}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 处理批量返回结果
     */
    private function processBatchResult($resultData, $accounts, $platformKey, $client = null, $platformConfig = null) {
        $success = 0;
        $failed = 0;
        $totalOrders = 0;
        $totalBalances = 0;

        // 建立账户映射（providerAccountId -> account info）
        $accountMap = [];
        foreach ($accounts as $account) {
            $accountMap[$account['providerAccountId']] = $account;
        }

        // 处理每个账户的数据
        foreach ($resultData as $accountData) {
            $providerAccountId = isset($accountData['Id']) ? (string)$accountData['Id'] : null;

            if (!$providerAccountId || !isset($accountMap[$providerAccountId])) {
                continue;
            }

            $account = $accountMap[$providerAccountId];
            $tradingLogin = $providerAccountId;

            try {
                // 1. 处理余额 + credit
                $balance = isset($accountData['Balance']) && $accountData['Balance'] !== ''
                    ? (float)$accountData['Balance']
                    : null;
                $credit = isset($accountData['Credit']) && $accountData['Credit'] !== ''
                    ? (float)$accountData['Credit']
                    : null;

                // credit 数值变化时才调 GetCreditHistory 拉流水，避免每次同步都查全量
                if ($credit !== null && $client !== null && !empty($platformConfig['get_credit_history'])) {
                    $storedCredit = $this->externalAccountModel->getStoredCredit($providerAccountId);
                    if ($this->isCreditChanged($storedCredit, $credit)) {
                        try {
                            $this->syncFinanceProCreditDeals($client, $platformConfig['get_credit_history'], $providerAccountId);
                        } catch (Exception $e) {
                            Logger::error("Failed FinancePro credit deal sync for account {$providerAccountId}: " . $e->getMessage());
                        }
                    }
                }

                if ($balance !== null || $credit !== null) {
                    $this->externalAccountModel->updateBalanceAndCredit($providerAccountId, $balance, $credit);
                    if ($balance !== null) {
                        $totalBalances++;
                    }
                }

                // 2. 处理订单（client_balance_and_trade 接口返回的都是历史订单）
                if (isset($accountData['TradeList']) && is_array($accountData['TradeList'])) {
                    // Cmd=6 是 balance（入金/出金）、Cmd=7 是 credit（赠金/撤回），都不是真实交易订单，
                    // credit 流水另外通过 syncFinanceProCreditDeals 写到 trading_credit_deals 表，
                    // 这里直接过滤掉，不污染 orders 表
                    $tradeList = array_values(array_filter($accountData['TradeList'], static function ($trade) {
                        if (!is_array($trade)) {
                            return true;
                        }
                        $cmd = (int)($trade['Cmd'] ?? -1);
                        return $cmd !== 6 && $cmd !== 7;
                    }));

                    // FP 的 OpenTime / CloseTime 是毫秒（13 位），入库前归一化为秒，口径与 credit 流水一致
                    foreach ($tradeList as &$trade) {
                        if (isset($trade['OpenTime']) && (int)$trade['OpenTime'] > 9999999999) {
                            $trade['OpenTime'] = (int)((int)$trade['OpenTime'] / 1000);
                        }
                        if (isset($trade['CloseTime']) && (int)$trade['CloseTime'] > 9999999999) {
                            $trade['CloseTime'] = (int)((int)$trade['CloseTime'] / 1000);
                        }
                    }
                    unset($trade);

                    $orders = $this->normalizeOrdersWithLocalSymbolId($tradeList, $platformKey);

                    if (!empty($orders)) {
                        // 所有订单都作为历史订单处理（trading_status=2）
                        // 使用 batchInsertHistoryOrders 方法，它会自动判断新增或更新
                        $this->orderModel->batchInsertHistoryOrders($orders, $tradingLogin, $platformKey);
                        $totalOrders += count($orders);
                        $this->triggerCommissionOrdersFromHistory($orders, $tradingLogin, $platformKey);
                    }
                }

                $success++;

            } catch (Exception $e) {
                $failed++;
                Logger::error("Failed to process account {$providerAccountId}: " . $e->getMessage());
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'orders' => $totalOrders,
            'balances' => $totalBalances
        ];
    }

    /**
     * 对已入库的历史订单触发 Commission Order 生成，保持幂等。
     */
    private function triggerCommissionOrdersFromHistory(array $orders, string $tradingLogin, string $platformKey): void
    {
        $commissionService = new CommissionOrderService();
        // RelTrix 迁移交接时间线 T（config: integrations.mt4.commission_cutoff_ts），0 = 不启用
        $cutoffTs = $platformKey === 'mt4'
            ? (int)($this->integrationsConfig['mt4']['commission_cutoff_ts'] ?? 0)
            : 0;
//        $skippedBeforeCutoff = 0;
        foreach ($orders as $order) {
            $tradingId = isset($order['Id']) ? (int)$order['Id'] : 0;
            $closetime = isset($order['CloseTime']) ? (int)$order['CloseTime'] : 0;
            if ($tradingId <= 0 || $closetime <= 0) {
                continue;
            }
            // 迁移交接时间线之前平仓的单，返佣由 RelTrix 侧结算，跳过
            if ($cutoffTs > 0 && $closetime < $cutoffTs) {
//                $skippedBeforeCutoff++;
                continue;
            }

            $row = $this->db->fetchOne(
                'SELECT id FROM orders WHERE trading_id = :tid AND trading_login = :login AND trading_platforms_key = :pk AND trading_status = 2 AND closetime > 0 LIMIT 1',
                ['tid' => $tradingId, 'login' => $tradingLogin, 'pk' => $platformKey]
            );
            if ($row && !empty($row['id'])) {
                $oid = (int) $row['id'];
                try {
                    $commissionService->createFromOrder($oid);
                } catch (Exception $e) {
                    Logger::error("CommissionOrderService::createFromOrder(orderId={$oid}): " . $e->getMessage());
                }
            }
        }
//        if ($skippedBeforeCutoff > 0) {
//            Logger::error("triggerCommissionOrdersFromHistory: login={$tradingLogin} 跳过 {$skippedBeforeCutoff} 笔 T 前平仓订单的返佣");
//        }
    }

    private function extractTradingIds(array $orders): array
    {
        $ids = [];
        foreach ($orders as $order) {
            if (isset($order['Id']) && (int)$order['Id'] > 0) {
                $ids[] = (int)$order['Id'];
            }
        }
        return $ids;
    }

    private function normalizeOrdersWithLocalSymbolId(array $orders, string $platformKey): array
    {
        $normalized = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $localSymbolId = $this->resolveLocalSymbolId($platformKey, $order);
            if ($localSymbolId > 0) {
                $order['SymbolId'] = $localSymbolId;
            }
            $normalized[] = $order;
        }
        return $normalized;
    }

    private function resolveLocalSymbolId(string $platformKey, array $order): int
    {
        $this->loadSymbolMapForPlatform($platformKey);

        // 平台回传的 Symbol Id（如 FinancePro ResData.Symbols[].Id）本身就是 ibCustomSymbols.trading_id，
        // 命中已同步品种时直接存它。symbol_id 全平台统一存 trading_id，佣金匹配才能对齐 ibCustomSymbols.trading_id
        $externalSymbolId = isset($order['SymbolId']) && is_numeric($order['SymbolId']) ? (int)$order['SymbolId'] : 0;
        if ($externalSymbolId > 0 && isset($this->symbolTradingIdMapByPlatform[$platformKey][$externalSymbolId])) {
            return $externalSymbolId;
        }

        // 平台未回传 numeric id（或该品种还没同步到）时，按品种名解析 trading_id
        $symbolName = isset($order['Symbol']) ? (string)$order['Symbol'] : '';
        return $this->resolveTradingSymbolIdByName($platformKey, $symbolName);
    }

    private function resolveTradingSymbolIdByName(string $platformKey, string $symbolName): int
    {
        $this->loadSymbolMapForPlatform($platformKey);
        $key = strtoupper(trim($symbolName));
        if ($key === '') {
            return 0;
        }
        return isset($this->symbolNameTradingIdMapByPlatform[$platformKey][$key])
            ? (int)$this->symbolNameTradingIdMapByPlatform[$platformKey][$key]
            : 0;
    }

    private function loadSymbolMapForPlatform(string $platformKey): void
    {
        if (isset($this->symbolNameIdMapByPlatform[$platformKey])
            && isset($this->symbolNameTradingIdMapByPlatform[$platformKey])) {
            return;
        }

        $rows = $this->db->fetchAll(
            "SELECT id, symbolName, trading_id FROM ibCustomSymbols
             WHERE trading_platforms_key = :platform_key AND EnabledMark = 1",
            ['platform_key' => $platformKey]
        );

        $nameMap = [];
        $tradingNameMap = [];
        $tradingIdMap = [];
        foreach ($rows as $row) {
            $localId = isset($row['id']) ? (int)$row['id'] : 0;
            if ($localId <= 0) {
                continue;
            }

            $name = strtoupper(trim((string)($row['symbolName'] ?? '')));
            if ($name !== '') {
                $nameMap[$name] = $localId;
            }

            $tradingId = isset($row['trading_id']) && is_numeric($row['trading_id']) ? (int)$row['trading_id'] : 0;
            if ($tradingId > 0) {
                if ($name !== '') {
                    $tradingNameMap[$name] = $tradingId;
                }
                $tradingIdMap[$tradingId] = $localId;
            }
        }

        $this->symbolNameIdMapByPlatform[$platformKey] = $nameMap;
        $this->symbolNameTradingIdMapByPlatform[$platformKey] = $tradingNameMap;
        $this->symbolTradingIdMapByPlatform[$platformKey] = $tradingIdMap;
    }


    /**
     * 获取所有活跃的交易账户（根据配置动态获取所有启用的平台）
     * 根据 app.php 中的 trading_platforms 配置动态过滤启用的平台
     */
    private function getAllActiveAccounts() {
        $sql = "SELECT
                    ta.id as tradingAccountId,
                    ta.userId,
                    ta.status as accountStatus,
                    tp.id as platformId,
                    tp.platformKey,
                    tp.displayName as platformName,
                    tea.id as externalAccountId,
                    tea.providerAccountId,
                    tea.providerKey
                FROM tradingAccounts ta
                INNER JOIN tradingPlatforms tp ON tp.id = ta.platformId
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                WHERE ta.status = 'active'
                AND tea.providerAccountId IS NOT NULL
                AND tea.providerAccountId != ''
                ORDER BY tp.platformKey, ta.id";

        $allAccounts = $this->db->fetchAll($sql);

        $filteredAccounts = [];
        foreach ($allAccounts as $account) {
            if ($this->isSyncEnabledForPlatform($account['platformKey'])) {
                $filteredAccounts[] = $account;
            }
        }

        return $filteredAccounts;
    }

    /**
     * 格式化返回结果
     */
    private function formatResult($log, $success, $failed, $processed, $duration, $overallSuccess = true) {
        return [
            'success' => $overallSuccess,
            'statistics' => [
                'success' => $success,
                'failed' => $failed,
                'processed' => $processed,
                'duration' => $duration
            ],
            'log' => $log
        ];
    }
}
