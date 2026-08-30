<?php

require_once __DIR__ . '/FivePayDepositReconciliationService.php';
require_once __DIR__ . '/XLinkDepositReconciliationService.php';

/**
 * Shared PSP deposit reconciliation dispatcher for Swoole.
 *
 * Each gateway keeps its own adapter. This service only merges their results
 * into one background-job summary.
 */
class PspDepositReconciliationService
{
    /**
     * @return array{
     *   success:bool,
     *   discovered:int,
     *   processed:int,
     *   changed:int,
     *   errors:int,
     *   adapters:array<string,array{success:bool,discovered:int,processed:int,changed:int,errors:int}>
     * }
     */
    public function run(): array
    {
        $adapters = [
            '5pay' => new FivePayDepositReconciliationService(),
            'xlink' => new XLinkDepositReconciliationService(),
        ];

        $merged = [
            'success' => true,
            'discovered' => 0,
            'processed' => 0,
            'changed' => 0,
            'errors' => 0,
            'adapters' => [],
        ];

        foreach ($adapters as $gatewayKey => $adapter) {
            $result = $adapter->run();
            $merged['adapters'][$gatewayKey] = [
                'success' => !empty($result['success']),
                'discovered' => (int)($result['discovered'] ?? 0),
                'processed' => (int)($result['processed'] ?? 0),
                'changed' => (int)($result['changed'] ?? 0),
                'errors' => (int)($result['errors'] ?? 0),
            ];
            $merged['discovered'] += $merged['adapters'][$gatewayKey]['discovered'];
            $merged['processed'] += $merged['adapters'][$gatewayKey]['processed'];
            $merged['changed'] += $merged['adapters'][$gatewayKey]['changed'];
            $merged['errors'] += $merged['adapters'][$gatewayKey]['errors'];
            if (empty($result['success'])) {
                $merged['success'] = false;
            }
        }

        return $merged;
    }
}
