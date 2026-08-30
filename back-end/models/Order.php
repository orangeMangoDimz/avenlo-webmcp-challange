<?php
/**
 * Order Model
 * 用于操作orders表，存储交易订单数据
 */

require_once __DIR__ . '/BaseModel.php';

class Order extends BaseModel {
    /**
     * orders.volume is stored in units of lots * 100: MT4 passes lot*100 through, and MT5
     * converts VolumeExt (1e-8 lots) via /1e8*100 (see Mt5GatewayApiClient). Divide by this
     * constant whenever lots are displayed.
     */
    const VOLUME_PER_LOT = 100;

    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $fillable = [
        'trading_id',
        'trading_login',
        'trading_platforms_key',
        'trading_status',
        'symbol_id',
        'symbol',
        'source',
        'digits',
        'cmd',
        'volume',
        'opentime',
        'state',
        'ordertype',
        'targetprice',
        'openprice',
        'sl',
        'tp',
        'closetime',
        'expiration',
        'reason',
        'commission',
        'commissionagent',
        'storage',
        'closeprice',
        'ask',
        'bid',
        'profit',
        'taxes',
        'magic',
        'comment',
        'gwopenorder',
        'gwcloseorder',
        'gwopenprice',
        'gwcloseprice',
        'margin',
        'marginrate',
        'created_at',
        'updated_at'
    ];

    /**
     * 批量插入或更新历史订单（client_balance_and_trade 接口返回的都是历史订单）
     * 使用 ON DUPLICATE KEY UPDATE 实现新增或更新
     * 所有订单都设置为 trading_status=2（历史订单）
     *
     * @param array $orders 订单数据数组（都是历史订单）
     * @param string $tradingLogin 交易账户ID
     * @param string $platformKey 交易平台标识
     * @return int 影响的行数
     */
    public function batchInsertHistoryOrders($orders, $tradingLogin, $platformKey) {
        if (empty($orders)) {
            return 0;
        }

        $now = time();
        $values = [];
        $params = [];

        foreach ($orders as $index => $order) {
            $values[] = "(
                :trading_id{$index},
                :trading_login{$index},
                :trading_platforms_key{$index},
                :trading_status{$index},
                :symbol_id{$index},
                :symbol{$index},
                :source{$index},
                :digits{$index},
                :cmd{$index},
                :volume{$index},
                :opentime{$index},
                :state{$index},
                :ordertype{$index},
                :targetprice{$index},
                :openprice{$index},
                :sl{$index},
                :tp{$index},
                :closetime{$index},
                :expiration{$index},
                :reason{$index},
                :commission{$index},
                :commissionagent{$index},
                :storage{$index},
                :closeprice{$index},
                :ask{$index},
                :bid{$index},
                :profit{$index},
                :taxes{$index},
                :magic{$index},
                :comment{$index},
                :gwopenorder{$index},
                :gwcloseorder{$index},
                :gwopenprice{$index},
                :gwcloseprice{$index},
                :margin{$index},
                :marginrate{$index},
                :created_at{$index},
                :updated_at{$index}
            )";

            $params["trading_id{$index}"] = isset($order['Id']) ? (int)$order['Id'] : 0;
            $params["trading_login{$index}"] = $tradingLogin;
            $params["trading_platforms_key{$index}"] = $platformKey;
            $params["trading_status{$index}"] = 2; // 所有订单都是历史订单
            $params["symbol_id{$index}"] = isset($order['SymbolId']) ? (int)$order['SymbolId'] : 0;
            $params["symbol{$index}"] = $order['Symbol'] ?? null;
            $params["source{$index}"] = $order['Source'] ?? null;
            $params["digits{$index}"] = isset($order['Digits']) ? (int)$order['Digits'] : 0;
            $params["cmd{$index}"] = isset($order['Cmd']) ? (int)$order['Cmd'] : 0;
            $params["volume{$index}"] = isset($order['Volume']) ? (float)$order['Volume'] : 0.00;
            $params["opentime{$index}"] = isset($order['OpenTime']) ? (int)$order['OpenTime'] : 0;
            $params["state{$index}"] = isset($order['State']) ? (int)$order['State'] : 2;
            $params["ordertype{$index}"] = $order['OrderType'] ?? null;
            $params["targetprice{$index}"] = isset($order['TargetPrice']) && $order['TargetPrice'] !== '' ? (float)$order['TargetPrice'] : null;
            $params["openprice{$index}"] = isset($order['OpenPrice']) ? (float)$order['OpenPrice'] : 0.000000;
            $params["sl{$index}"] = isset($order['Sl']) ? (float)$order['Sl'] : null;
            $params["tp{$index}"] = isset($order['Tp']) ? (float)$order['Tp'] : null;
            $params["closetime{$index}"] = isset($order['CloseTime']) && $order['CloseTime'] > 0 ? (int)$order['CloseTime'] : null;
            $params["expiration{$index}"] = isset($order['Expiration']) && $order['Expiration'] > 0 ? (int)$order['Expiration'] : null;
            $params["reason{$index}"] = isset($order['Reason']) ? (int)$order['Reason'] : null;
            $params["commission{$index}"] = isset($order['Commission']) ? (float)$order['Commission'] : 0.00;
            $params["commissionagent{$index}"] = isset($order['CommissionAgent']) ? (float)$order['CommissionAgent'] : null;
            $params["storage{$index}"] = isset($order['Storage']) ? (float)$order['Storage'] : null;
            $params["closeprice{$index}"] = isset($order['ClosePrice']) ? (float)$order['ClosePrice'] : null;
            $params["ask{$index}"] = isset($order['Ask']) ? (float)$order['Ask'] : null;
            $params["bid{$index}"] = isset($order['Bid']) ? (float)$order['Bid'] : null;
            $params["profit{$index}"] = isset($order['Profit']) ? (float)$order['Profit'] : null;
            $params["taxes{$index}"] = isset($order['Taxes']) ? (float)$order['Taxes'] : null;
            $params["magic{$index}"] = isset($order['Magic']) ? (int)$order['Magic'] : null;
            $params["comment{$index}"] = $order['Comment'] ?? null;
            $params["gwopenorder{$index}"] = $order['GwOpenOrder'] ?? null;
            $params["gwcloseorder{$index}"] = $order['GwCloseOrder'] ?? null;
            $params["gwopenprice{$index}"] = isset($order['GwOpenPrice']) ? (float)$order['GwOpenPrice'] : null;
            $params["gwcloseprice{$index}"] = isset($order['GwClosePrice']) ? (float)$order['GwClosePrice'] : null;
            $params["margin{$index}"] = isset($order['Margin']) ? (float)$order['Margin'] : null;
            $params["marginrate{$index}"] = isset($order['MarginRate']) ? (float)$order['MarginRate'] : null;
            $params["created_at{$index}"] = $now;
            $params["updated_at{$index}"] = $now;
        }

