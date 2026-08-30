<?php
/**
 * 角色 update — 保存前后快照与差异对比
 */

class AdminRoleLogSnapshot {
    /**
     * @param array<string,mixed> $row
     * @param int[]|null $permissionIds
     * @return array<string,mixed>
     */
    public static function fromDbRow(array $row, $permissionIds = null) {
        if ($permissionIds === null) {
            if (isset($row['permissionIds']) && is_array($row['permissionIds'])) {
                $permissionIds = $row['permissionIds'];
            } elseif (isset($row['permissions']) && is_array($row['permissions'])) {
                $permissionIds = [];
                foreach ($row['permissions'] as $p) {
                    if (!is_array($p)) {
                        continue;
                    }
                    $pid = (int) ($p['id'] ?? $p['permissionId'] ?? 0);
                    if ($pid > 0) {
                        $permissionIds[] = $pid;
                    }
                }
            } else {
                $permissionIds = [];
            }
        }
        $permissionIds = self::normalizePermissionIds($permissionIds);

        return [
            'roleName' => trim((string) ($row['roleName'] ?? '')),
            'roleDisplayName' => trim((string) ($row['roleDisplayName'] ?? '')),
            'description' => trim((string) ($row['description'] ?? '')),
            'isActive' => !empty($row['isActive']),
            'permissionIds' => $permissionIds,
            'permissionCount' => count($permissionIds),
        ];
    }

    /**
     * @param int[]|string[] $ids
     * @return int[]
     */
    public static function normalizePermissionIds($ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), function ($id) {
            return $id > 0;
        })));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function sectionFingerprints(array $state) {
        $name = trim((string) ($state['roleName'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($state['roleDisplayName'] ?? ''));
        }
        $ids = self::normalizePermissionIds($state['permissionIds'] ?? []);
        return [
            'roleName' => $name,
            'description' => (string) ($state['description'] ?? ''),
            'isActive' => !empty($state['isActive']) ? '1' : '0',
            'permissions' => implode(',', $ids),
        ];
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @return string[]
     */
    public static function changedSectionKeys(array $before, array $after) {
        $order = ['roleName', 'description', 'isActive', 'permissions'];
        $changed = [];
        foreach ($order as $key) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
