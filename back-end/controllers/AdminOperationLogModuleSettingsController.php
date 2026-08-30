<?php
/**
 * 后台操作日志模块开关（日志设置）
 */

require_once __DIR__ . '/../models/AdminOperationLogModuleSetting.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/RequestInput.php';
require_once __DIR__ . '/../services/OperationLog/LogSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class AdminOperationLogModuleSettingsController {
    private $model;

    public function __construct() {
        $this->model = new AdminOperationLogModuleSetting();
    }

    /**
     * 分页列表
     * GET /api/operation-log/module-settings?page=1&per_page=10
     */
    public function index() {
        AuthMiddleware::authenticate();
        AuthMiddleware::checkPermission('page_logsettings_readonly');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPageParam = $_GET['per_page'] ?? 10;

        if ($perPageParam === 'all') {
            $total = $this->model->count();
            $perPage = max(1, $total);
        } else {
            $perPage = max(1, min(100, (int)$perPageParam));
        }

        $result = $this->model->paginate($page, $perPage, $this->model->getVisibleListConditions(), 'sortOrder ASC, id ASC');
        $items = array_map([$this, 'formatRow'], $result['items']);

        Response::paginated(
            $items,
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 按 modelKey 查询是否开启（供其它业务接口判断）
     * GET /api/operation-log/module-settings/check?modelKey=log_client
     */
    public function check() {
        AuthMiddleware::authenticate();
        AuthMiddleware::checkPermission('page_logsettings_readonly');

        $modelKey = trim((string)($_GET['modelKey'] ?? ''));
        if ($modelKey === '') {
            Response::validationError(['modelKey' => ['modelKey is required']]);
            return;
        }

        $row = $this->model->findByModelKey($modelKey);
        if (!$row) {
            Response::notFound('Module not found');
            return;
        }

        $visible = (int)($row['isVisible'] ?? 0) === AdminOperationLogModuleSetting::VISIBLE_YES;
        $enabled = $visible && (int)($row['status'] ?? 0) === AdminOperationLogModuleSetting::STATUS_RUNNING;
        Response::success([
            'modelKey' => $modelKey,
            'visible' => $visible,
            'enabled' => $enabled,
            'status' => (int)$row['status'],
            'isVisible' => (int)$row['isVisible'],
        ]);
    }

    /**
     * 启动单条
     * POST /api/operation-log/module-settings/start
     * Body: { "id": 1 }
     */
    public function startOne() {
        $this->applyStatusChange(AdminOperationLogModuleSetting::STATUS_RUNNING, false);
    }

    /**
     * 停止单条
     * POST /api/operation-log/module-settings/stop
     * Body: { "id": 1 }
     */
    public function stopOne() {
        $this->applyStatusChange(AdminOperationLogModuleSetting::STATUS_STOPPED, false);
    }

    /**
     * 批量启动
     * POST /api/operation-log/module-settings/bulk-start
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkStart() {
        $this->applyStatusChange(AdminOperationLogModuleSetting::STATUS_RUNNING, true);
    }

    /**
     * 批量停止
     * POST /api/operation-log/module-settings/bulk-stop
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkStop() {
        $this->applyStatusChange(AdminOperationLogModuleSetting::STATUS_STOPPED, true);
    }

    private function applyStatusChange($status, $isBulk) {
        AuthMiddleware::authenticate();
        AuthMiddleware::checkPermission('page_logsettings_edit');

        $admin = AuthMiddleware::getCurrentUser();
        $operatorId = isset($admin['userId']) ? (int)$admin['userId'] : null;
        $data = $this->getJsonBody();
        $input = LogSettingsOperationLog::inputFromRequest($data);
        $enabled = (int)$status === AdminOperationLogModuleSetting::STATUS_RUNNING;
        $opType = $enabled ? 'enable' : 'disable';
        $failureMethod = $this->resolveFailureMethod($enabled, $isBulk);

        if ($isBulk) {
            $ids = $data['ids'] ?? null;
            if (!is_array($ids) || empty($ids)) {
                $errors = ['ids' => ['ids must be a non-empty array']];
                LogSettingsOperationLog::logFailure(
                    $input,
                    $opType,
                    $failureMethod,
                    OperationLogTextHelpers::validationErrorsToMessage($errors),
                    $operatorId
                );
                Response::validationError($errors);
                return;
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
                return $id > 0;
            })));
            if (empty($ids)) {
                $errors = ['ids' => ['ids must contain valid positive integers']];
                LogSettingsOperationLog::logFailure(
                    $input,
                    $opType,
                    $failureMethod,
                    OperationLogTextHelpers::validationErrorsToMessage($errors),
                    $operatorId
                );
                Response::validationError($errors);
                return;
            }
        } else {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                $errors = ['id' => ['id is required']];
                LogSettingsOperationLog::logFailure(
                    $input,
                    $opType,
                    $failureMethod,
                    OperationLogTextHelpers::validationErrorsToMessage($errors),
                    $operatorId
                );
                Response::validationError($errors);
                return;
            }
            $ids = [$id];
        }

        $modules = $this->model->findByIds($ids);
        $affected = $this->model->updateStatusByIds($ids, $status, $operatorId);
        if ($affected <= 0) {
            LogSettingsOperationLog::logFailure(
                $input,
                $opType,
                $failureMethod,
                'No records updated',
                $operatorId
            );
            Response::error('No records updated', 404);
            return;
        }

        $orderedModules = $this->orderModulesByIds($modules, $ids);
        if ($isBulk) {
            LogSettingsOperationLog::logBulkToggleSuccess($input, $orderedModules, $enabled, $operatorId);
        } elseif (!empty($orderedModules[0])) {
            LogSettingsOperationLog::logModuleToggleSuccess($input, $orderedModules[0], $enabled, $operatorId);
        }

        Response::success([
            'affected' => $affected,
            'ids' => $ids,
            'status' => (int)$status,
        ], $enabled ? 'Started' : 'Stopped');
    }

    private function resolveFailureMethod($enabled, $isBulk) {
        if ($isBulk) {
            return $enabled ? 'logSettingsModuleBulkEnableFailure' : 'logSettingsModuleBulkDisableFailure';
        }
        return $enabled ? 'logSettingsModuleEnableFailure' : 'logSettingsModuleDisableFailure';
    }

    /**
     * @param array<int,array<string,mixed>> $modules
     * @param int[] $ids
     * @return array<int,array<string,mixed>>
     */
    private function orderModulesByIds(array $modules, array $ids) {
        $byId = [];
        foreach ($modules as $row) {
            $byId[(int)($row['id'] ?? 0)] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    private function getJsonBody() {
        $data = RequestInput::readJsonBody();
        return is_array($data) ? $data : [];
    }

    private function formatRow($row) {
        $status = (int)($row['status'] ?? 0);
        $lastAt = $row['lastStartStopAt'] ?? null;

        return [
            'id' => (int)$row['id'],
            'moduleNameZh' => $row['moduleNameZh'] ?? '',
            'moduleNameEn' => $row['moduleNameEn'] ?? '',
            'modelKey' => $row['modelKey'] ?? '',
            'lastStartStopAt' => $this->formatDateTime($lastAt),
            'status' => $status,
            'statusKey' => $status === AdminOperationLogModuleSetting::STATUS_RUNNING ? 'running' : 'stopped',
            'isVisible' => (int)($row['isVisible'] ?? 1),
            'sortOrder' => (int)($row['sortOrder'] ?? 0),
        ];
    }

    private function formatDateTime($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
