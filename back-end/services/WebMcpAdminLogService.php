<?php

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../models/AdminOperationLog.php';
require_once __DIR__ . '/../models/AdminOperationLogModuleSetting.php';

class WebMcpAdminLogService {
    private $db;
    private $operationLogModel;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->operationLogModel = new AdminOperationLog();
    }

    public function searchAdminUsers(array $input): array {
        $where = ['u.deletedAt IS NULL'];
        $params = [];

        if (isset($input['query'])) {
            $query = '%' . $input['query'] . '%';
            $parts = [
                'u.username LIKE :adminQueryUsername',
                'u.email LIKE :adminQueryEmail',
                'u.fullName LIKE :adminQueryName',
            ];
            $params['adminQueryUsername'] = $query;
            $params['adminQueryEmail'] = $query;
            $params['adminQueryName'] = $query;
            if (ctype_digit($input['query'])) {
                $parts[] = 'u.id = :adminQueryId';
                $params['adminQueryId'] = (int)$input['query'];
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (isset($input['status'])) {
            $where[] = 'u.status = :adminStatus';
            $params['adminStatus'] = $input['status'];
        }
        if (isset($input['roleId'])) {
            $where[] = 'u.roleId = :adminRoleId';
            $params['adminRoleId'] = $input['roleId'];
        }

        $from = 'FROM adminUsers u
                 LEFT JOIN adminRoles r ON r.id = u.roleId
                 LEFT JOIN departments d ON d.id = u.departmentId
                 LEFT JOIN positions p ON p.id = u.positionId
                 WHERE ' . implode(' AND ', $where);
        $count = $this->db->fetchOne("SELECT COUNT(*) AS total {$from}", $params);
        $total = (int)($count['total'] ?? 0);
        $offset = ($input['page'] - 1) * $input['limit'];
        $rows = $this->db->fetchAll(
            "SELECT u.id, u.username, u.email, u.fullName, u.status, u.isLocked,
                    u.roleId, u.departmentId, u.positionId, u.createdAt, u.lastLoginAt,
                    r.roleKey, r.roleName, r.roleDisplayName, r.isActive AS roleIsActive,
                    d.name AS departmentName, p.name AS positionName
             {$from}
             ORDER BY u.createdAt DESC, u.id DESC
             LIMIT " . (int)$input['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );

        return [
            'adminUsers' => array_map([self::class, 'projectAdminUser'], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
        ];
    }

    public function getAdminUser(int $adminUserId): ?array {
        $row = $this->db->fetchOne(
            "SELECT u.id, u.username, u.email, u.fullName, u.status, u.isLocked,
                    u.roleId, u.departmentId, u.positionId, u.createdAt, u.updatedAt,
                    u.lastLoginAt, r.roleKey, r.roleName, r.roleDisplayName,
                    r.isActive AS roleIsActive, d.name AS departmentName,
                    p.name AS positionName
             FROM adminUsers u
             LEFT JOIN adminRoles r ON r.id = u.roleId
             LEFT JOIN departments d ON d.id = u.departmentId
             LEFT JOIN positions p ON p.id = u.positionId
             WHERE u.id = :adminUserId AND u.deletedAt IS NULL
             LIMIT 1",
            ['adminUserId' => $adminUserId]
        );
        return $row ? self::projectAdminUser($row) : null;
    }

    public function getRolePermissions(array $selector): ?array {
        $role = $this->resolveRole($selector);
        if ($role === null) {
            return null;
        }
        $implicitAllPermissions = (int)$role['id'] === 1;
        if ($implicitAllPermissions) {
            $permissions = $this->db->fetchAll(
                "SELECT id, permissionKey, permissionName,
                        permissionDisplayName, permissionDisplayNameZh,
                        description, module
                 FROM adminPermissions
                 WHERE isActive = 1
                 ORDER BY sortOrder ASC, id ASC"
            );
        } else {
            $permissions = $this->db->fetchAll(
                "SELECT p.id, p.permissionKey, p.permissionName,
                        p.permissionDisplayName, p.permissionDisplayNameZh,
                        p.description, p.module
                 FROM adminRolePermissions rp
                 INNER JOIN adminPermissions p ON p.id = rp.permissionId
                 WHERE rp.roleId = :roleId AND p.isActive = 1
                 ORDER BY p.sortOrder ASC, p.id ASC",
                ['roleId' => (int)$role['id']]
            );
        }
        return [
            'role' => self::projectRole($role),
            'permissions' => array_map([self::class, 'projectPermission'], $permissions),
            'implicitAllPermissions' => $implicitAllPermissions,
        ];
    }

    public function findRolesByPermission(string $permissionKey, bool $includeInactive): ?array {
        $permission = $this->findPermission($permissionKey);
        if ($permission === null) {
            return null;
        }
        $where = ['rp.permissionId = :permissionId'];
        if (!$includeInactive) {
            $where[] = 'r.isActive = 1';
        }
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT r.*
             FROM adminRolePermissions rp
             INNER JOIN adminRoles r ON r.id = rp.roleId
             WHERE " . implode(' AND ', $where) . '
             ORDER BY r.level DESC, r.id ASC',
            ['permissionId' => (int)$permission['id']]
        );

        $superAdmin = $this->db->fetchOne(
            'SELECT id, roleKey, roleName, roleDisplayName, description,
                    level, isSystem, isActive
             FROM adminRoles WHERE id = 1 LIMIT 1'
        );
        if ($superAdmin && ($includeInactive || !empty($superAdmin['isActive']))) {
            array_unshift($rows, $superAdmin);
        }
        $seen = [];
        $roles = [];
        foreach ($rows as $row) {
            $roleId = (int)($row['id'] ?? 0);
            if ($roleId <= 0 || isset($seen[$roleId])) {
                continue;
            }
            $seen[$roleId] = true;
            $roles[] = self::projectRole($row);
        }

        return [
            'permission' => self::projectPermission($permission),
            'roles' => $roles,
        ];
    }

    public function checkAdminUserPermission(int $adminUserId, string $permissionKey): ?array {
        $user = $this->db->fetchOne(
            "SELECT u.id, u.username, u.email, u.fullName, u.status, u.isLocked,
                    u.roleId, r.roleKey, r.roleName, r.roleDisplayName,
                    r.isActive AS roleIsActive
             FROM adminUsers u
             LEFT JOIN adminRoles r ON r.id = u.roleId
             WHERE u.id = :adminUserId AND u.deletedAt IS NULL
             LIMIT 1",
            ['adminUserId' => $adminUserId]
        );
        $permission = $this->findPermission($permissionKey);
        if (!$user || !$permission) {
            return null;
        }

        if ((int)($user['roleId'] ?? 0) === 1) {
            $sources = ['super_admin'];
        } else {
            $rows = $this->db->fetchAll(
                "SELECT permissionSource
                 FROM vAdminUserPermissions
                 WHERE userId = :adminUserId
                   AND permissionKey = :permissionKey
                   AND isGranted = 1
                 ORDER BY permissionSource ASC",
                ['adminUserId' => $adminUserId, 'permissionKey' => $permissionKey]
            );
            $sources = array_values(array_unique(array_map(
                static fn($row) => (string)($row['permissionSource'] ?? ''),
                $rows
            )));
            $sources = array_values(array_filter($sources));
        }

        return [
            'adminUser' => self::projectAdminUser($user),
            'permission' => self::projectPermission($permission),
            'hasPermission' => !empty($sources),
            'sources' => $sources,
        ];
    }

    public function searchOperationLogs(array $input): array {
        $filters = $this->scopeOperationLogFilters(self::operationLogFilters($input));
        $total = $this->operationLogModel->countByFilters($filters);
        $rows = $this->operationLogModel->findByFilters(
            $filters,
            $input['page'],
            $input['limit']
        );
        return [
            'operationLogs' => array_map([self::class, 'projectOperationLog'], $rows),
            'pagination' => self::pagination($input['page'], $input['limit'], $total),
            'filters' => array_diff_key($input, ['page' => true, 'limit' => true]),
        ];
    }

    public function getOperationLog(int $operationLogId): ?array {
        $filters = self::scopeFiltersToVisibleModules(
            ['logId' => $operationLogId, 'modelKey' => 'all'],
            $this->visibleModelKeys()
        );
        $rows = $this->operationLogModel->findByFilters(
            $filters,
            1,
            1
        );
        return $rows ? self::projectOperationLog($rows[0]) : null;
    }

    public static function operationLogFilters(array $input): array {
        $filters = [
            'modelKey' => 'all',
            'subModule' => 'all',
            'operationType' => $input['operationType'] ?? 'all',
            'startDate' => $input['startDate'] ?? '',
            'endDate' => $input['endDate'] ?? '',
            'operatorId' => $input['operatorId'] ?? null,
            'targetId' => $input['targetId'] ?? null,
            'query' => $input['query'] ?? '',
        ];
        if (isset($input['module'])) {
            $module = self::resolveModule($input['module']);
            if ($module === null) {
                throw new InvalidArgumentException('module is not a registered operation-log module.');
            }
            $filters['modelKey'] = $module['modelKey'];
            $filters['subModule'] = $module['subModuleKey'];
        }
        if (isset($input['targetType'])) {
            $filters['targetScopes'] = self::targetScopes($input['targetType']);
            if (!$filters['targetScopes']) {
                throw new InvalidArgumentException('targetType is not supported.');
            }
        }
        return $filters;
    }

    public function scopeOperationLogFilters(array $filters): array {
        return self::scopeFiltersToVisibleModules($filters, $this->visibleModelKeys());
    }

    public static function scopeFiltersToVisibleModules(array $filters, array $visibleModelKeys): array {
        $visibleModelKeys = array_values(array_unique(array_filter(array_map(
            static fn($key) => trim((string)$key),
            $visibleModelKeys
        ))));
        if (!$visibleModelKeys) {
            throw new InvalidArgumentException('No operation-log modules are visible.');
        }
        $modelKey = trim((string)($filters['modelKey'] ?? 'all'));
        if ($modelKey === '' || $modelKey === 'all') {
            $filters['modelKey'] = 'all';
            $filters['modelKeys'] = $visibleModelKeys;
            return $filters;
        }
        if (!in_array($modelKey, $visibleModelKeys, true)) {
            throw new InvalidArgumentException('module is not visible in the operation-log report.');
        }
        unset($filters['modelKeys']);
        return $filters;
    }

    public static function resolveModule(string $module): ?array {
        $module = trim($module);
        foreach (self::operationLogPages() as $page) {
            if ((string)($page['subModuleKey'] ?? '') !== $module) {
                continue;
            }
            return [
                'modelKey' => (string)($page['modelKey'] ?? ''),
                'subModuleKey' => $module,
                'targetType' => self::publicTargetName($page['defaultTarget'] ?? 'none'),
            ];
        }
        return null;
    }

    public static function targetScopes(string $targetType): array {
        $internal = self::internalTargetName($targetType);
        if ($internal === null) {
            return [];
        }
        $scopes = [];
        foreach (self::operationLogPages() as $page) {
            $modelKey = trim((string)($page['modelKey'] ?? ''));
            $subModuleKey = trim((string)($page['subModuleKey'] ?? ''));
            if (
                $modelKey === ''
                || $subModuleKey === ''
                || (string)($page['defaultTarget'] ?? 'none') !== $internal
            ) {
                continue;
            }
            $key = $modelKey . ':' . $subModuleKey;
            $scopes[$key] = ['modelKey' => $modelKey, 'subModuleKey' => $subModuleKey];
        }
        return array_values($scopes);
    }

    public static function publicTargetType(string $modelKey, string $subModuleKey): ?string {
        foreach (self::operationLogPages() as $page) {
            if (
                (string)($page['modelKey'] ?? '') === $modelKey
                && (string)($page['subModuleKey'] ?? '') === $subModuleKey
            ) {
                return self::publicTargetName($page['defaultTarget'] ?? 'none');
            }
        }
        return null;
    }

    public static function projectAdminUser(array $row): array {
        $roleName = trim((string)($row['roleDisplayName'] ?? $row['roleName'] ?? ''));
        return [
            'id' => (int)($row['id'] ?? 0),
            'username' => self::nullableString($row['username'] ?? null),
            'fullName' => self::nullableString($row['fullName'] ?? null),
            'email' => self::nullableString($row['email'] ?? null),
            'status' => self::nullableString($row['status'] ?? null),
            'isLocked' => (bool)($row['isLocked'] ?? false),
            'role' => [
                'id' => isset($row['roleId']) ? (int)$row['roleId'] : null,
                'name' => $roleName !== '' ? $roleName : null,
                'key' => self::nullableString($row['roleKey'] ?? null),
                'isActive' => isset($row['roleIsActive']) ? (bool)$row['roleIsActive'] : null,
            ],
            'department' => [
                'id' => isset($row['departmentId']) ? (int)$row['departmentId'] : null,
                'name' => self::nullableString($row['departmentName'] ?? $row['department'] ?? null),
            ],
            'position' => [
                'id' => isset($row['positionId']) ? (int)$row['positionId'] : null,
                'name' => self::nullableString($row['positionName'] ?? null),
            ],
            'lastLoginAt' => self::nullableString($row['lastLoginAt'] ?? null),
            'createdAt' => self::nullableString($row['createdAt'] ?? null),
            'updatedAt' => self::nullableString($row['updatedAt'] ?? null),
        ];
    }

    public static function projectOperationLog(array $row): array {
        $modelKey = (string)($row['modelKey'] ?? '');
        $subModuleKey = (string)($row['subModuleKey'] ?? '');
        $targetType = self::publicTargetType($modelKey, $subModuleKey);
        return [
            'id' => (int)($row['id'] ?? 0),
            'operator' => [
                'id' => (int)($row['operatorId'] ?? 0),
                'fullName' => self::nullableString($row['operatorFullName'] ?? null),
            ],
            'module' => [
                'key' => $subModuleKey,
                'modelKey' => $modelKey,
                'nameEn' => self::nullableString($row['subModuleNameEn'] ?? null),
                'nameZh' => self::nullableString($row['subModuleNameZh'] ?? null),
                'categoryNameEn' => self::nullableString($row['moduleNameEn'] ?? null),
                'categoryNameZh' => self::nullableString($row['moduleNameZh'] ?? null),
            ],
            'operationType' => self::nullableString($row['operationTypeKey'] ?? null),
            'target' => isset($row['targetId']) && $row['targetId'] !== null ? [
                'type' => $targetType,
                'id' => (int)$row['targetId'],
                'displayNameEn' => self::nullableString($row['targetDisplayNameEn'] ?? null),
                'displayNameZh' => self::nullableString($row['targetDisplayNameZh'] ?? null),
            ] : null,
            'detailEn' => self::nullableString($row['detailEn'] ?? null),
            'detailZh' => self::nullableString($row['detailZh'] ?? null),
            'ipAddress' => self::nullableString($row['ipAddress'] ?? null),
            'operatedAt' => self::isoUtc($row['operatedAt'] ?? null),
        ];
    }

    private function resolveRole(array $selector): ?array {
        if (isset($selector['roleId'])) {
            return $this->db->fetchOne(
                'SELECT id, roleKey, roleName, roleDisplayName, description,
                        level, isSystem, isActive
                 FROM adminRoles WHERE id = :roleId LIMIT 1',
                ['roleId' => $selector['roleId']]
            ) ?: null;
        }
        $rows = $this->db->fetchAll(
            "SELECT id, roleKey, roleName, roleDisplayName, description,
                    level, isSystem, isActive FROM adminRoles
             WHERE LOWER(roleName) = LOWER(:roleName)
                OR LOWER(roleDisplayName) = LOWER(:roleDisplayName)
             ORDER BY id ASC
             LIMIT 2",
            ['roleName' => $selector['roleName'], 'roleDisplayName' => $selector['roleName']]
        );
        if (count($rows) > 1) {
            throw new InvalidArgumentException('roleName matched more than one role; use roleId.');
        }
        return $rows[0] ?? null;
    }

    private function findPermission(string $permissionKey): ?array {
        return $this->db->fetchOne(
            "SELECT id, permissionKey, permissionName, permissionDisplayName,
                    permissionDisplayNameZh, description, module
             FROM adminPermissions
             WHERE permissionKey = :permissionKey AND isActive = 1
             LIMIT 1",
            ['permissionKey' => $permissionKey]
        ) ?: null;
    }

    private function visibleModelKeys(): array {
        $rows = (new AdminOperationLogModuleSetting())->findReportTabs();
        return array_values(array_filter(array_map(
            static fn($row) => trim((string)($row['modelKey'] ?? '')),
            $rows
        )));
    }

    private static function projectRole(array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'key' => self::nullableString($row['roleKey'] ?? $row['roleName'] ?? null),
            'name' => self::nullableString($row['roleDisplayName'] ?? $row['roleName'] ?? null),
            'description' => self::nullableString($row['description'] ?? null),
            'level' => (int)($row['level'] ?? 0),
            'isSystem' => (bool)($row['isSystem'] ?? false),
            'isActive' => (bool)($row['isActive'] ?? false),
        ];
    }

    private static function projectPermission(array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'key' => self::nullableString($row['permissionKey'] ?? null),
            'name' => self::nullableString($row['permissionDisplayName'] ?? $row['permissionName'] ?? null),
            'nameZh' => self::nullableString($row['permissionDisplayNameZh'] ?? null),
            'description' => self::nullableString($row['description'] ?? null),
            'module' => self::nullableString($row['module'] ?? null),
        ];
    }

    private static function operationLogPages(): array {
        $config = require __DIR__ . '/../config/operation_log_pages.php';
        return is_array($config['pages'] ?? null) ? $config['pages'] : [];
    }

    private static function publicTargetName($internal): ?string {
        $map = [
            'client_user' => 'client',
            'admin_user' => 'admin_user',
            'admin_role' => 'admin_role',
            'ib_partner' => 'ib_partner',
            'points_mall_product' => 'points_mall_product',
            'transaction' => 'transaction',
        ];
        return $map[(string)$internal] ?? null;
    }

    private static function internalTargetName(string $public): ?string {
        return $public === 'client' ? 'client_user'
            : (in_array($public, ['admin_user', 'admin_role', 'ib_partner', 'points_mall_product', 'transaction'], true)
                ? $public
                : null);
    }

    private static function nullableString($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function isoUtc($value): ?string {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        try {
            $date = new DateTime((string)$value, new DateTimeZone('UTC'));
            return $date->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $exception) {
            return null;
        }
    }

    private static function pagination(int $page, int $limit, int $total): array {
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 0;
        return [
            'page' => $page,
            'limit' => $limit,
            'perPage' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'hasMore' => $page < $totalPages,
        ];
    }
}
