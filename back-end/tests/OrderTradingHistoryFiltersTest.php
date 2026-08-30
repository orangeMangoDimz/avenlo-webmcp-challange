<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Order.php';

/**
 * The admin client detail -> Trading History tab relies on Order's multi-account query
 * conditions. Open positions and pending orders always have closetime = 0, so filtering a
 * date range on closetime would match nothing. The WHERE builder shared by the list and the
 * totals must therefore switch the time column based on order status.
 */
class OrderTradingHistoryFiltersTest extends TestCase
{
    /**
     * Building the query needs no database connection, so bypass BaseModel's constructor
     */
    private function buildWhere(array $filters): array
    {
        $order = (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Order::class, 'buildLoginsWhere');
        $method->setAccessible(true);

        return $method->invoke($order, ['1001', '1002'], ['mt5'], $filters);
    }

    public function testClosedOrdersFilterByCloseTime(): void
    {
        list($whereClause, $params) = $this->buildWhere([
            'trading_status' => 2,
            'periodFrom' => '2026-01-01',
            'periodTo' => '2026-01-31'
        ]);

        $this->assertStringContainsString('closetime >= :period_from', $whereClause);
        $this->assertStringContainsString('closetime <= :period_to', $whereClause);
        $this->assertStringNotContainsString('opentime', $whereClause);
        // The end date must cover the whole final day
        $this->assertSame(strtotime('2026-01-31') + 86399, $params['period_to']);
    }

    public function testOpenPositionsFilterByOpenTimeInstead(): void
    {
        list($whereClause) = $this->buildWhere([
            'trading_status' => 0,
            'date_field' => 'opentime',
            'periodFrom' => '2026-01-01'
        ]);

        $this->assertStringContainsString('opentime >= :period_from', $whereClause);
        $this->assertStringNotContainsString('closetime', $whereClause);
    }

    public function testLoginsAndPlatformsAreAlwaysParameterBound(): void
    {
        list($whereClause, $params) = $this->buildWhere([
            'trading_status' => 2,
            'keywords' => "x' OR 1=1 --",
            'cmd' => 1
        ]);

        $this->assertStringContainsString('trading_login IN (:login_0, :login_1)', $whereClause);
        $this->assertStringContainsString('trading_platforms_key IN (:platform_0)', $whereClause);
        $this->assertSame('1001', $params['login_0']);
        $this->assertSame('mt5', $params['platform_0']);
        // Keywords appear only as a bound parameter, never concatenated into SQL
        $this->assertStringNotContainsString('1=1', $whereClause);
        $this->assertSame("%x' OR 1=1 --%", $params['keywords']);
        $this->assertSame(1, $params['cmd']);
    }

    public function testSideFilterMatchesLimitAndStopVariantsToo(): void
    {
        // Pending orders only ever carry cmd 2-5 / 8-9, so a side filter that matched just
        // cmd 0 or 1 would return nothing on the pending tab.
        list($whereClause, $params) = $this->buildWhere([
            'trading_status' => 1,
            'cmd_in' => [1, 3, 5, 9]
        ]);

        $this->assertStringContainsString('cmd IN (:cmd_in_0, :cmd_in_1, :cmd_in_2, :cmd_in_3)', $whereClause);
        $this->assertSame([1, 3, 5, 9], [$params['cmd_in_0'], $params['cmd_in_1'], $params['cmd_in_2'], $params['cmd_in_3']]);
        // The exact-cmd filter must not also fire
        $this->assertStringNotContainsString('cmd = :cmd', $whereClause);
    }

    public function testTotalsAreZeroWithoutTradingAccounts(): void
    {
        $order = (new ReflectionClass(Order::class))->newInstanceWithoutConstructor();

        // With no trading accounts it must not hit the database, just return zeroed totals
        $totals = $order->getOrderTotalsByLogins([], [], ['trading_status' => 2]);

        $this->assertSame(0, $totals['totalOrders']);
        $this->assertSame(0.0, $totals['totalLots']);
        $this->assertSame(0.0, $totals['netProfit']);
    }
}