        $sql = "INSERT INTO {$this->table} (
            trading_id, trading_login, trading_platforms_key, trading_status,
            symbol_id, symbol, source, digits, cmd, volume, opentime, state,
            ordertype, targetprice, openprice, sl, tp, closetime, expiration,
            reason, commission, commissionagent, storage, closeprice, ask, bid,
            profit, taxes, magic, comment, gwopenorder, gwcloseorder,
            gwopenprice, gwcloseprice, margin, marginrate, created_at, updated_at
        ) VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE
            trading_login = VALUES(trading_login),
            trading_status = VALUES(trading_status),
            symbol_id = VALUES(symbol_id),
            symbol = VALUES(symbol),
            source = VALUES(source),
            digits = VALUES(digits),
            cmd = VALUES(cmd),
            volume = VALUES(volume),
            opentime = VALUES(opentime),
            state = VALUES(state),
            ordertype = VALUES(ordertype),
            targetprice = VALUES(targetprice),
            openprice = VALUES(openprice),
            sl = VALUES(sl),
            tp = VALUES(tp),
            closetime = VALUES(closetime),
            expiration = VALUES(expiration),
            reason = VALUES(reason),
            commission = VALUES(commission),
            commissionagent = VALUES(commissionagent),
            storage = VALUES(storage),
            closeprice = VALUES(closeprice),
            ask = VALUES(ask),
            bid = VALUES(bid),
            profit = VALUES(profit),
            taxes = VALUES(taxes),
            magic = VALUES(magic),
            comment = VALUES(comment),
            gwopenorder = VALUES(gwopenorder),
            gwcloseorder = VALUES(gwcloseorder),
            gwopenprice = VALUES(gwopenprice),
            gwcloseprice = VALUES(gwcloseprice),
            margin = VALUES(margin),
            marginrate = VALUES(marginrate),
            updated_at = VALUES(updated_at)";

        try {
            $this->db->query($sql, $params);
            return count($orders);
        } catch (Exception $e) {
            // error_log('Batch insert/update history orders failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量插入订单（不更新）
     *
     * @param array $orders 订单数据数组
     * @param string $tradingLogin 交易账户ID
     * @param string $platformKey 交易平台标识
     * @param int $tradingStatus 订单状态
     * @return int 插入数量
     */
    private function batchInsert($orders, $tradingLogin, $platformKey, $tradingStatus) {
        if (empty($orders)) {
            return 0;
        }

        $now = time();
        $values = [];
        $params = [];

        foreach ($orders as $index => $order) {
            $state = isset($order['State']) ? (int)$order['State'] : 2;

            $values[] = "(
                :trading_id{$index},
                :trading_login{$index},
                :trading_platforms_key{$index},
                :trading_status{$index},
                :symbol_id{$index},
                :symbol{$index},
                :source{$index},
                :digits{$index},
                :cmd{$index},
                :volume{$index},
                :opentime{$index},
                :state{$index},
                :ordertype{$index},
                :targetprice{$index},
                :openprice{$index},
                :sl{$index},
                :tp{$index},
                :closetime{$index},
                :expiration{$index},
                :reason{$index},
                :commission{$index},
                :commissionagent{$index},
                :storage{$index},
                :closeprice{$index},
                :ask{$index},
                :bid{$index},
                :profit{$index},
                :taxes{$index},
                :magic{$index},
                :comment{$index},
                :gwopenorder{$index},
                :gwcloseorder{$index},
                :gwopenprice{$index},
                :gwcloseprice{$index},
                :margin{$index},
                :marginrate{$index},
                :created_at{$index},
                :updated_at{$index}
            )";

            $params["trading_id{$index}"] = isset($order['Id']) ? (int)$order['Id'] : 0;
            $params["trading_login{$index}"] = $tradingLogin;
            $params["trading_platforms_key{$index}"] = $platformKey;
            $params["trading_status{$index}"] = $tradingStatus;
            $params["symbol_id{$index}"] = isset($order['SymbolId']) ? (int)$order['SymbolId'] : 0;
            $params["symbol{$index}"] = $order['Symbol'] ?? null;
            $params["source{$index}"] = $order['Source'] ?? null;
            $params["digits{$index}"] = isset($order['Digits']) ? (int)$order['Digits'] : 0;
            $params["cmd{$index}"] = isset($order['Cmd']) ? (int)$order['Cmd'] : 0;
            $params["volume{$index}"] = isset($order['Volume']) ? (float)$order['Volume'] : 0.00;
            $params["opentime{$index}"] = isset($order['OpenTime']) ? (int)$order['OpenTime'] : 0;
            $params["state{$index}"] = $state;
            $params["ordertype{$index}"] = $order['OrderType'] ?? null;
            $params["targetprice{$index}"] = isset($order['TargetPrice']) && $order['TargetPrice'] !== '' ? (float)$order['TargetPrice'] : null;
            $params["openprice{$index}"] = isset($order['OpenPrice']) ? (float)$order['OpenPrice'] : 0.000000;
            $params["sl{$index}"] = isset($order['Sl']) ? (float)$order['Sl'] : null;
            $params["tp{$index}"] = isset($order['Tp']) ? (float)$order['Tp'] : null;
            $params["closetime{$index}"] = isset($order['CloseTime']) && $order['CloseTime'] > 0 ? (int)$order['CloseTime'] : null;
            $params["expiration{$index}"] = isset($order['Expiration']) && $order['Expiration'] > 0 ? (int)$order['Expiration'] : null;
            $params["reason{$index}"] = isset($order['Reason']) ? (int)$order['Reason'] : null;
            $params["commission{$index}"] = isset($order['Commission']) ? (float)$order['Commission'] : 0.00;
            $params["commissionagent{$index}"] = isset($order['CommissionAgent']) ? (float)$order['CommissionAgent'] : null;
            $params["storage{$index}"] = isset($order['Storage']) ? (float)$order['Storage'] : null;
            $params["closeprice{$index}"] = isset($order['ClosePrice']) ? (float)$order['ClosePrice'] : null;
            $params["ask{$index}"] = isset($order['Ask']) ? (float)$order['Ask'] : null;
            $params["bid{$index}"] = isset($order['Bid']) ? (float)$order['Bid'] : null;
            $params["profit{$index}"] = isset($order['Profit']) ? (float)$order['Profit'] : null;
            $params["taxes{$index}"] = isset($order['Taxes']) ? (float)$order['Taxes'] : null;
            $params["magic{$index}"] = isset($order['Magic']) ? (int)$order['Magic'] : null;
            $params["comment{$index}"] = $order['Comment'] ?? null;
            $params["gwopenorder{$index}"] = $order['GwOpenOrder'] ?? null;
            $params["gwcloseorder{$index}"] = $order['GwCloseOrder'] ?? null;
            $params["gwopenprice{$index}"] = isset($order['GwOpenPrice']) ? (float)$order['GwOpenPrice'] : null;
            $params["gwcloseprice{$index}"] = isset($order['GwClosePrice']) ? (float)$order['GwClosePrice'] : null;
            $params["margin{$index}"] = isset($order['Margin']) ? (float)$order['Margin'] : null;
            $params["marginrate{$index}"] = isset($order['MarginRate']) ? (float)$order['MarginRate'] : null;
            $params["created_at{$index}"] = $now;
            $params["updated_at{$index}"] = $now;
        }

        $sql = "INSERT INTO {$this->table} (
            trading_id, trading_login, trading_platforms_key, trading_status,
            symbol_id, symbol, source, digits, cmd, volume, opentime, state,
            ordertype, targetprice, openprice, sl, tp, closetime, expiration,
            reason, commission, commissionagent, storage, closeprice, ask, bid,
            profit, taxes, magic, comment, gwopenorder, gwcloseorder,
            gwopenprice, gwcloseprice, margin, marginrate, created_at, updated_at
        ) VALUES " . implode(', ', $values);

        try {
            $this->db->query($sql, $params);
            return count($orders);
        } catch (Exception $e) {
            error_log('Batch insert orders failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量更新订单状态
     *
     * @param array $tradingIds 订单ID数组
     * @param string $platformKey 平台标识
     * @param int $newStatus 新状态
     * @return int 更新数量
     */
    private function batchUpdateStatus($tradingIds, $platformKey, $newStatus) {
        if (empty($tradingIds)) {
            return 0;
        }

        $placeholders = [];
        $params = ['platform_key' => $platformKey, 'new_status' => $newStatus];

        foreach ($tradingIds as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $sql = "UPDATE {$this->table}
                SET trading_status = :new_status, updated_at = :updated_at
                WHERE trading_id IN (" . implode(', ', $placeholders) . ")
                AND trading_platforms_key = :platform_key
                AND trading_status != :new_status";

        $params['updated_at'] = time();

        try {
            $this->db->query($sql, $params);
            return count($tradingIds);
        } catch (Exception $e) {
            error_log('Batch update order status failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 批量插入或更新开单/未开单订单（正常更新所有字段）
     * 使用ON DUPLICATE KEY UPDATE实现
     *
     * @param array $orders 订单数据数组（State=0或1的订单）
     * @param string $tradingLogin 交易账户ID
     * @param string $platformKey 交易平台标识
     * @return int 影响的行数
     */
    public function batchUpsertActiveOrders($tradingStatus, $orders, $tradingLogin, $platformKey) {
        if (empty($orders)) {
            return 0;
        }

        $now = time();
        $values = [];
        $params = [];

        foreach ($orders as $index => $order) {
            // State: 0-挂单/未开单，1-开单持仓
            $state = isset($order['State']) ? (int)$order['State'] : 0;

            $values[] = "(
                :trading_id{$index},
                :trading_login{$index},
                :trading_platforms_key{$index},
                :trading_status{$index},
                :symbol_id{$index},
                :symbol{$index},
                :source{$index},
                :digits{$index},
                :cmd{$index},
                :volume{$index},
                :opentime{$index},
                :state{$index},
                :ordertype{$index},
                :targetprice{$index},
                :openprice{$index},
                :sl{$index},
                :tp{$index},
                :closetime{$index},
                :expiration{$index},
                :reason{$index},
                :commission{$index},
                :commissionagent{$index},
                :storage{$index},
                :closeprice{$index},
                :ask{$index},
                :bid{$index},
                :profit{$index},
                :taxes{$index},
                :magic{$index},
                :comment{$index},
                :gwopenorder{$index},
                :gwcloseorder{$index},
                :gwopenprice{$index},
                :gwcloseprice{$index},
                :margin{$index},
                :marginrate{$index},
                :created_at{$index},
                :updated_at{$index}
            )";

            $params["trading_id{$index}"] = isset($order['Id']) ? (int)$order['Id'] : 0;
            $params["trading_login{$index}"] = $tradingLogin;
            $params["trading_platforms_key{$index}"] = $platformKey;
            $params["trading_status{$index}"] = $tradingStatus;
            $params["symbol_id{$index}"] = isset($order['SymbolId']) ? (int)$order['SymbolId'] : 0;
            $params["symbol{$index}"] = $order['Symbol'] ?? null;
            $params["source{$index}"] = $order['Source'] ?? null;
            $params["digits{$index}"] = isset($order['Digits']) ? (int)$order['Digits'] : 0;
            $params["cmd{$index}"] = isset($order['Cmd']) ? (int)$order['Cmd'] : 0;
            $params["volume{$index}"] = isset($order['Volume']) ? (float)$order['Volume'] : 0.00;
            $params["opentime{$index}"] = isset($order['OpenTime']) ? (int)$order['OpenTime'] : 0;
            $params["state{$index}"] = $state;
            $params["ordertype{$index}"] = $order['OrderType'] ?? null;
            $params["targetprice{$index}"] = isset($order['TargetPrice']) && $order['TargetPrice'] !== '' ? (float)$order['TargetPrice'] : null;
            $params["openprice{$index}"] = isset($order['OpenPrice']) ? (float)$order['OpenPrice'] : 0.000000;
            $params["sl{$index}"] = isset($order['Sl']) ? (float)$order['Sl'] : null;
            $params["tp{$index}"] = isset($order['Tp']) ? (float)$order['Tp'] : null;
            $params["closetime{$index}"] = isset($order['CloseTime']) && $order['CloseTime'] > 0 ? (int)$order['CloseTime'] : null;
            $params["expiration{$index}"] = isset($order['Expiration']) && $order['Expiration'] > 0 ? (int)$order['Expiration'] : null;
            $params["reason{$index}"] = isset($order['Reason']) ? (int)$order['Reason'] : null;
            $params["commission{$index}"] = isset($order['Commission']) ? (float)$order['Commission'] : 0.00;
            $params["commissionagent{$index}"] = isset($order['CommissionAgent']) ? (float)$order['CommissionAgent'] : null;
            $params["storage{$index}"] = isset($order['Storage']) ? (float)$order['Storage'] : null;
            $params["closeprice{$index}"] = isset($order['ClosePrice']) ? (float)$order['ClosePrice'] : null;
            $params["ask{$index}"] = isset($order['Ask']) ? (float)$order['Ask'] : null;
            $params["bid{$index}"] = isset($order['Bid']) ? (float)$order['Bid'] : null;
            $params["profit{$index}"] = isset($order['Profit']) ? (float)$order['Profit'] : null;
            $params["taxes{$index}"] = isset($order['Taxes']) ? (float)$order['Taxes'] : null;
            $params["magic{$index}"] = isset($order['Magic']) ? (int)$order['Magic'] : null;
            $params["comment{$index}"] = $order['Comment'] ?? null;
            $params["gwopenorder{$index}"] = $order['GwOpenOrder'] ?? null;
            $params["gwcloseorder{$index}"] = $order['GwCloseOrder'] ?? null;
            $params["gwopenprice{$index}"] = isset($order['GwOpenPrice']) ? (float)$order['GwOpenPrice'] : null;
            $params["gwcloseprice{$index}"] = isset($order['GwClosePrice']) ? (float)$order['GwClosePrice'] : null;
            $params["margin{$index}"] = isset($order['Margin']) ? (float)$order['Margin'] : null;
            $params["marginrate{$index}"] = isset($order['MarginRate']) ? (float)$order['MarginRate'] : null;
            $params["created_at{$index}"] = $now;
            $params["updated_at{$index}"] = $now;
        }

        $sql = "INSERT INTO {$this->table} (
            trading_id, trading_login, trading_platforms_key, trading_status,
            symbol_id, symbol, source, digits, cmd, volume, opentime, state,
            ordertype, targetprice, openprice, sl, tp, closetime, expiration,
            reason, commission, commissionagent, storage, closeprice, ask, bid,
            profit, taxes, magic, comment, gwopenorder, gwcloseorder,
            gwopenprice, gwcloseprice, margin, marginrate, created_at, updated_at
        ) VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE
            trading_login = VALUES(trading_login),
            trading_status = VALUES(trading_status),
            symbol_id = VALUES(symbol_id),
            symbol = VALUES(symbol),
            source = VALUES(source),
            digits = VALUES(digits),
            cmd = VALUES(cmd),
            volume = VALUES(volume),
            opentime = VALUES(opentime),
            state = VALUES(state),
            ordertype = VALUES(ordertype),
            targetprice = VALUES(targetprice),
            openprice = VALUES(openprice),
            sl = VALUES(sl),
            tp = VALUES(tp),
            closetime = VALUES(closetime),
            expiration = VALUES(expiration),
            reason = VALUES(reason),
            commission = VALUES(commission),
            commissionagent = VALUES(commissionagent),
            storage = VALUES(storage),
            closeprice = VALUES(closeprice),
            ask = VALUES(ask),
            bid = VALUES(bid),
            profit = VALUES(profit),
            taxes = VALUES(taxes),
            magic = VALUES(magic),
            comment = VALUES(comment),
            gwopenorder = VALUES(gwopenorder),
            gwcloseorder = VALUES(gwcloseorder),
            gwopenprice = VALUES(gwopenprice),
            gwcloseprice = VALUES(gwcloseprice),
            margin = VALUES(margin),
            marginrate = VALUES(marginrate),
            updated_at = VALUES(updated_at)";

        try {
            $this->db->query($sql, $params);
            return count($orders);
        } catch (Exception $e) {
//            error_log('Batch upsert active orders failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 清理某账户在指定 trading_status（0=持仓 / 1=挂单）下不在 $keepTradingIds 中的残留订单。
     * 持仓被平掉或挂单被取消/成交/过期后，MT5 返回的列表不再包含它，本方法把这些旧行删掉，
     * 避免前端仍把它们当成活跃单显示。
     *
     * @param int    $tradingStatus    0=持仓，1=挂单
     * @param string $tradingLogin     MT5 login
     * @param string $platformKey      平台标识（如 mt5）
     * @param array  $keepTradingIds   本次拉到的 trading_id 列表；空数组表示整表清空该账户该状态
     * @return int 删除的行数
     */
    public function pruneStaleActiveOrders($tradingStatus, $tradingLogin, $platformKey, array $keepTradingIds) {
        $params = [
            'trading_login' => $tradingLogin,
            'trading_platforms_key' => $platformKey,
            'trading_status' => (int)$tradingStatus,
        ];

        $sql = "DELETE FROM {$this->table}
                WHERE trading_login = :trading_login
                  AND trading_platforms_key = :trading_platforms_key
                  AND trading_status = :trading_status";

        $keepTradingIds = array_values(array_unique(array_filter(array_map('intval', $keepTradingIds), function ($v) {
            return $v > 0;
        })));

        if (!empty($keepTradingIds)) {
            $placeholders = [];
            foreach ($keepTradingIds as $i => $id) {
                $placeholders[] = ":keep_id{$i}";
                $params["keep_id{$i}"] = $id;
            }
            $sql .= " AND trading_id NOT IN (" . implode(',', $placeholders) . ")";
        }

        try {
            $stmt = $this->db->query($sql, $params);
            return $stmt ? (int)$stmt->rowCount() : 0;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 批量插入或更新订单数据（保留原方法以兼容）
     * 使用ON DUPLICATE KEY UPDATE实现
     *
     * @param array $orders 订单数据数组
     * @param string $tradingLogin 交易账户ID
     * @param string $platformKey 交易平台标识
     * @return int 影响的行数
     */
    public function batchUpsert($orders, $tradingLogin, $platformKey) {
        if (empty($orders)) {
            return 0;
        }

        $now = time();
        $values = [];
        $params = [];

        foreach ($orders as $index => $order) {
            // 根据State判断trading_status
            // State: 0-挂单/未开单，1-开单持仓，2-关单/历史订单
            $state = isset($order['State']) ? (int)$order['State'] : 2;
            $tradingStatus = 2; // 默认历史订单
            if ($state === 0) {
                $tradingStatus = 1; // 挂单/未开单
            } elseif ($state === 1) {
                $tradingStatus = 0; // 开单持仓
            } elseif ($state === 2) {
                $tradingStatus = 2; // 关单/历史订单
            }

            $baseIndex = $index * 40; // 每个订单约40个字段

            $values[] = "(
                :trading_id{$index},
                :trading_login{$index},
                :trading_platforms_key{$index},
                :trading_status{$index},
                :symbol_id{$index},
                :symbol{$index},
                :source{$index},
                :digits{$index},
                :cmd{$index},
                :volume{$index},
                :opentime{$index},
                :state{$index},
                :ordertype{$index},
                :targetprice{$index},
                :openprice{$index},
                :sl{$index},
                :tp{$index},
                :closetime{$index},
                :expiration{$index},
                :reason{$index},
                :commission{$index},
                :commissionagent{$index},
                :storage{$index},
                :closeprice{$index},
                :ask{$index},
                :bid{$index},
                :profit{$index},
                :taxes{$index},
                :magic{$index},
                :comment{$index},
                :gwopenorder{$index},
                :gwcloseorder{$index},
                :gwopenprice{$index},
                :gwcloseprice{$index},
                :margin{$index},
                :marginrate{$index},
                :created_at{$index},
                :updated_at{$index}
            )";

            $params["trading_id{$index}"] = isset($order['Id']) ? (int)$order['Id'] : 0;
            $params["trading_login{$index}"] = $tradingLogin;
            $params["trading_platforms_key{$index}"] = $platformKey;
            $params["trading_status{$index}"] = $tradingStatus;
            $params["symbol_id{$index}"] = isset($order['SymbolId']) ? (int)$order['SymbolId'] : 0;
            $params["symbol{$index}"] = $order['Symbol'] ?? null;
            $params["source{$index}"] = $order['Source'] ?? null;
            $params["digits{$index}"] = isset($order['Digits']) ? (int)$order['Digits'] : 0;
            $params["cmd{$index}"] = isset($order['Cmd']) ? (int)$order['Cmd'] : 0;
            $params["volume{$index}"] = isset($order['Volume']) ? (float)$order['Volume'] : 0.00;
            $params["opentime{$index}"] = isset($order['OpenTime']) ? (int)$order['OpenTime'] : 0;
            $params["state{$index}"] = isset($order['State']) ? (int)$order['State'] : null;
            $params["ordertype{$index}"] = $order['OrderType'] ?? null;
            $params["targetprice{$index}"] = isset($order['TargetPrice']) && $order['TargetPrice'] !== '' ? (float)$order['TargetPrice'] : null;
            $params["openprice{$index}"] = isset($order['OpenPrice']) ? (float)$order['OpenPrice'] : 0.000000;
            $params["sl{$index}"] = isset($order['Sl']) ? (float)$order['Sl'] : null;
            $params["tp{$index}"] = isset($order['Tp']) ? (float)$order['Tp'] : null;
            $params["closetime{$index}"] = isset($order['CloseTime']) && $order['CloseTime'] > 0 ? (int)$order['CloseTime'] : null;
            $params["expiration{$index}"] = isset($order['Expiration']) && $order['Expiration'] > 0 ? (int)$order['Expiration'] : null;
            $params["reason{$index}"] = isset($order['Reason']) ? (int)$order['Reason'] : null;
            $params["commission{$index}"] = isset($order['Commission']) ? (float)$order['Commission'] : 0.00;
            $params["commissionagent{$index}"] = isset($order['CommissionAgent']) ? (float)$order['CommissionAgent'] : null;
            $params["storage{$index}"] = isset($order['Storage']) ? (float)$order['Storage'] : null;
            $params["closeprice{$index}"] = isset($order['ClosePrice']) ? (float)$order['ClosePrice'] : null;
            $params["ask{$index}"] = isset($order['Ask']) ? (float)$order['Ask'] : null;
            $params["bid{$index}"] = isset($order['Bid']) ? (float)$order['Bid'] : null;
            $params["profit{$index}"] = isset($order['Profit']) ? (float)$order['Profit'] : null;
            $params["taxes{$index}"] = isset($order['Taxes']) ? (float)$order['Taxes'] : null;
            $params["magic{$index}"] = isset($order['Magic']) ? (int)$order['Magic'] : null;
            $params["comment{$index}"] = $order['Comment'] ?? null;
            $params["gwopenorder{$index}"] = $order['GwOpenOrder'] ?? null;
            $params["gwcloseorder{$index}"] = $order['GwCloseOrder'] ?? null;
            $params["gwopenprice{$index}"] = isset($order['GwOpenPrice']) ? (float)$order['GwOpenPrice'] : null;
            $params["gwcloseprice{$index}"] = isset($order['GwClosePrice']) ? (float)$order['GwClosePrice'] : null;
            $params["margin{$index}"] = isset($order['Margin']) ? (float)$order['Margin'] : null;
            $params["marginrate{$index}"] = isset($order['MarginRate']) ? (float)$order['MarginRate'] : null;
            $params["created_at{$index}"] = $now;
            $params["updated_at{$index}"] = $now;
        }

        $sql = "INSERT INTO {$this->table} (
            trading_id, trading_login, trading_platforms_key, trading_status,
            symbol_id, symbol, source, digits, cmd, volume, opentime, state,
            ordertype, targetprice, openprice, sl, tp, closetime, expiration,
            reason, commission, commissionagent, storage, closeprice, ask, bid,
            profit, taxes, magic, comment, gwopenorder, gwcloseorder,
            gwopenprice, gwcloseprice, margin, marginrate, created_at, updated_at
        ) VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE
            trading_login = VALUES(trading_login),
            trading_status = VALUES(trading_status),
            symbol_id = VALUES(symbol_id),
            symbol = VALUES(symbol),
            source = VALUES(source),
            digits = VALUES(digits),
            cmd = VALUES(cmd),
            volume = VALUES(volume),
            opentime = VALUES(opentime),
            state = VALUES(state),
            ordertype = VALUES(ordertype),
            targetprice = VALUES(targetprice),
            openprice = VALUES(openprice),
            sl = VALUES(sl),
            tp = VALUES(tp),
            closetime = VALUES(closetime),
            expiration = VALUES(expiration),
            reason = VALUES(reason),
            commission = VALUES(commission),
            commissionagent = VALUES(commissionagent),
            storage = VALUES(storage),
            closeprice = VALUES(closeprice),
            ask = VALUES(ask),
            bid = VALUES(bid),
            profit = VALUES(profit),
            taxes = VALUES(taxes),
            magic = VALUES(magic),
            comment = VALUES(comment),
            gwopenorder = VALUES(gwopenorder),
            gwcloseorder = VALUES(gwcloseorder),
            gwopenprice = VALUES(gwopenprice),
            gwcloseprice = VALUES(gwcloseprice),
            margin = VALUES(margin),
            marginrate = VALUES(marginrate),
            updated_at = VALUES(updated_at)";

        try {
            $this->db->query($sql, $params);
            return count($orders);
        } catch (Exception $e) {
            error_log('Batch upsert orders failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 分页查询订单历史
     *
     * @param string $tradingLogin 交易账户ID
     * @param string $platformKey 交易平台标识
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $sort 排序字段
     * @param string $sortOrder 排序方向
     * @return array
     */
    public function getOrderHistory($tradingLogin, $platformKey, $filters = [], $page = 1, $pageSize = 20, $sort = 'Id', $sortOrder = 'DESC') {
        $offset = ($page - 1) * $pageSize;
        $where = ['trading_login = :trading_login', 'trading_platforms_key = :platform_key'];
        $params = [
            'trading_login' => $tradingLogin,
            'platform_key' => $platformKey
        ];

        // 筛选条件
        if (isset($filters['trading_status'])) {
            $where[] = 'trading_status = :trading_status';
            $params['trading_status'] = $filters['trading_status'];
        }

        if (isset($filters['cmd'])) {
            $where[] = 'cmd = :cmd';
            $params['cmd'] = $filters['cmd'];
        }

        if (isset($filters['keywords']) && !empty($filters['keywords'])) {
            $where[] = '(symbol LIKE :keywords OR comment LIKE :keywords)';
            $params['keywords'] = '%' . $filters['keywords'] . '%';
        }

        if (isset($filters['periodFrom']) && !empty($filters['periodFrom'])) {
            $where[] = 'closetime >= :period_from';
            $params['period_from'] = strtotime($filters['periodFrom']);
        }

        if (isset($filters['periodTo']) && !empty($filters['periodTo'])) {
            $where[] = 'closetime <= :period_to';
            $params['period_to'] = strtotime($filters['periodTo']) + 86400 - 1; // 包含当天
        }

        // 字段映射（前端使用的字段名 -> 数据库字段名）
        $fieldMap = [
            'Id' => 'trading_id',
            'Symbol' => 'symbol',
            'Cmd' => 'cmd',
            'Volume' => 'volume',
            'OpenPrice' => 'openprice',
            'ClosePrice' => 'closeprice',
            'Profit' => 'profit',
            'CloseTime' => 'closetime'
        ];

        $orderBy = 'closetime DESC';
        if (isset($fieldMap[$sort])) {
            $orderBy = $fieldMap[$sort] . ' ' . strtoupper($sortOrder);
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // 查询数据
        // LIMIT和OFFSET不能作为参数绑定，需要直接拼接到SQL中
        $pageSize = (int)$pageSize;
        $offset = (int)$offset;

        $sql = "SELECT
            trading_id as Id,
            trading_login as Login,
            trading_platforms_key,
            symbol_id as SymbolId,
            symbol as Symbol,
            source as Source,
            digits as Digits,
            cmd as Cmd,
            volume as Volume,
            opentime as OpenTime,
            state as State,
            ordertype as OrderType,
            targetprice as TargetPrice,
            openprice as OpenPrice,
            sl as Sl,
            tp as Tp,
            closetime as CloseTime,
            expiration as Expiration,
            reason as Reason,
            commission as Commission,
            commissionagent as CommissionAgent,
            storage as Storage,
            closeprice as ClosePrice,
            ask as Ask,
            bid as Bid,
            profit as Profit,
            taxes as Taxes,
            magic as Magic,
            comment as Comment,
            gwopenorder as GwOpenOrder,
            gwcloseorder as GwCloseOrder,
            gwopenprice as GwOpenPrice,
            gwcloseprice as GwClosePrice,
            margin as Margin,
            marginrate as MarginRate
        FROM {$this->table}
        {$whereClause}
        ORDER BY {$orderBy}
        LIMIT {$pageSize} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'hasMore' => $total > ($page * $pageSize)
        ];
    }

    /**
     * 分页查询订单历史（支持多平台、多登录账户）
     *
     * @param array $logins 交易账户ID数组
     * @param array $platformKeys 交易平台标识数组
     * @param array $filters 筛选条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $sort 排序字段
     * @param string $sortOrder 排序方向
     * @return array
     */
    public function getOrderHistoryByLogins($logins, $platformKeys, $filters = [], $page = 1, $pageSize = 20, $sort = 'Id', $sortOrder = 'DESC') {
        if (empty($logins) || empty($platformKeys)) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'pageSize' => $pageSize,
                'hasMore' => false
            ];
        }

        $offset = ($page - 1) * $pageSize;
        list($whereClause, $params) = $this->buildLoginsWhere($logins, $platformKeys, $filters);

        // 字段映射（前端使用的字段名 -> 数据库字段名）
        $fieldMap = [
            'Id' => 'trading_id',
            'Symbol' => 'symbol',
            'Cmd' => 'cmd',
            'Volume' => 'volume',
            'OpenPrice' => 'openprice',
            'ClosePrice' => 'closeprice',
            'Profit' => 'profit',
            'CloseTime' => 'closetime',
            'OpenTime' => 'opentime'
        ];

        $orderBy = 'closetime DESC';
        if (isset($fieldMap[$sort])) {
            $orderBy = $fieldMap[$sort] . ' ' . strtoupper($sortOrder);
        }

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $totalResult = $this->db->fetchOne($countSql, $params);
        $total = (int)($totalResult['total'] ?? 0);

        // 查询数据
        // LIMIT和OFFSET不能作为参数绑定，需要直接拼接到SQL中
        $pageSize = (int)$pageSize;
        $offset = (int)$offset;

        $sql = "SELECT
            trading_id as Id,
            trading_login as Login,
            trading_platforms_key,
            symbol_id as SymbolId,
            symbol as Symbol,
            source as Source,
            digits as Digits,
            cmd as Cmd,
            volume as Volume,
            opentime as OpenTime,
            state as State,
            ordertype as OrderType,
            targetprice as TargetPrice,
            openprice as OpenPrice,
            sl as Sl,
            tp as Tp,
            closetime as CloseTime,
            expiration as Expiration,
            reason as Reason,
            commission as Commission,
            commissionagent as CommissionAgent,
            storage as Storage,
            closeprice as ClosePrice,
            ask as Ask,
            bid as Bid,
            profit as Profit,
            taxes as Taxes,
            magic as Magic,
            comment as Comment,
            gwopenorder as GwOpenOrder,
            gwcloseorder as GwCloseOrder,
            gwopenprice as GwOpenPrice,
            gwcloseprice as GwClosePrice,
            margin as Margin,
            marginrate as MarginRate
        FROM {$this->table}
        {$whereClause}
        ORDER BY {$orderBy}
        LIMIT {$pageSize} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'hasMore' => $total > ($page * $pageSize)
        ];
    }

    /**
     * Build the WHERE clause for multi-account, multi-platform order queries.
     * Shared by getOrderHistoryByLogins and getOrderTotalsByLogins so the list and the
     * totals are always computed over exactly the same rows.
     *
     * @param array $logins trading account logins
     * @param array $platformKeys trading platform keys
     * @param array $filters query filters
     * @return array [whereClause, params]
     */
    private function buildLoginsWhere($logins, $platformKeys, $filters = []) {
        $where = [];
        $params = [];

        $loginPlaceholders = [];
        foreach ($logins as $index => $login) {
            $loginPlaceholders[] = ':login_' . $index;
            $params['login_' . $index] = $login;
        }

        $platformPlaceholders = [];
        foreach ($platformKeys as $index => $platformKey) {
            $platformPlaceholders[] = ':platform_' . $index;
            $params['platform_' . $index] = $platformKey;
        }

        $where[] = 'trading_login IN (' . implode(', ', $loginPlaceholders) . ')';
        $where[] = 'trading_platforms_key IN (' . implode(', ', $platformPlaceholders) . ')';

        if (isset($filters['trading_status'])) {
            $where[] = 'trading_status = :trading_status';
            $params['trading_status'] = $filters['trading_status'];
        }

        if (isset($filters['cmd'])) {
            $where[] = 'cmd = :cmd';
            $params['cmd'] = $filters['cmd'];
        }

        // Filter by trade side rather than one exact cmd: buy/sell each cover the
        // market order plus their limit/stop variants (cmd 2-5, 8-9).
        if (isset($filters['cmd_in']) && is_array($filters['cmd_in']) && !empty($filters['cmd_in'])) {
            $cmdPlaceholders = [];
            foreach (array_values($filters['cmd_in']) as $index => $cmdValue) {
                $cmdPlaceholders[] = ':cmd_in_' . $index;
                $params['cmd_in_' . $index] = (int)$cmdValue;
            }
            $where[] = 'cmd IN (' . implode(', ', $cmdPlaceholders) . ')';
        }

        if (isset($filters['keywords']) && !empty($filters['keywords'])) {
            $where[] = '(symbol LIKE :keywords OR comment LIKE :keywords)';
            $params['keywords'] = '%' . $filters['keywords'] . '%';
        }

        // Open positions and pending orders have closetime = 0, so a date range must
        // filter on opentime for those; closed orders filter on closetime.
        $dateField = (isset($filters['date_field']) && $filters['date_field'] === 'opentime') ? 'opentime' : 'closetime';

        if (isset($filters['periodFrom']) && !empty($filters['periodFrom'])) {
            $where[] = "{$dateField} >= :period_from";
            $params['period_from'] = strtotime($filters['periodFrom']);
        }

        if (isset($filters['periodTo']) && !empty($filters['periodTo'])) {
            $where[] = "{$dateField} <= :period_to";
            $params['period_to'] = strtotime($filters['periodTo']) + 86400 - 1; // include the whole end day
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * Aggregate lots and profit across accounts and platforms. Covers the entire filtered
     * result set and is unaffected by pagination.
     *
     * @param array $logins trading account logins
     * @param array $platformKeys trading platform keys
     * @param array $filters same filters accepted by getOrderHistoryByLogins
     * @return array
     */
    public function getOrderTotalsByLogins($logins, $platformKeys, $filters = []) {
        $empty = [
            'totalOrders' => 0,
            'totalLots' => 0.0,
            'grossProfit' => 0.0,
            'totalCommission' => 0.0,
            'totalSwap' => 0.0,
            'totalTaxes' => 0.0,
            'netProfit' => 0.0
        ];

        if (empty($logins) || empty($platformKeys)) {
            return $empty;
        }

        list($whereClause, $params) = $this->buildLoginsWhere($logins, $platformKeys, $filters);

        $sql = "SELECT
            COUNT(*) as totalOrders,
            COALESCE(SUM(volume), 0) as totalLots,
            COALESCE(SUM(profit), 0) as grossProfit,
            COALESCE(SUM(commission), 0) as totalCommission,
            COALESCE(SUM(storage), 0) as totalSwap,
            COALESCE(SUM(taxes), 0) as totalTaxes
        FROM {$this->table}
        {$whereClause}";

        $row = $this->db->fetchOne($sql, $params);
        if (!$row) {
            return $empty;
        }

        $grossProfit = (float)($row['grossProfit'] ?? 0);
        $totalCommission = (float)($row['totalCommission'] ?? 0);
        $totalSwap = (float)($row['totalSwap'] ?? 0);
        $totalTaxes = (float)($row['totalTaxes'] ?? 0);

        return [
            'totalOrders' => (int)($row['totalOrders'] ?? 0),
            // volume is lots * 100, so the total must be converted back to lots
            'totalLots' => (float)($row['totalLots'] ?? 0) / self::VOLUME_PER_LOT,
            'grossProfit' => $grossProfit,
            'totalCommission' => $totalCommission,
            'totalSwap' => $totalSwap,
            'totalTaxes' => $totalTaxes,
            // Net P/L = gross + commission + swap + taxes (commission and swap are already negative)
            'netProfit' => $grossProfit + $totalCommission + $totalSwap + $totalTaxes
        ];
    }
}
