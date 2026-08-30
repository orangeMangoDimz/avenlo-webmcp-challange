<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../controllers/DailyReportController.php';

/**
 * The Daily Report page lists one row per sales user for a chosen day. The controller has to
 * zero-fill sales users with no activity and compare the monthly KPI against month-to-date
 * net deposit rather than the single day. The shared SQL and date parsing it delegates to are
 * covered by SalesPerformanceMetricsTest.
 */
class DailyReportSummaryTest extends TestCase
{
    /** Building the payload needs no database connection */
    private function controller(): DailyReportController
    {
        return (new ReflectionClass(DailyReportController::class))->newInstanceWithoutConstructor();
    }

    private function call(string $method, array $args = [])
    {
        $reflection = new ReflectionMethod(DailyReportController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->controller(), $args);
    }

    private function access(bool $canEditKpi = true, bool $canViewAllSales = true): array
    {
        return [
            'adminUserId' => 7,
            'canView' => true,
            'canViewAllSales' => $canViewAllSales,
            'canEditKpi' => $canEditKpi,
        ];
    }

    public function testSalesWithoutActivityStillGetsARowOfZeros(): void
    {
        $salesUsers = [['id' => '11', 'username' => 'jane', 'fullName' => 'Jane Tan', 'email' => 'jane@x.io', 'status' => 'active']];

        $payload = $this->call('buildPayload', [
            $salesUsers, [], [], [], [], '2026-08-06', '2026-08', 600, $this->access(),
        ]);

        $this->assertCount(1, $payload['rows']);
        $row = $payload['rows'][0];
        $this->assertSame(11, $row['salesId']);
        $this->assertSame('Jane Tan', $row['salesName']);
        $this->assertSame(0.0, $row['deposits']);
        $this->assertSame(0.0, $row['netDeposit']);
        $this->assertSame(0, $row['newLeads']);
        $this->assertNull($row['kpiTarget']);
        $this->assertNull($row['kpiAchievementRate']);
        $this->assertSame('2026-08', $payload['month']);
        $this->assertSame('UTC+10:00', $payload['timezone']['label']);
        $this->assertTrue($payload['permissions']['canEditKpi']);
    }

    public function testUsernameIsUsedWhenTheSalesUserHasNoFullName(): void
    {
        $salesUsers = [['id' => '4', 'username' => 'ali', 'fullName' => '  ', 'email' => 'ali@x.io', 'status' => 'active']];

        $payload = $this->call('buildPayload', [
            $salesUsers, [], [], [], [], '2026-08-06', '2026-08', 600, $this->access(),
        ]);

        $this->assertSame('ali', $payload['rows'][0]['salesName']);
    }

    public function testAchievementComparesMonthlyKpiWithMonthToDateNotTheSingleDay(): void
    {
        $salesUsers = [['id' => '11', 'username' => 'jane', 'fullName' => 'Jane Tan', 'email' => 'jane@x.io', 'status' => 'active']];
        $dayTotals = [
            ['salesId' => '11', 'transactionType' => 'deposit', 'totalAmount' => '120000', 'transactionCount' => '14'],
            ['salesId' => '11', 'transactionType' => 'withdrawal', 'totalAmount' => '30000', 'transactionCount' => '4'],
        ];
        $monthTotals = [
            ['salesId' => '11', 'transactionType' => 'deposit', 'totalAmount' => '500000', 'transactionCount' => '60'],
            ['salesId' => '11', 'transactionType' => 'withdrawal', 'totalAmount' => '100000', 'transactionCount' => '12'],
        ];
        $registrations = [['salesId' => '11', 'newLeads' => '9', 'newClients' => '3']];
        $kpiMap = [11 => ['salesId' => '11', 'kpiTarget' => '800000.00', 'updatedAt' => '2026-08-01 09:00:00', 'updatedByName' => 'Manager']];

        $payload = $this->call('buildPayload', [
            $salesUsers, $dayTotals, $monthTotals, $registrations, $kpiMap, '2026-08-06', '2026-08', 600, $this->access(),
        ]);
        $row = $payload['rows'][0];

        $this->assertSame(90000.0, $row['netDeposit']);
        $this->assertSame(14, $row['depositCount']);
        $this->assertSame(9, $row['newLeads']);
        $this->assertSame(3, $row['newClients']);
        $this->assertSame(400000.0, $row['monthToDateNetDeposit']);
        // 400k month-to-date against an 800k monthly target, not 90k against 800k
        $this->assertSame(50.0, $row['kpiAchievementRate']);
        $this->assertSame('Manager', $row['kpiUpdatedByName']);

        $this->assertSame(90000.0, $payload['summary']['netDeposit']);
        $this->assertSame(800000.0, $payload['summary']['kpiTarget']);
        $this->assertSame(50.0, $payload['summary']['kpiAchievementRate']);
    }

    public function testTotalsAddUpAcrossSalesUsers(): void
    {
        $salesUsers = [
            ['id' => '11', 'username' => 'jane', 'fullName' => 'Jane Tan', 'email' => 'jane@x.io', 'status' => 'active'],
            ['id' => '12', 'username' => 'ali', 'fullName' => 'Ali Rahman', 'email' => 'ali@x.io', 'status' => 'active'],
        ];
        $dayTotals = [
            ['salesId' => '11', 'transactionType' => 'deposit', 'totalAmount' => '100', 'transactionCount' => '1'],
            ['salesId' => '12', 'transactionType' => 'deposit', 'totalAmount' => '400', 'transactionCount' => '2'],
            ['salesId' => '12', 'transactionType' => 'withdrawal', 'totalAmount' => '150', 'transactionCount' => '1'],
        ];
        $registrations = [
            ['salesId' => '11', 'newLeads' => '2', 'newClients' => '1'],
            ['salesId' => '12', 'newLeads' => '5', 'newClients' => '0'],
        ];

        $payload = $this->call('buildPayload', [
            $salesUsers, $dayTotals, $dayTotals, $registrations, [], '2026-08-06', '2026-08', 600, $this->access(),
        ]);

        $this->assertSame(500.0, $payload['summary']['deposits']);
        $this->assertSame(150.0, $payload['summary']['withdrawals']);
        $this->assertSame(350.0, $payload['summary']['netDeposit']);
        $this->assertSame(7, $payload['summary']['newLeads']);
        $this->assertSame(1, $payload['summary']['newClients']);
        // No KPI saved for either sales user, so there is no rate to report
        $this->assertNull($payload['summary']['kpiAchievementRate']);
    }

    public function testAchievementRateIsNullWithoutAUsableTarget(): void
    {
        // Dividing by a missing or zero target would blow up or report a meaningless rate
        $this->assertNull($this->call('achievementRate', [500.0, null]));
        $this->assertNull($this->call('achievementRate', [500.0, 0]));
        $this->assertSame(50.0, $this->call('achievementRate', [500.0, 1000]));
    }
}
