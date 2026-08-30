<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/AssignableIbRebatePackage.php';

class AssignableIbRebatePackageTest extends TestCase
{
    public function testGroupsRulesByIbCodeAndKeepsFirstRuleAsPointer(): void
    {
        $rows = [
            [
                'ruleId' => 10,
                'ruleName' => 'Standard FX $10',
                'ibPartnerId' => 40,
                'ibCode' => 'IB-2026-040',
            ],
            [
                'ruleId' => 11,
                'ruleName' => 'Standard Metal $10',
                'ibPartnerId' => 40,
                'ibCode' => 'IB-2026-040',
            ],
            [
                'ruleId' => 61,
                'ruleName' => 'Ultra Metal and Fx $2 (MT4)',
                'ibPartnerId' => 236,
                'ibCode' => 'IB-2026-236',
            ],
        ];

        $packages = AssignableIbRebatePackage::fromRuleRows($rows);

        $this->assertCount(2, $packages);
        $this->assertSame(40, $packages[0]['ibPartnerId']);
        $this->assertSame('IB-2026-040', $packages[0]['ibCode']);
        $this->assertSame(10, $packages[0]['ruleId']);
        $this->assertSame(236, $packages[1]['ibPartnerId']);
        $this->assertSame('IB-2026-236', $packages[1]['ibCode']);
        $this->assertSame(61, $packages[1]['ruleId']);
    }

    public function testSkipsRowsWithoutIbPartner(): void
    {
        $packages = AssignableIbRebatePackage::fromRuleRows([
            ['ruleId' => 1, 'ibPartnerId' => 0, 'ibCode' => ''],
            ['ruleId' => 2, 'ibPartnerId' => 40, 'ibCode' => 'IB-2026-040'],
        ]);

        $this->assertCount(1, $packages);
        $this->assertSame(40, $packages[0]['ibPartnerId']);
    }
}
