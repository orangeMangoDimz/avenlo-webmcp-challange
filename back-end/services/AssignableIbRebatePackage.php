<?php

class AssignableIbRebatePackage
{
    public static function fromRuleRows(array $rows): array
    {
        $packages = [];
        foreach ($rows as $row) {
            $ibPartnerId = (int) ($row['ibPartnerId'] ?? 0);
            if ($ibPartnerId <= 0 || isset($packages[$ibPartnerId])) {
                continue;
            }
            $packages[$ibPartnerId] = [
                'ibPartnerId' => $ibPartnerId,
                'ibCode' => isset($row['ibCode']) ? (string) $row['ibCode'] : '',
                'ruleId' => (int) ($row['ruleId'] ?? 0),
            ];
        }
        return array_values($packages);
    }
}
