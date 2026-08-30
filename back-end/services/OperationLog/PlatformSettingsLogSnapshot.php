<?php
/**
 * 平台设置 — 保存前后快照与差异对比
 */

class PlatformSettingsLogSnapshot {
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function tradingGroupFromRow(array $row) {
        $scale = $row['scale'] ?? null;
        if ($scale !== null && $scale !== '') {
            $scale = (string) (float) $scale;
        } else {
            $scale = '';
        }

        return [
            'name' => trim((string) ($row['name'] ?? '')),
            'platformKey' => trim((string) ($row['trading_platforms_key'] ?? '')),
            'label' => trim((string) ($row['label'] ?? '')),
            'unit' => $row['unit'] === null || $row['unit'] === '' ? '' : trim((string) $row['unit']),
            'scale' => $scale,
        ];
    }

    /**
     * @param array<string,mixed> $beforePayload 前端弹窗保存前快照（operationLogGroupBefore）
     * @param array<string,mixed> $dbRow
     * @return array<string,mixed>
     */
    public static function tradingGroupBeforeFromRequest(array $beforePayload, array $dbRow) {
        $scale = $beforePayload['scale'] ?? $dbRow['scale'] ?? null;
        if ($scale !== null && $scale !== '') {
            $scale = (string) (float) $scale;
        } else {
            $scale = '';
        }

        return [
            'name' => trim((string) ($dbRow['name'] ?? '')),
            'platformKey' => trim((string) ($dbRow['trading_platforms_key'] ?? '')),
            'label' => trim((string) ($beforePayload['label'] ?? $dbRow['label'] ?? '')),
            'unit' => array_key_exists('unit', $beforePayload)
                ? ($beforePayload['unit'] === null || $beforePayload['unit'] === '' ? '' : trim((string) $beforePayload['unit']))
                : ($dbRow['unit'] === null || $dbRow['unit'] === '' ? '' : trim((string) $dbRow['unit'])),
            'scale' => $scale,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function tradingGroupFingerprints(array $state) {
        return [
            'label' => (string) ($state['label'] ?? ''),
            'unit' => (string) ($state['unit'] ?? ''),
            'scale' => (string) ($state['scale'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function platformAccountFromRow(array $row) {
        $mode = trim((string) ($row['passwordMode'] ?? 'random'));
        return [
            'platformKey' => trim((string) ($row['platformKey'] ?? '')),
            'platformName' => trim((string) ($row['displayName'] ?? $row['platformKey'] ?? '')),
            'accountLimit' => (int) ($row['accountLimit'] ?? 1),
            'passwordMode' => $mode === 'manual' ? 'manual' : 'random',
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function platformAccountFingerprints(array $state) {
        return [
            'accountLimit' => (string) (int) ($state['accountLimit'] ?? 0),
            'passwordMode' => (string) ($state['passwordMode'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function leverageFromRow(array $row) {
        return [
            'platformKey' => trim((string) ($row['platformKey'] ?? '')),
            'platformName' => trim((string) ($row['platformName'] ?? $row['platformKey'] ?? '')),
            'leverageValue' => trim((string) ($row['leverageValue'] ?? '')),
            'displayLabel' => trim((string) ($row['displayLabel'] ?? '')),
            'riskNote' => trim((string) ($row['riskNote'] ?? '')),
            'displayOrder' => (string) (int) ($row['displayOrder'] ?? 0),
            'isEnabled' => !empty($row['isEnabled']),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function leverageFingerprints(array $state) {
        return [
            'leverageValue' => (string) ($state['leverageValue'] ?? ''),
            'displayLabel' => (string) ($state['displayLabel'] ?? ''),
            'riskNote' => (string) ($state['riskNote'] ?? ''),
            'displayOrder' => (string) ($state['displayOrder'] ?? '0'),
            'isEnabled' => !empty($state['isEnabled']) ? '1' : '0',
        ];
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @param string[] $order
     * @return string[]
     */
    public static function changedKeys(array $before, array $after, array $order) {
        $changed = [];
        foreach ($order as $key) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
