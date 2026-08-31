<?php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/WebMcpAdminLogService.php';

class WebMcpAdminLogController {
    private const MAX_ID = 2147483647;
    private const MAX_PAGE = 1000;
    private const MAX_LIMIT = 50;
    private const MAX_QUERY_LENGTH = 200;

    private $service;

    public function __construct() {
        $this->service = new WebMcpAdminLogService();
    }

    public static function routeHandlers(): array {
        return [
            'admin/search-admin-users' => 'searchAdminUsers',
            'admin/get-admin-user' => 'getAdminUser',
            'admin/get-role-permissions' => 'getRolePermissions',
            'admin/find-roles-by-permission' => 'findRolesByPermission',
            'admin/check-admin-user-permission' => 'checkAdminUserPermission',
            'admin/search-operation-logs' => 'searchOperationLogs',
            'admin/get-operation-log' => 'getOperationLog',
            'admin/export-operation-logs' => 'exportOperationLogs',
        ];
    }

    public static function normalizeAdminSearchInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['query', 'status', 'roleId', 'page', 'limit']);
        if (!array_intersect(['query', 'status', 'roleId'], array_keys($input))) {
            throw new InvalidArgumentException('At least one administrator search filter is required.');
        }

        $normalized = [];
        if (array_key_exists('query', $input)) {
            $normalized['query'] = self::normalizeString($input['query'], 'query', 100);
        }
        if (array_key_exists('status', $input)) {
            if (!is_string($input['status'])) {
                throw new InvalidArgumentException('status must be active or inactive.');
            }
            $status = strtolower(trim($input['status']));
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('status must be active or inactive.');
            }
            $normalized['status'] = $status;
        }
        if (array_key_exists('roleId', $input)) {
            $normalized['roleId'] = self::normalizePositiveInteger($input['roleId'], 'roleId');
        }
        $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
        $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        return $normalized;
    }

    public static function normalizeAdminUserInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['adminUserId']);
        if (!array_key_exists('adminUserId', $input)) {
            throw new InvalidArgumentException('adminUserId is required.');
        }
        return ['adminUserId' => self::normalizePositiveInteger($input['adminUserId'], 'adminUserId')];
    }

    public static function normalizeRoleInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['roleId', 'roleName']);
        $provided = array_values(array_intersect(['roleId', 'roleName'], array_keys($input)));
        if (count($provided) !== 1) {
            throw new InvalidArgumentException('Exactly one of roleId or roleName is required.');
        }
        return $provided[0] === 'roleId'
            ? ['roleId' => self::normalizePositiveInteger($input['roleId'], 'roleId')]
            : ['roleName' => self::normalizeString($input['roleName'], 'roleName', 100)];
    }

    public static function normalizePermissionInput(array $input, bool $allowIncludeInactive = false): array {
        $allowed = $allowIncludeInactive ? ['permissionKey', 'includeInactive'] : ['permissionKey'];
        self::rejectUnsupportedKeys($input, $allowed);
        if (!array_key_exists('permissionKey', $input)) {
            throw new InvalidArgumentException('permissionKey is required.');
        }
        $permissionKey = self::normalizePermissionKey($input['permissionKey']);
        $normalized = ['permissionKey' => $permissionKey];
        if ($allowIncludeInactive) {
            $normalized['includeInactive'] = self::normalizeBoolean(
                $input['includeInactive'] ?? false,
                'includeInactive'
            );
        }
        return $normalized;
    }

    public static function normalizeAdminPermissionInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['adminUserId', 'permissionKey']);
        if (!array_key_exists('adminUserId', $input) || !array_key_exists('permissionKey', $input)) {
            throw new InvalidArgumentException('adminUserId and permissionKey are required.');
        }
        return [
            'adminUserId' => self::normalizePositiveInteger($input['adminUserId'], 'adminUserId'),
            'permissionKey' => self::normalizePermissionKey($input['permissionKey']),
        ];
    }

    public static function normalizeOperationLogSearchInput(array $input, bool $forExport = false): array {
        $filterKeys = [
            'operatorId', 'module', 'operationType', 'targetType', 'targetId',
            'startDate', 'endDate', 'query',
        ];
        $allowed = $forExport ? $filterKeys : array_merge($filterKeys, ['page', 'limit']);
        self::rejectUnsupportedKeys($input, $allowed);
        if (!array_intersect($filterKeys, array_keys($input))) {
            throw new InvalidArgumentException('At least one operation-log filter is required.');
        }

        $normalized = [];
        if (array_key_exists('operatorId', $input)) {
            $normalized['operatorId'] = self::normalizePositiveInteger($input['operatorId'], 'operatorId');
        }
        foreach (['module' => 80, 'operationType' => 80] as $key => $maximum) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeKey($input[$key], $key, $maximum);
            }
        }
        if (array_key_exists('targetType', $input)) {
            if (!is_string($input['targetType'])) {
                throw new InvalidArgumentException('targetType must be a string.');
            }
            $targetType = strtolower(trim($input['targetType']));
            if (!in_array($targetType, [
                'client', 'admin_user', 'admin_role', 'points_mall_product',
            ], true)) {
                throw new InvalidArgumentException('targetType is not supported.');
            }
            $normalized['targetType'] = $targetType;
        }
        if (array_key_exists('targetId', $input)) {
            $normalized['targetId'] = self::normalizePositiveInteger($input['targetId'], 'targetId');
        }
        if (isset($normalized['targetId']) && !isset($normalized['targetType'])) {
            throw new InvalidArgumentException('targetType is required when targetId is provided.');
        }
        foreach (['startDate', 'endDate'] as $key) {
            if (array_key_exists($key, $input)) {
                $normalized[$key] = self::normalizeDate($input[$key], $key);
            }
        }
        if (
            isset($normalized['startDate'], $normalized['endDate'])
            && $normalized['startDate'] > $normalized['endDate']
        ) {
            throw new InvalidArgumentException('startDate must be on or before endDate.');
        }
        if (array_key_exists('query', $input)) {
            $normalized['query'] = self::normalizeString(
                $input['query'],
                'query',
                self::MAX_QUERY_LENGTH
            );
        }
        if (!$forExport) {
            $normalized['page'] = self::normalizePositiveInteger($input['page'] ?? 1, 'page', self::MAX_PAGE);
            $normalized['limit'] = self::normalizePositiveInteger($input['limit'] ?? 25, 'limit', self::MAX_LIMIT);
        }
        return $normalized;
    }

    public static function normalizeOperationLogIdInput(array $input): array {
        self::rejectUnsupportedKeys($input, ['operationLogId']);
        if (!array_key_exists('operationLogId', $input)) {
            throw new InvalidArgumentException('operationLogId is required.');
        }
        return ['operationLogId' => self::normalizePositiveInteger($input['operationLogId'], 'operationLogId')];
    }

    public function searchAdminUsers(): void {
        $this->requirePermissions(['page_accountmanagement_readonly', 'page_accountmanagement_edit']);
        $input = $this->normalizeQuery([self::class, 'normalizeAdminSearchInput']);
        Response::success($this->service->searchAdminUsers($input));
    }

    public function getAdminUser(): void {
        $this->requirePermissions(['page_accountmanagement_readonly', 'page_accountmanagement_edit']);
        $input = $this->normalizeQuery([self::class, 'normalizeAdminUserInput']);
        $adminUser = $this->service->getAdminUser($input['adminUserId']);
        if ($adminUser === null) {
            Response::notFound('Administrator not found.');
        }
        Response::success(['adminUser' => $adminUser]);
    }

    public function getRolePermissions(): void {
        $this->requirePermissions(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
        $input = $this->normalizeQuery([self::class, 'normalizeRoleInput']);
        try {
            $payload = $this->service->getRolePermissions($input);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
        if ($payload === null) {
            Response::notFound('Role not found.');
        }
        Response::success($payload);
    }

    public function findRolesByPermission(): void {
        $this->requirePermissions(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
        $input = $this->normalizeQuery(static function (array $query): array {
            return self::normalizePermissionInput($query, true);
        });
        $payload = $this->service->findRolesByPermission(
            $input['permissionKey'],
            $input['includeInactive']
        );
        if ($payload === null) {
            Response::notFound('Permission not found.');
        }
        Response::success($payload);
    }

    public function checkAdminUserPermission(): void {
        $this->requirePermissions(['page_rolemanagement_readonly', 'page_rolemanagement_edit']);
        $input = $this->normalizeQuery([self::class, 'normalizeAdminPermissionInput']);
        $payload = $this->service->checkAdminUserPermission(
            $input['adminUserId'],
            $input['permissionKey']
        );
        if ($payload === null) {
            Response::notFound('Administrator or permission not found.');
        }
        Response::success($payload);
    }

    public function searchOperationLogs(): void {
        $this->requirePermissions(['page_operationlogreport_readonly']);
        $input = $this->normalizeQuery([self::class, 'normalizeOperationLogSearchInput']);
        try {
            Response::success($this->service->searchOperationLogs($input));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    public function getOperationLog(): void {
        $this->requirePermissions(['page_operationlogreport_readonly']);
        $input = $this->normalizeQuery([self::class, 'normalizeOperationLogIdInput']);
        $log = $this->service->getOperationLog($input['operationLogId']);
        if ($log === null) {
            Response::notFound('Operation log not found.');
        }
        Response::success(['operationLog' => $log]);
    }

    public function exportOperationLogs(): void {
        $this->requirePermissions(['page_operationlogreport_export']);
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $body = [];
        }
        $language = strtolower(trim((string)($body['language'] ?? 'en'))) === 'zh' ? 'zh' : 'en';
        unset($body['language']);
        try {
            $input = self::normalizeOperationLogSearchInput($body, true);
            $filters = $this->service->scopeOperationLogFilters(
                WebMcpAdminLogService::operationLogFilters($input)
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        require_once __DIR__ . '/../services/OperationLogReportExportService.php';
        $currentUser = AuthMiddleware::requireAdmin();
        $adminUserId = (int)($currentUser['userId'] ?? 0);
        try {
            $queued = OperationLogReportExportService::queueForAdmin(
                $adminUserId,
                ['filters' => $filters, 'language' => $language]
            );
        } catch (RuntimeException $exception) {
            Response::error($exception->getMessage(), 409);
        }
        Response::success($queued, !empty($queued['reused'])
            ? 'Existing export remains available.'
            : 'Export task accepted');
    }

    private function requirePermissions(array $permissionKeys): void {
        AuthMiddleware::requireAdmin();
        AuthMiddleware::checkAnyPermission($permissionKeys);
    }

    private function normalizeQuery(callable $normalizer): array {
        try {
            return $normalizer(array_diff_key($_GET, ['path' => true]));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    private static function rejectUnsupportedKeys(array $input, array $allowed): void {
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("{$key} is not supported.");
            }
        }
    }

    private static function normalizePositiveInteger($value, string $name, int $maximum = self::MAX_ID): int {
        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $value = (int)trim($value);
        }
        if (!is_int($value) || $value < 1 || $value > $maximum) {
            throw new InvalidArgumentException("{$name} must be an integer between 1 and {$maximum}.");
        }
        return $value;
    }

    private static function normalizeString($value, string $name, int $maximum): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException("{$name} must be a string.");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maximum) {
            throw new InvalidArgumentException("{$name} must be between 1 and {$maximum} characters.");
        }
        return $value;
    }

    private static function normalizeKey($value, string $name, int $maximum): string {
        $value = self::normalizeString($value, $name, $maximum);
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("{$name} contains unsupported characters.");
        }
        return $value;
    }

    private static function normalizePermissionKey($value): string {
        return self::normalizeKey($value, 'permissionKey', 120);
    }

    private static function normalizeDate($value, string $name): string {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new InvalidArgumentException("{$name} must use YYYY-MM-DD.");
        }
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$name} must be a valid date.");
        }
        return $value;
    }

    private static function normalizeBoolean($value, string $name): bool {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 'true' || $value === '1' || $value === 1) {
            return true;
        }
        if ($value === 'false' || $value === '0' || $value === 0) {
            return false;
        }
        throw new InvalidArgumentException("{$name} must be a boolean.");
    }
}
