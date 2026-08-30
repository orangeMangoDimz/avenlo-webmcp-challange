<?php
/**
 * 邮件设置 — 板块模板 ID 快照与差异
 */

class EmailSettingsLogSnapshot {
    /**
     * @param int[] $ids
     * @return int[]
     */
    public static function normalizeTemplateIds(array $ids) {
        $normalized = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
        sort($normalized);
        return $normalized;
    }

    /**
     * @param int[] $before
     * @param int[] $after
     */
    public static function idsEqual(array $before, array $after) {
        return self::normalizeTemplateIds($before) === self::normalizeTemplateIds($after);
    }

    /**
     * @param int[] $before
     * @param int[] $after
     * @return array{added:int[],removed:int[]}
     */
    public static function diffTemplateIds(array $before, array $after) {
        $beforeSet = self::normalizeTemplateIds($before);
        $afterSet = self::normalizeTemplateIds($after);
        return [
            'added' => array_values(array_diff($afterSet, $beforeSet)),
            'removed' => array_values(array_diff($beforeSet, $afterSet)),
        ];
    }
}
