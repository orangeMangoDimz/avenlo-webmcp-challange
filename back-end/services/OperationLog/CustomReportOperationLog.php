<?php
/**
 * Report module — Custom Report (log_report / custom_report)
 */

require_once __DIR__ . '/../OperationLogPages.php';
require_once __DIR__ . '/../AdminOperationLogWriter.php';

class CustomReportOperationLog {
    public static function subModule() {
        $key = OperationLogPages::subModuleKeyByAlias('page_custom_report');
        return $key !== '' ? $key : 'custom_report';
    }

    public static function log($operationTypeKey, $detailZh, $detailEn, $operatorId = null) {
        $params = [
            'modelKey' => 'log_report',
            'subModuleKey' => self::subModule(),
            'operationTypeKey' => trim((string) $operationTypeKey) ?: 'edit',
            'targetId' => null,
            'detailZh' => trim((string) $detailZh),
            'detailEn' => trim((string) $detailEn),
        ];
        $oid = $operatorId !== null ? (int) $operatorId : 0;
        if ($oid > 0) {
            $params['operatorId'] = $oid;
        }
        try {
            (new AdminOperationLogWriter())->record($params);
        } catch (Throwable $e) {
            // Never block business flow on log write failure.
        }
    }

    public static function reportCreated($reportId, $name, $operatorId = null) {
        $name = self::safeName($name);
        $id = self::safeId($reportId);
        self::log(
            'add',
            "创建自定义报表「{$name}」(ID: {$id})",
            "Created custom report \"{$name}\" (ID: {$id})",
            $operatorId
        );
    }

    public static function reportUpdated($reportId, $name, $operatorId = null) {
        $name = self::safeName($name);
        $id = self::safeId($reportId);
        self::log(
            'edit',
            "更新自定义报表「{$name}」(ID: {$id})",
            "Updated custom report \"{$name}\" (ID: {$id})",
            $operatorId
        );
    }

    public static function reportDeleted($reportId, $name, $operatorId = null) {
        $name = self::safeName($name);
        $id = self::safeId($reportId);
        self::log(
            'delete',
            "删除自定义报表「{$name}」(ID: {$id})",
            "Deleted custom report \"{$name}\" (ID: {$id})",
            $operatorId
        );
    }

    public static function widgetCreated($reportId, $widgetId, $widgetName, $operatorId = null) {
        $widgetName = self::safeName($widgetName);
        self::log(
            'add',
            "创建报表组件「{$widgetName}」(report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . ")",
            "Created report widget \"{$widgetName}\" (report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . ")",
            $operatorId
        );
    }

    public static function widgetUpdated($reportId, $widgetId, $widgetName, $summaryZh = '', $summaryEn = '', $operatorId = null) {
        $widgetName = self::safeName($widgetName);
        $extraZh = $summaryZh !== '' ? "；{$summaryZh}" : '';
        $extraEn = $summaryEn !== '' ? "; {$summaryEn}" : '';
        self::log(
            'edit',
            "更新报表组件「{$widgetName}」(report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . "){$extraZh}",
            "Updated report widget \"{$widgetName}\" (report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . "){$extraEn}",
            $operatorId
        );
    }

    public static function widgetDeleted($reportId, $widgetId, $widgetName, $operatorId = null) {
        $widgetName = self::safeName($widgetName);
        self::log(
            'delete',
            "删除报表组件「{$widgetName}」(report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . ")",
            "Deleted report widget \"{$widgetName}\" (report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . ")",
            $operatorId
        );
    }

    public static function widgetExported($reportId, $widgetId, $widgetName, $count, $operatorId = null) {
        $widgetName = self::safeName($widgetName);
        $count = max(0, (int) $count);
        self::log(
            'export',
            "导出报表组件「{$widgetName}」数据，共 {$count} 行 (report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . ")",
            "Exported report widget \"{$widgetName}\" data, {$count} row(s) (report: " . self::safeId($reportId) . ", widget: " . self::safeId($widgetId) . ")",
            $operatorId
        );
    }

    public static function dataSourceExported($dataSourceId, $sourceName, $count, $operatorId = null) {
        $sourceName = self::safeName($sourceName);
        $count = max(0, (int) $count);
        self::log(
            'export',
            "导出销售覆盖表「{$sourceName}」数据，共 {$count} 行 (dataSource: " . self::safeId($dataSourceId) . ")",
            "Exported sales coverage table \"{$sourceName}\" data, {$count} row(s) (dataSource: " . self::safeId($dataSourceId) . ")",
            $operatorId
        );
    }

    public static function widgetDuplicated($reportId, $sourceWidgetId, $newWidgetId, $widgetName, $operatorId = null) {
        $widgetName = self::safeName($widgetName);
        self::log(
            'add',
            "复制报表组件「{$widgetName}」(report: " . self::safeId($reportId)
                . ", from: " . self::safeId($sourceWidgetId)
                . ", to: " . self::safeId($newWidgetId) . ")",
            "Duplicated report widget \"{$widgetName}\" (report: " . self::safeId($reportId)
                . ", from: " . self::safeId($sourceWidgetId)
                . ", to: " . self::safeId($newWidgetId) . ")",
            $operatorId
        );
    }

    public static function summarizeViewConfigChange($beforeConfig, $afterConfig) {
        $before = is_array($beforeConfig) ? $beforeConfig : [];
        $after = is_array($afterConfig) ? $afterConfig : [];
        $beforeTypes = self::typeLabels($before['types'] ?? []);
        $afterTypes = self::typeLabels($after['types'] ?? []);
        $added = array_values(array_diff($afterTypes, $beforeTypes));
        $removed = array_values(array_diff($beforeTypes, $afterTypes));
        $partsZh = [];
        $partsEn = [];
        if ($added) {
            $list = implode(', ', $added);
            $partsZh[] = "新增类型: {$list}";
            $partsEn[] = "added types: {$list}";
        }
        if ($removed) {
            $list = implode(', ', $removed);
            $partsZh[] = "删除类型: {$list}";
            $partsEn[] = "removed types: {$list}";
        }
        $beforeActive = trim((string) ($before['activeView'] ?? ''));
        $afterActive = trim((string) ($after['activeView'] ?? ''));
        if ($beforeActive !== $afterActive) {
            $partsZh[] = "活动类型: {$beforeActive} → {$afterActive}";
            $partsEn[] = "active type: {$beforeActive} → {$afterActive}";
        }
        if (!$partsZh && json_encode($before) !== json_encode($after)) {
            $partsZh[] = '更新视图配置';
            $partsEn[] = 'updated view config';
        }
        return [implode('；', $partsZh), implode('; ', $partsEn)];
    }

    private static function typeLabels($raw) {
        if (!is_array($raw)) {
            return [];
        }
        $labels = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? $item['id'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }
        return $labels;
    }

    private static function safeName($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '(unnamed)';
        }
        return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
    }

    private static function safeId($id) {
        $id = trim((string) $id);
        if ($id === '') {
            return '-';
        }
        return function_exists('mb_substr') ? mb_substr($id, 0, 64) : substr($id, 0, 64);
    }
}
