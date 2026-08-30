<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/SalesPerformanceMetrics.php';

/**
 * Shared aggregates behind the Daily Report page and the Sales Dashboard. The parts worth
 * pinning without a database: only completed money counts, sales ids are always bound as
 * parameters, and a bad date / month / timezone can never reach SQL.
 */
class SalesPerformanceMetricsTest extends TestCase
{
    public function testOnlyCompletedTransactionsAreCounted(): void
    {
        // A pending or rejected deposit must never inflate a sales figure
        list($sql, $params) = SalesPerformanceMetrics::buildTransactionTotalsQuery(
            [11, 12], '2026-08-01', '2026-08-06', 600
        );

        $this->assertStringContainsString("t.status = 'completed'", $sql);
        $this->assertStringContainsString("t.transactionType IN ('deposit', 'withdrawal')", $sql);
        $this->assertStringContainsString('sb.salesId IN (:sid_0, :sid_1)', $sql);
        $this->assertSame('2026-08-01', $params['startDate']);
        $this->assertSame('2026-08-06', $params['endDate']);
        $this->assertSame([11, 12], [$params['sid_0'], $params['sid_1']]);
    }

    public function testSalesIdsAreAlwaysBoundAsParameters(): void
    {
        list($inClause, $params) = SalesPerformanceMetrics::buildIdInClause(['11', '9 OR 1=1', 12], 'sid');

        $this->assertSame(':sid_0, :sid_1, :sid_2', $inClause);
        $this->assertSame([11, 9, 12], [$params['sid_0'], $params['sid_1'], $params['sid_2']]);
    }

    public function testDepositAndWithdrawalRowsFoldIntoOneEntryPerSales(): void
    {
        $indexed = SalesPerformanceMetrics::indexTransactionTotals([
            ['salesId' => '11', 'transactionType' => 'deposit', 'totalAmount' => '120000', 'transactionCount' => '14'],
            ['salesId' => '11', 'transactionType' => 'withdrawal', 'totalAmount' => '30000', 'transactionCount' => '4'],
            ['salesId' => '12', 'transactionType' => 'deposit', 'totalAmount' => '500', 'transactionCount' => '1'],
        ]);

        $this->assertSame(120000.0, $indexed[11]['deposits']);
        $this->assertSame(30000.0, $indexed[11]['withdrawals']);
        $this->assertSame(14, $indexed[11]['depositCount']);
        $this->assertSame(500.0, $indexed[12]['deposits']);
        // A sales user with no withdrawal still reports zero, never a missing key
        $this->assertSame(0.0, $indexed[12]['withdrawals']);
    }

    public function testOnlyRealCalendarDatesAndMonthsAreAccepted(): void
    {
        $this->assertSame('2026-08-06', SalesPerformanceMetrics::parseDate('2026-08-06'));
        $this->assertNull(SalesPerformanceMetrics::parseDate('2026-02-30'));
        $this->assertNull(SalesPerformanceMetrics::parseDate('06/08/2026'));
        $this->assertNull(SalesPerformanceMetrics::parseDate("2026-08-06' OR 1=1 --"));
        $this->assertNull(SalesPerformanceMetrics::parseDate(null));

        $this->assertSame('2026-08', SalesPerformanceMetrics::parseMonth('2026-08'));
        $this->assertNull(SalesPerformanceMetrics::parseMonth('2026-13'));
        $this->assertNull(SalesPerformanceMetrics::parseMonth('2026-08-06'));
    }

    public function testMonthBoundsCoverShortAndLeapMonths(): void
    {
        $this->assertSame(['2026-08-01', '2026-08-31'], SalesPerformanceMetrics::monthBounds('2026-08'));
        $this->assertSame(['2026-02-01', '2026-02-28'], SalesPerformanceMetrics::monthBounds('2026-02'));
        // 2028 is a leap year, so February must stretch to the 29th
        $this->assertSame(['2028-02-01', '2028-02-29'], SalesPerformanceMetrics::monthBounds('2028-02'));
    }

    public function testTimezoneOffsetFallsBackToUtcPlus10WhenMissingOrOutOfRange(): void
    {
        $this->assertSame(600, SalesPerformanceMetrics::resolveTzOffsetMinutes(null));
        $this->assertSame(600, SalesPerformanceMetrics::resolveTzOffsetMinutes('not-a-number'));
        $this->assertSame(600, SalesPerformanceMetrics::resolveTzOffsetMinutes(5000));
        $this->assertSame(600, SalesPerformanceMetrics::resolveTzOffsetMinutes(-9999));
        $this->assertSame(-300, SalesPerformanceMetrics::resolveTzOffsetMinutes('-300'));
        $this->assertSame('UTC-05:30', SalesPerformanceMetrics::offsetLabel(-330));
        $this->assertSame('UTC+10:00', SalesPerformanceMetrics::offsetLabel(600));
    }
}
