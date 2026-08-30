<?php
/**
 * 邮件模板 — 保存前后快照与差异对比
 */

class EmailTemplatesLogSnapshot {
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function fromDbRow(array $row) {
        $variables = $row['variables'] ?? [];
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($variables)) {
            $variables = [];
        }

        return [
            'templateKey' => trim((string) ($row['templateKey'] ?? '')),
            'templateName' => trim((string) ($row['templateName'] ?? '')),
            'category' => trim((string) ($row['category'] ?? '')),
            'emailSubject' => trim((string) ($row['emailSubject'] ?? '')),
            'emailBody' => (string) ($row['emailBody'] ?? ''),
            'recipientType' => trim((string) ($row['recipientType'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'variables' => $variables,
            'isActive' => !empty($row['isActive']),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function fingerprints(array $state) {
        $variables = $state['variables'] ?? [];
        if (!is_array($variables)) {
            $variables = [];
        }
        ksort($variables);

        return [
            'templateKey' => (string) ($state['templateKey'] ?? ''),
            'templateName' => (string) ($state['templateName'] ?? ''),
            'category' => (string) ($state['category'] ?? ''),
            'emailSubject' => (string) ($state['emailSubject'] ?? ''),
            'emailBody' => (string) ($state['emailBody'] ?? ''),
            'recipientType' => (string) ($state['recipientType'] ?? ''),
            'description' => (string) ($state['description'] ?? ''),
            'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
            'isActive' => !empty($state['isActive']) ? '1' : '0',
        ];
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @return string[]
     */
    public static function changedKeys(array $before, array $after) {
        $order = [
            'templateKey',
            'templateName',
            'category',
            'emailSubject',
            'emailBody',
            'recipientType',
            'description',
            'variables',
            'isActive',
        ];
        $changed = [];
        foreach ($order as $key) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
