<?php
/**
 * 账户管理（管理员用户）update — 保存前后快照与差异对比
 */

class AdminUserLogSnapshot {
    /**
     * @param array<string,mixed> $userInfo vAdminUsersFull 行
     * @param array<string,mixed> $labels roleLabelZh, roleLabelEn, departmentName, positionName
     * @param bool $passwordChanged
     * @return array<string,mixed>
     */
    public static function fromUserInfo(array $userInfo, array $labels = [], $passwordChanged = false) {
        $departmentId = $userInfo['departmentId'] ?? null;
        if ($departmentId === '' || $departmentId === 0 || $departmentId === '0') {
            $departmentId = null;
        } elseif ($departmentId !== null) {
            $departmentId = (int) $departmentId;
        }

        $positionId = $userInfo['positionId'] ?? null;
        if ($positionId === '' || $positionId === 0 || $positionId === '0') {
            $positionId = null;
        } elseif ($positionId !== null) {
            $positionId = (int) $positionId;
        }

        $roleId = $userInfo['roleId'] ?? null;
        if ($roleId === '' || $roleId === 0 || $roleId === '0') {
            $roleId = null;
        } elseif ($roleId !== null) {
            $roleId = (int) $roleId;
        }

        $roleLabelZh = trim((string) ($labels['roleLabelZh'] ?? ''));
        if ($roleLabelZh === '') {
            $roleLabelZh = trim((string) ($userInfo['roleDisplayName'] ?? $userInfo['roleName'] ?? ''));
        }
        $roleLabelEn = trim((string) ($labels['roleLabelEn'] ?? ''));
        if ($roleLabelEn === '') {
            $roleLabelEn = trim((string) ($userInfo['roleName'] ?? $userInfo['roleDisplayName'] ?? ''));
        }

        return [
            'fullName' => trim((string) ($userInfo['fullName'] ?? '')),
            'username' => trim((string) ($userInfo['username'] ?? '')),
            'email' => trim((string) ($userInfo['email'] ?? '')),
            'roleId' => $roleId,
            'roleLabelZh' => $roleLabelZh,
            'roleLabelEn' => $roleLabelEn,
            'departmentId' => $departmentId,
            'departmentName' => trim((string) ($labels['departmentName'] ?? '')),
            'positionId' => $positionId,
            'positionName' => trim((string) ($labels['positionName'] ?? '')),
            'status' => trim((string) ($userInfo['status'] ?? '')),
            'passwordChanged' => !!$passwordChanged,
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,string>
     */
    public static function sectionFingerprints(array $state) {
        return [
            'fullName' => $state['fullName'] ?? '',
            'email' => $state['email'] ?? '',
            'role' => $state['roleId'] === null ? 'null' : (string) (int) $state['roleId'],
            'department' => $state['departmentId'] === null ? 'null' : (string) (int) $state['departmentId'],
            'position' => $state['positionId'] === null ? 'null' : (string) (int) $state['positionId'],
            'status' => $state['status'] ?? '',
            'password' => !empty($state['passwordChanged']) ? '1' : '0',
        ];
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @return string[]
     */
    public static function changedSectionKeys(array $before, array $after) {
        $order = ['fullName', 'email', 'role', 'department', 'position', 'status', 'password'];
        $changed = [];
        foreach ($order as $key) {
            if (($before[$key] ?? '') !== ($after[$key] ?? '')) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
