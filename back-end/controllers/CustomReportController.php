<?php
/**
 * Custom Report Controller
 * Lists custom_reports; show report with widgets
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../services/OperationLog/CustomReportOperationLog.php';

class CustomReportController {
    private const MAX_TABLE_VISIBLE_COLUMNS = 10;

    /**
     * GET /api/custom-reports
     */
    public function index() {
        $this->requireAdmin();

        $db = Database::getInstance();
        $search = trim((string)($_GET['search'] ?? ''));

        $sql = "SELECT
                    cr.id,
                    cr.name,
                    cr.created_by AS createdBy,
                    cr.created_at AS createdAt,
                    cr.updated_at AS updatedAt,
                    au.fullName AS createdByName,
                    (
                      SELECT COUNT(*)
                      FROM report_widgets rw
                      WHERE rw.report_id = cr.id
                    ) AS widgetCount
                FROM custom_reports cr
                LEFT JOIN adminUsers au ON au.id = CAST(cr.created_by AS UNSIGNED)
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND cr.name LIKE :search";
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY cr.created_at DESC";

        $rows = $db->fetchAll($sql, $params);

        Response::success(['items' => $rows]);
    }

    /**
     * POST /api/custom-reports
     * Body: { name }
     */
    public function create() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            Response::error('Report name is required', 400);
            return;
        }

        if (mb_strlen($name) > 255) {
            Response::error('Report name is too long', 400);
            return;
        }

        $id = $this->generateUuid();
        $createdBy = (string)$admin['userId'];

        $db = Database::getInstance();
        $db->query(
            "INSERT INTO custom_reports (id, name, created_by, created_at, updated_at)
             VALUES (:id, :name, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [
                'id' => $id,
                'name' => $name,
                'created_by' => $createdBy
            ]
        );

        CustomReportOperationLog::reportCreated($id, $name, $createdBy);

        Response::success([
            'id' => $id,
            'name' => $name,
            'createdBy' => $createdBy,
            'createdAt' => date('Y-m-d H:i:s')
        ], 'Report created', 201);
    }

    /**
     * PUT/PATCH /api/custom-reports/{id}
     * Body: { name }
     */
    public function update($id) {
        $admin = $this->requireAdmin();

        $id = trim((string)$id);
        if ($id === '') {
            Response::error('Report id is required', 400);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            Response::error('Report name is required', 400);
            return;
        }

        if (mb_strlen($name) > 255) {
            Response::error('Report name is too long', 400);
            return;
        }

        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id FROM custom_reports WHERE id = :id",
            ['id' => $id]
        );

        if (!$existing) {
            Response::error('Report not found', 404);
            return;
        }

        $db->query(
            "UPDATE custom_reports
             SET name = :name, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            [
                'id' => $id,
                'name' => $name
            ]
        );

        CustomReportOperationLog::reportUpdated($id, $name, $admin['userId'] ?? null);

        Response::success([
            'id' => $id,
            'name' => $name,
            'updatedAt' => date('Y-m-d H:i:s')
        ], 'Report updated');
    }

    /**
     * DELETE /api/custom-reports/{id}
     */
    public function delete($id) {
        $admin = $this->requireAdmin();

        $id = trim((string)$id);
        if ($id === '') {
            Response::error('Report id is required', 400);
            return;
        }

        $db = Database::getInstance();
        $existing = $db->fetchOne(
            "SELECT id, name FROM custom_reports WHERE id = :id",
            ['id' => $id]
        );

        if (!$existing) {
            Response::error('Report not found', 404);
            return;
        }

        $db->query(
            "DELETE FROM custom_reports WHERE id = :id",
            ['id' => $id]
        );

        CustomReportOperationLog::reportDeleted($id, $existing['name'] ?? '', $admin['userId'] ?? null);

        Response::success(null, 'Report deleted');
    }

    /**
     * GET /api/custom-reports/data-sources
     */
    public function listDataSources() {
        $this->requireAdmin();

        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT
                id,
                display_name AS displayName,
                source_type AS sourceType,
                schema_name AS schemaName,
                object_name AS objectName,
                query_handler AS queryHandler,
                created_at AS createdAt
             FROM report_data_sources
             WHERE is_detail_only = 0
             ORDER BY display_name ASC"
        );

        Response::success(['items' => $rows]);
    }

    /**
     * GET /api/custom-reports/data-sources/{dataSourceId}
     */
    public function getDataSource($dataSourceId) {
        $this->requireAdmin();

        $db = Database::getInstance();
        if (!$this->requireSelectableDataSource($db, $dataSourceId)) {
            return;
        }

        $row = $db->fetchOne(
            "SELECT
                id,
                display_name AS displayName,
                source_type AS sourceType,
                schema_name AS schemaName,
                object_name AS objectName,
                query_handler AS queryHandler,
                created_at AS createdAt
             FROM report_data_sources
             WHERE id = :id",
            ['id' => $dataSourceId]
        );
        if (!$row) {
            Response::error('Data source not found', 404);
            return;
        }

        $fields = $this->loadDataSourceFields($db, $dataSourceId, true);
        Response::success([
            'dataSource' => $row,
            'fields' => $fields
        ]);
    }

    /**
     * GET /api/custom-reports/data-sources/{dataSourceId}/rows
     */
    public function getDataSourceRows($dataSourceId) {
        $this->requireAdmin();

        $db = Database::getInstance();
        if (!$this->requireSelectableDataSource($db, $dataSourceId)) {
            return;
        }

        $ctx = $this->loadDataSourceQueryContext($db, $dataSourceId);
        if ($ctx === null) {
            Response::error('Data source not found', 404);
            return;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(200, (int)($_GET['per_page'] ?? 10)));
        $search = trim((string)($_GET['search'] ?? ''));

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }

        $allowedFields = $ctx['columnNames'];
        if (!$allowedFields) {
            Response::error('Data source has no fields', 400);
            return;
        }

        $selectList = implode(', ', array_map(function ($col) {
            return $this->quoteIdent($col);
        }, $allowedFields));

        $sql = "SELECT {$selectList} FROM {$ctx['fromSql']} WHERE 1=1";
        $params = [];

        $this->applySalesScope($sql, $params, $scope, $allowedFields);
        $this->applyWidgetSearch($sql, $params, $allowedFields, $search);
        $this->applyWidgetFilters($sql, $params, $allowedFields, $ctx['fieldsByName']);

        $sortClauses = $this->buildWidgetSortClauses($allowedFields);
        if (!$sortClauses) {
            $sortClauses[] = $this->quoteIdent($allowedFields[0]) . ' DESC';
        }
        $sql .= ' ORDER BY ' . implode(', ', $sortClauses);

        $countSql = preg_replace('/^SELECT .+ FROM /', 'SELECT COUNT(*) as count FROM ', $sql, 1);
        $countSql = substr($countSql, 0, strpos($countSql, 'ORDER BY'));
        $countResult = $db->fetchOne($countSql, $params);
        $total = (int)($countResult['count'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $rows = $db->fetchAll($sql, $params);
        Response::paginated($rows, $total, $page, $perPage);
    }

    /**
     * GET /api/custom-reports/data-sources/{dataSourceId}/column-values
     */
    public function getDataSourceColumnValues($dataSourceId) {
        $this->requireAdmin();

        $db = Database::getInstance();
        if (!$this->requireSelectableDataSource($db, $dataSourceId)) {
            return;
        }

        $ctx = $this->loadDataSourceQueryContext($db, $dataSourceId);
        if ($ctx === null) {
            Response::error('Data source not found', 404);
            return;
        }

        $field = trim((string)($_GET['field'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));
        $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));

        $allowedFields = $ctx['columnNames'];
        if (!$allowedFields) {
            Response::error('Data source has no fields', 400);
            return;
        }
        if (!in_array($field, $allowedFields, true)) {
            Response::error('Invalid field', 400);
            return;
        }

        $ident = $this->quoteIdent($field);
        if ($ident === null) {
            Response::error('Invalid field', 400);
            return;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        if ($scope['scope'] === 'none') {
            Response::success([
                'field' => $field,
                'values' => []
            ]);
            return;
        }

        $sql = "SELECT DISTINCT TRIM(CAST({$ident} AS CHAR)) AS value
                FROM {$ctx['fromSql']}
                WHERE TRIM(COALESCE(CAST({$ident} AS CHAR), '')) <> ''";
        $params = [];

        $this->applySalesScope($sql, $params, $scope, $allowedFields);
        $this->applyWidgetFilters(
            $sql,
            $params,
            $allowedFields,
            $ctx['fieldsByName'],
            $this->parseFiltersParam(),
            $field
        );

        if ($search !== '') {
            $sql .= ' AND CAST(' . $ident . ' AS CHAR) LIKE :column_value_search';
            $params['column_value_search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY value ASC LIMIT ' . $limit;

        $rows = $db->fetchAll($sql, $params);
        $values = [];
        foreach ($rows as $row) {
            $values[] = (string)($row['value'] ?? '');
        }

        Response::success([
            'field' => $field,
            'values' => $values
        ]);
    }

    /**
     * POST /api/custom-reports/{id}/widgets
     * Body: { name, dataSourceId, viewConfig }
     */
    public function createWidget($reportId) {
        $admin = $this->requireAdmin();

        $reportId = trim((string)$reportId);
        if ($reportId === '') {
            Response::error('Report id is required', 400);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim((string)($data['name'] ?? ''));
        $dataSourceId = trim((string)($data['dataSourceId'] ?? ''));

        if ($name === '') {
            Response::error('Widget name is required', 400);
            return;
        }
        if ($dataSourceId === '') {
            Response::error('Data source is required', 400);
            return;
        }

        $db = Database::getInstance();
        $report = $db->fetchOne(
            "SELECT id FROM custom_reports WHERE id = :id",
            ['id' => $reportId]
        );
        if (!$report) {
            Response::error('Report not found', 404);
            return;
        }

        if (!$this->requireSelectableDataSource($db, $dataSourceId)) {
            return;
        }

        $fields = $this->loadDataSourceFields($db, $dataSourceId);
        $viewConfig = $this->sanitizeViewConfig($data['viewConfig'] ?? null, $fields);
        if (empty($viewConfig['types'])) {
            Response::error('Widget type is required', 400);
            return;
        }

        $firstKind = (string)($viewConfig['types'][0]['kind'] ?? 'table');
        $widgetType = $firstKind === 'chart' ? 'chart' : 'table';
        $id = $this->generateUuid();
        $createdBy = (string)$admin['userId'];
        $createdAt = date('Y-m-d H:i:s');
        $viewConfig['types'] = array_map(function ($type) use ($createdBy, $createdAt) {
            $next = $type;
            if (empty($next['createdBy'])) {
                $next['createdBy'] = $createdBy;
            }
            if (empty($next['createdAt'])) {
                $next['createdAt'] = $createdAt;
            }
            return $next;
        }, $viewConfig['types']);

        $db->query(
            "INSERT INTO report_widgets (id, report_id, data_source_id, widget_type, name, view_config, created_by, created_at, updated_at)
             VALUES (:id, :report_id, :data_source_id, :widget_type, :name, :view_config, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            [
                'id' => $id,
                'report_id' => $reportId,
                'data_source_id' => $dataSourceId,
                'widget_type' => $widgetType,
                'name' => $name,
                'view_config' => json_encode($viewConfig),
                'created_by' => $createdBy
            ]
        );

        CustomReportOperationLog::widgetCreated($reportId, $id, $name, $createdBy);

        Response::success([
            'id' => $id,
            'reportId' => $reportId,
            'dataSourceId' => $dataSourceId,
            'widgetType' => $widgetType,
            'name' => $name,
            'viewConfig' => $viewConfig,
            'createdBy' => $createdBy,
            'createdAt' => date('Y-m-d H:i:s')
        ], 'Widget created', 201);
    }

    /**
     * PUT/PATCH /api/custom-reports/{reportId}/widgets/{widgetId}
     * Body: { name?, dataSourceId?, viewConfig? }
     */
    public function updateWidget($reportId, $widgetId) {
        $admin = $this->requireAdmin();

        $reportId = trim((string)$reportId);
        $widgetId = trim((string)$widgetId);
        if ($reportId === '' || $widgetId === '') {
            Response::error('Report id and widget id are required', 400);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $db = Database::getInstance();
        $widget = $db->fetchOne(
            "SELECT id, data_source_id AS dataSourceId, widget_type AS widgetType, name, view_config AS viewConfig
             FROM report_widgets
             WHERE id = :id AND report_id = :report_id",
            ['id' => $widgetId, 'report_id' => $reportId]
        );
        if (!$widget) {
            Response::error('Widget not found', 404);
            return;
        }

        $name = array_key_exists('name', $data)
            ? trim((string)$data['name'])
            : trim((string)($widget['name'] ?? ''));
        $dataSourceId = array_key_exists('dataSourceId', $data)
            ? trim((string)$data['dataSourceId'])
            : trim((string)($widget['dataSourceId'] ?? ''));
        $widgetType = trim((string)($widget['widgetType'] ?? 'table'));
        if ($widgetType === '') {
            $widgetType = 'table';
        }

        if ($name === '') {
            Response::error('Widget name is required', 400);
            return;
        }
        if ($dataSourceId === '') {
            Response::error('Data source is required', 400);
            return;
        }

        if (!$this->requireSelectableDataSource($db, $dataSourceId)) {
            return;
        }

        $beforeViewConfig = $this->parseViewConfig($widget['viewConfig'] ?? null);
        $viewConfig = $beforeViewConfig;
        $viewConfigChanged = false;
        if (array_key_exists('viewConfig', $data)) {
            $fields = $this->loadDataSourceFields($db, $dataSourceId);
            $viewConfig = $this->sanitizeViewConfig($data['viewConfig'], $fields);
            $viewConfigChanged = true;
        }

        $db->query(
            "UPDATE report_widgets
             SET data_source_id = :data_source_id,
                 widget_type = :widget_type,
                 name = :name,
                 view_config = :view_config,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND report_id = :report_id",
            [
                'id' => $widgetId,
                'report_id' => $reportId,
                'data_source_id' => $dataSourceId,
                'widget_type' => $widgetType,
                'name' => $name,
                'view_config' => json_encode($viewConfig)
            ]
        );

        $summaryZh = '';
        $summaryEn = '';
        if ($viewConfigChanged) {
            list($summaryZh, $summaryEn) = CustomReportOperationLog::summarizeViewConfigChange(
                $beforeViewConfig,
                $viewConfig
            );
        } elseif ($name !== trim((string)($widget['name'] ?? ''))
            || $dataSourceId !== trim((string)($widget['dataSourceId'] ?? ''))
        ) {
            $summaryZh = '更新名称或数据源';
            $summaryEn = 'updated name or data source';
        }
        CustomReportOperationLog::widgetUpdated(
            $reportId,
            $widgetId,
            $name,
            $summaryZh,
            $summaryEn,
            $admin['userId'] ?? null
        );

        Response::success([
            'id' => $widgetId,
            'reportId' => $reportId,
            'dataSourceId' => $dataSourceId,
            'widgetType' => $widgetType,
            'name' => $name,
            'viewConfig' => $viewConfig,
            'updatedAt' => date('Y-m-d H:i:s')
        ], 'Widget updated');
    }

    /**
     * DELETE /api/custom-reports/{reportId}/widgets/{widgetId}
     */
    public function deleteWidget($reportId, $widgetId) {
        $admin = $this->requireAdmin();

        $reportId = trim((string)$reportId);
        $widgetId = trim((string)$widgetId);
        if ($reportId === '' || $widgetId === '') {
            Response::error('Report id and widget id are required', 400);
            return;
        }

        $db = Database::getInstance();
        $widget = $db->fetchOne(
            "SELECT id, name FROM report_widgets WHERE id = :id AND report_id = :report_id",
            ['id' => $widgetId, 'report_id' => $reportId]
        );
        if (!$widget) {
            Response::error('Widget not found', 404);
            return;
        }

        $db->query(
            "DELETE FROM report_widgets WHERE id = :id AND report_id = :report_id",
            ['id' => $widgetId, 'report_id' => $reportId]
        );

        CustomReportOperationLog::widgetDeleted(
            $reportId,
            $widgetId,
            $widget['name'] ?? '',
            $admin['userId'] ?? null
        );

        Response::success(null, 'Widget deleted');
    }

    /**
     * POST /api/custom-reports/{reportId}/widgets/{widgetId}/duplicate
     * Clones widget only; reuses the same data_source_id
     */
    public function duplicateWidget($reportId, $widgetId) {
        $admin = $this->requireAdmin();

        $reportId = trim((string)$reportId);
        $widgetId = trim((string)$widgetId);
        if ($reportId === '' || $widgetId === '') {
            Response::error('Report id and widget id are required', 400);
            return;
        }

        $db = Database::getInstance();
        $widget = $db->fetchOne(
            "SELECT
                w.id,
                w.report_id AS reportId,
                w.data_source_id AS dataSourceId,
                w.widget_type AS widgetType,
                w.name,
                w.view_config AS viewConfig
             FROM report_widgets w
             WHERE w.id = :id AND w.report_id = :report_id",
            ['id' => $widgetId, 'report_id' => $reportId]
        );
        if (!$widget) {
            Response::error('Widget not found', 404);
            return;
        }

        $dataSource = $db->fetchOne(
            "SELECT id, display_name AS displayName
             FROM report_data_sources
             WHERE id = :id",
            ['id' => $widget['dataSourceId']]
        );
        if (!$dataSource) {
            Response::error('Data source not found', 404);
            return;
        }

        $widgetName = trim((string)($widget['name'] ?? ''));
        if ($widgetName === '') {
            $widgetName = trim((string)($dataSource['displayName'] ?? ''));
        }
        if ($widgetName === '') {
            $widgetName = 'Widget';
        }
        $widgetName = $this->nextUniqueWidgetCopyName($db, $reportId, $widgetName);

        $viewConfig = $this->parseViewConfig($widget['viewConfig'] ?? null);
        $newWidgetId = $this->generateUuid();
        $createdBy = (string)$admin['userId'];
        $db->query(
            "INSERT INTO report_widgets (
                id, report_id, data_source_id, widget_type, name, view_config, created_by, created_at, updated_at
             ) VALUES (
                :id, :report_id, :data_source_id, :widget_type, :name, :view_config, :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             )",
            [
                'id' => $newWidgetId,
                'report_id' => $reportId,
                'data_source_id' => $widget['dataSourceId'],
                'widget_type' => $widget['widgetType'],
                'name' => $widgetName,
                'view_config' => json_encode($viewConfig),
                'created_by' => $createdBy
            ]
        );

        CustomReportOperationLog::widgetDuplicated(
            $reportId,
            $widgetId,
            $newWidgetId,
            $widgetName,
            $createdBy
        );

        Response::success([
            'id' => $newWidgetId,
            'reportId' => $reportId,
            'dataSourceId' => $widget['dataSourceId'],
            'dataSourceName' => $dataSource['displayName'],
            'widgetType' => $widget['widgetType'],
            'name' => $widgetName,
            'viewConfig' => $viewConfig,
            'createdBy' => $createdBy,
            'createdAt' => date('Y-m-d H:i:s')
        ], 'Widget duplicated', 201);
    }

    /**
     * GET /api/custom-reports/{id}
     * Report + widgets (+ data source display name)
     */
    public function show($id) {
        $this->requireAdmin();

        $id = trim((string)$id);
        if ($id === '') {
            Response::error('Report id is required', 400);
            return;
        }

        $db = Database::getInstance();
        $report = $db->fetchOne(
            "SELECT
                cr.id,
                cr.name,
                cr.created_by AS createdBy,
                cr.created_at AS createdAt,
                cr.updated_at AS updatedAt,
                au.fullName AS createdByName
             FROM custom_reports cr
             LEFT JOIN adminUsers au ON au.id = CAST(cr.created_by AS UNSIGNED)
             WHERE cr.id = :id",
            ['id' => $id]
        );

        if (!$report) {
            Response::error('Report not found', 404);
            return;
        }

        $widgets = $db->fetchAll(
            "SELECT
                w.id,
                w.report_id AS reportId,
                w.data_source_id AS dataSourceId,
                w.widget_type AS widgetType,
                w.name,
                w.view_config AS viewConfig,
                w.created_by AS createdBy,
                w.created_at AS createdAt,
                w.updated_at AS updatedAt,
                au.fullName AS createdByName,
                ds.display_name AS dataSourceName,
                ds.source_type AS dataSourceType,
                ds.object_name AS dataSourceObject
             FROM report_widgets w
             LEFT JOIN report_data_sources ds ON ds.id = w.data_source_id
             LEFT JOIN adminUsers au ON au.id = CAST(w.created_by AS UNSIGNED)
             WHERE w.report_id = :report_id
             ORDER BY w.created_at ASC",
            ['report_id' => $id]
        );

        foreach ($widgets as &$widgetRow) {
            $widgetRow['viewConfig'] = $this->parseViewConfig($widgetRow['viewConfig'] ?? null);
            $widgetRow['fields'] = $this->loadDataSourceFields($db, $widgetRow['dataSourceId'] ?? '');
            $widgetRow['detailPanels'] = $this->loadDetailPanels($db, $widgetRow['dataSourceId'] ?? '');
        }
        unset($widgetRow);

        Response::success([
            'report' => $report,
            'widgets' => $widgets
        ]);
    }

    /**
     * GET /api/custom-reports/transactions
     * Paginated vAllTransactions (widget data source)
     */
    public function getTransactions() {
        $this->requireAdmin();

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, (int)($_GET['per_page'] ?? 10));

        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }

        $transactionType = $_GET['type'] ?? null;
        $search = trim((string)($_GET['search'] ?? ''));
        $allowedSortFields = [
            'requestedAt',
            'firstName',
            'transactionType',
            'amount',
            'paymentMethod',
            'status'
        ];

        $sortClauses = [];
        $sortsRaw = $_GET['sorts'] ?? null;
        if ($sortsRaw !== null) {
            $sorts = json_decode($sortsRaw, true);
            if (is_array($sorts)) {
                foreach ($sorts as $sortRule) {
                    if (!is_array($sortRule)) {
                        continue;
                    }
                    $field = $sortRule['field'] ?? '';
                    if (!in_array($field, $allowedSortFields, true)) {
                        continue;
                    }
                    $direction = strtolower((string)($sortRule['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
                    $sortClauses[] = "{$field} {$direction}";
                }
            }
        }

        if (!$sortClauses) {
            $sortField = $_GET['sort_field'] ?? 'requestedAt';
            $sortDirection = strtolower((string)($_GET['sort_direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            if (!in_array($sortField, $allowedSortFields, true)) {
                $sortField = 'requestedAt';
            }
            $sortClauses[] = "{$sortField} {$sortDirection}";
        }

        $sql = "SELECT * FROM vAllTransactions WHERE 1=1";
        $params = [];

        if ($scope['scope'] === 'own') {
            $sql .= " AND userId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
        }

        if ($transactionType) {
            $sql .= " AND transactionType = :transactionType";
            $params['transactionType'] = $transactionType;
        }

        if ($search !== '') {
            $sql .= " AND (
                firstName LIKE :search
                OR lastName LIKE :search
                OR CONCAT(COALESCE(firstName, ''), ' ', COALESCE(lastName, '')) LIKE :search
                OR email LIKE :search
                OR transactionId LIKE :search
                OR CAST(userId AS CHAR) LIKE :search
            )";
            $params['search'] = '%' . $search . '%';
        }

        $filtersRaw = $_GET['filters'] ?? '[]';
        $filters = json_decode($filtersRaw, true);
        if (!is_array($filters)) {
            $filters = [];
        }

        $allowedFilterFields = [
            'requestedAt',
            'firstName',
            'transactionType',
            'amount',
            'paymentMethod',
            'status'
        ];
        $allowedOps = [
            'contains',
            'not_contains',
            'equals',
            'not_equals',
            'starts_with',
            'ends_with',
            'is_empty',
            'is_not_empty',
            'gt',
            'lt',
            'gte',
            'lte'
        ];

        $filterIndex = 0;
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? '';
            $op = $filter['op'] ?? 'contains';
            $value = isset($filter['value']) ? trim((string)$filter['value']) : '';

            if (!in_array($field, $allowedFilterFields, true) || !in_array($op, $allowedOps, true)) {
                continue;
            }

            $key = 'f' . $filterIndex;

            if ($op === 'is_empty') {
                if ($field === 'firstName') {
                    $sql .= " AND (TRIM(COALESCE(firstName, '')) = '' AND TRIM(COALESCE(lastName, '')) = '')";
                } else {
                    $sql .= " AND (TRIM(COALESCE(CAST({$field} AS CHAR), '')) = '')";
                }
                $filterIndex++;
                continue;
            }

            if ($op === 'is_not_empty') {
                if ($field === 'firstName') {
                    $sql .= " AND (TRIM(COALESCE(firstName, '')) <> '' OR TRIM(COALESCE(lastName, '')) <> '')";
                } else {
                    $sql .= " AND (TRIM(COALESCE(CAST({$field} AS CHAR), '')) <> '')";
                }
                $filterIndex++;
                continue;
            }

            if ($value === '' && !in_array($op, ['is_empty', 'is_not_empty'], true)) {
                continue;
            }

            if ($field === 'amount' && in_array($op, ['equals', 'not_equals', 'gt', 'lt', 'gte', 'lte'], true)) {
                if (!is_numeric($value)) {
                    continue;
                }
                $map = [
                    'equals' => '=',
                    'not_equals' => '<>',
                    'gt' => '>',
                    'lt' => '<',
                    'gte' => '>=',
                    'lte' => '<='
                ];
                $sql .= " AND amount {$map[$op]} :{$key}";
                $params[$key] = (float)$value;
                $filterIndex++;
                continue;
            }

            if ($field === 'requestedAt' && in_array($op, ['equals', 'not_equals', 'gt', 'lt', 'gte', 'lte'], true)) {
                $map = [
                    'equals' => '=',
                    'not_equals' => '<>',
                    'gt' => '>',
                    'lt' => '<',
                    'gte' => '>=',
                    'lte' => '<='
                ];
                $sql .= " AND DATE(requestedAt) {$map[$op]} :{$key}";
                $params[$key] = $value;
                $filterIndex++;
                continue;
            }

            if ($field === 'firstName') {
                if ($op === 'contains') {
                    $sql .= " AND (firstName LIKE :{$key} OR lastName LIKE :{$key} OR CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) LIKE :{$key})";
                    $params[$key] = '%' . $value . '%';
                } elseif ($op === 'not_contains') {
                    $sql .= " AND (COALESCE(firstName,'') NOT LIKE :{$key} AND COALESCE(lastName,'') NOT LIKE :{$key} AND CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) NOT LIKE :{$key})";
                    $params[$key] = '%' . $value . '%';
                } elseif ($op === 'equals') {
                    $sql .= " AND (CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) = :{$key} OR firstName = :{$key} OR lastName = :{$key})";
                    $params[$key] = $value;
                } elseif ($op === 'not_equals') {
                    $sql .= " AND CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) <> :{$key}";
                    $params[$key] = $value;
                } elseif ($op === 'starts_with') {
                    $sql .= " AND (firstName LIKE :{$key} OR lastName LIKE :{$key} OR CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) LIKE :{$key})";
                    $params[$key] = $value . '%';
                } elseif ($op === 'ends_with') {
                    $sql .= " AND (firstName LIKE :{$key} OR lastName LIKE :{$key} OR CONCAT(COALESCE(firstName,''), ' ', COALESCE(lastName,'')) LIKE :{$key})";
                    $params[$key] = '%' . $value;
                }
                $filterIndex++;
                continue;
            }

            if ($op === 'contains') {
                $sql .= " AND CAST({$field} AS CHAR) LIKE :{$key}";
                $params[$key] = '%' . $value . '%';
            } elseif ($op === 'not_contains') {
                $sql .= " AND CAST({$field} AS CHAR) NOT LIKE :{$key}";
                $params[$key] = '%' . $value . '%';
            } elseif ($op === 'equals') {
                $sql .= " AND CAST({$field} AS CHAR) = :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'not_equals') {
                $sql .= " AND CAST({$field} AS CHAR) <> :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'starts_with') {
                $sql .= " AND CAST({$field} AS CHAR) LIKE :{$key}";
                $params[$key] = $value . '%';
            } elseif ($op === 'ends_with') {
                $sql .= " AND CAST({$field} AS CHAR) LIKE :{$key}";
                $params[$key] = '%' . $value;
            } elseif ($op === 'gt') {
                $sql .= " AND CAST({$field} AS CHAR) > :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'lt') {
                $sql .= " AND CAST({$field} AS CHAR) < :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'gte') {
                $sql .= " AND CAST({$field} AS CHAR) >= :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'lte') {
                $sql .= " AND CAST({$field} AS CHAR) <= :{$key}";
                $params[$key] = $value;
            }

            $filterIndex++;
        }

        $sql .= ' ORDER BY ' . implode(', ', $sortClauses);

        $db = Database::getInstance();
        $countSql = str_replace('SELECT *', 'SELECT COUNT(*) as count', $sql);
        $countSql = substr($countSql, 0, strpos($countSql, 'ORDER BY'));
        $countResult = $db->fetchOne($countSql, $params);
        $total = (int)($countResult['count'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $transactions = $db->fetchAll($sql, $params);

        Response::paginated($transactions, $total, $page, $perPage);
    }

    /**
     * GET /api/custom-reports/{reportId}/widgets/{widgetId}/rows
     */
    public function getWidgetRows($reportId, $widgetId) {
        $this->requireAdmin();

        $ctx = $this->loadWidgetQueryContext($reportId, $widgetId);
        if ($ctx === null) {
            return;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(5000, (int)($_GET['per_page'] ?? 1000)));
        $search = trim((string)($_GET['search'] ?? ''));
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');

        try {
            $result = $this->queryWidgetRows(
                $ctx,
                $search,
                $_GET['filters'] ?? '[]',
                $_GET['sorts'] ?? null,
                $scope,
                ($page - 1) * $perPage,
                $perPage
            );
        } catch (RuntimeException $e) {
            $code = (int)$e->getCode();
            Response::error($e->getMessage(), $code >= 400 ? $code : 400);
            return;
        }

        Response::paginated($result['rows'], $result['total'], $page, $perPage);
    }

    /**
     * GET /api/custom-reports/{reportId}/widgets/{widgetId}/column-values
     * Query: field, search, limit, filters
     */
    public function getWidgetColumnValues($reportId, $widgetId) {
        $this->requireAdmin();

        $ctx = $this->loadWidgetQueryContext($reportId, $widgetId);
        if ($ctx === null) {
            return;
        }

        $field = trim((string)($_GET['field'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));
        $limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));

        $allowedFields = $ctx['columnNames'];
        if (!$allowedFields) {
            Response::error('Data source has no fields', 400);
            return;
        }
        if (!in_array($field, $allowedFields, true)) {
            Response::error('Invalid field', 400);
            return;
        }

        $ident = $this->quoteIdent($field);
        if ($ident === null) {
            Response::error('Invalid field', 400);
            return;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        if ($scope['scope'] === 'none') {
            Response::success([
                'field' => $field,
                'values' => []
            ]);
            return;
        }

        $sql = "SELECT DISTINCT TRIM(CAST({$ident} AS CHAR)) AS value
                FROM {$ctx['fromSql']}
                WHERE TRIM(COALESCE(CAST({$ident} AS CHAR), '')) <> ''";
        $params = [];

        $this->applySalesScope($sql, $params, $scope, $allowedFields);
        $this->applyWidgetFilters(
            $sql,
            $params,
            $allowedFields,
            $ctx['fieldsByName'],
            $this->parseFiltersParam(),
            $field
        );

        if ($search !== '') {
            $sql .= ' AND CAST(' . $ident . ' AS CHAR) LIKE :column_value_search';
            $params['column_value_search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY value ASC LIMIT ' . $limit;

        $db = Database::getInstance();
        $rows = $db->fetchAll($sql, $params);
        $values = [];
        foreach ($rows as $row) {
            $values[] = (string)($row['value'] ?? '');
        }

        Response::success([
            'field' => $field,
            'values' => $values
        ]);
    }

    /**
     * GET /api/custom-reports/{reportId}/widgets/{widgetId}/detail-rows
     * Query: panelId, parentValue, page, per_page, search
     */
    public function getWidgetDetailRows($reportId, $widgetId) {
        $this->requireAdmin();

        $ctx = $this->loadWidgetQueryContext($reportId, $widgetId);
        if ($ctx === null) {
            return;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(10000, (int)($_GET['per_page'] ?? 10)));
        $search = trim((string)($_GET['search'] ?? ''));
        $panelId = trim((string)($_GET['panelId'] ?? ''));
        $parentValue = trim((string)($_GET['parentValue'] ?? ''));

        if ($panelId === '') {
            Response::error('Panel id is required', 400);
            return;
        }

        $db = Database::getInstance();
        $panel = $this->findDetailPanel($db, $ctx['widget']['dataSourceId'] ?? '', $panelId);
        if (!$panel) {
            Response::error('Detail panel not found', 404);
            return;
        }

        $parentField = (string)($panel['parentField'] ?? '');
        $childField = (string)($panel['childField'] ?? '');
        if (!in_array($parentField, $ctx['columnNames'], true)) {
            Response::error('Invalid parent field', 400);
            return;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        if ($scope['scope'] === 'none' || $parentValue === '') {
            Response::paginated([], 0, $page, $perPage);
            return;
        }

        $childCtx = $this->loadDataSourceQueryContext($db, $panel['childDataSourceId']);
        if ($childCtx === null) {
            Response::error('Child data source not found', 404);
            return;
        }

        $childIdent = $this->quoteIdent($childField);
        if ($childIdent === null || !in_array($childField, $childCtx['columnNames'], true)) {
            Response::error('Invalid child field', 400);
            return;
        }

        $allowedFields = $childCtx['columnNames'];
        if (!$allowedFields) {
            Response::error('Data source has no fields', 400);
            return;
        }

        $selectList = implode(', ', array_map(function ($col) {
            return $this->quoteIdent($col);
        }, $allowedFields));

        $sql = "SELECT {$selectList} FROM {$childCtx['fromSql']} WHERE {$childIdent} = :parent_value";
        $params = ['parent_value' => $parentValue];

        $this->applySalesScope($sql, $params, $scope, $allowedFields);
        $this->applyWidgetSearch($sql, $params, $allowedFields, $search);

        $sortIdent = $this->quoteIdent($allowedFields[0]);
        $sql .= ' ORDER BY ' . $sortIdent . ' ASC';

        $countSql = preg_replace('/^SELECT .+ FROM /', 'SELECT COUNT(*) as count FROM ', $sql, 1);
        $countSql = substr($countSql, 0, strpos($countSql, 'ORDER BY'));
        $countResult = $db->fetchOne($countSql, $params);
        $total = (int)($countResult['count'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $rows = $db->fetchAll($sql, $params);
        Response::paginated($rows, $total, $page, $perPage);
    }

    /**
     * GET /api/custom-reports/{reportId}/widgets/{widgetId}/chart
     */
    public function getWidgetChart($reportId, $widgetId) {
        $this->requireAdmin();

        $ctx = $this->loadWidgetQueryContext($reportId, $widgetId);
        if ($ctx === null) {
            return;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
        if ($scope['scope'] === 'none') {
            Response::success([
                'labels' => [],
                'series' => [],
                'max' => 0
            ]);
            return;
        }

        $activeTypeId = (string)($ctx['viewConfig']['activeView'] ?? 'chart');
        $chart = [];
        if (isset($ctx['viewConfig']['views'][$activeTypeId]) && is_array($ctx['viewConfig']['views'][$activeTypeId])) {
            $chart = $ctx['viewConfig']['views'][$activeTypeId];
        } elseif (isset($ctx['viewConfig']['views']['chart']) && is_array($ctx['viewConfig']['views']['chart'])) {
            $chart = $ctx['viewConfig']['views']['chart'];
        }
        $overrides = [
            'chartType' => $_GET['chartType'] ?? null,
            'xField' => $_GET['xField'] ?? null,
            'yField' => $_GET['yField'] ?? null,
            'sortBy' => $_GET['sortBy'] ?? null,
            'ySortBy' => $_GET['ySortBy'] ?? null,
            'omitZero' => array_key_exists('omitZero', $_GET) ? $_GET['omitZero'] : null,
            'groupBy' => $_GET['groupBy'] ?? null,
            'cumulative' => array_key_exists('cumulative', $_GET) ? $_GET['cumulative'] : null,
            'range' => $_GET['range'] ?? null,
            'rangeMin' => array_key_exists('rangeMin', $_GET) ? $_GET['rangeMin'] : null,
            'rangeMax' => array_key_exists('rangeMax', $_GET) ? $_GET['rangeMax'] : null
        ];
        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                if (($key === 'rangeMin' || $key === 'rangeMax') && $value === '') {
                    $chart[$key] = null;
                }
                continue;
            }
            if ($key === 'omitZero' || $key === 'cumulative') {
                $chart[$key] = in_array((string)$value, ['1', 'true', 'yes'], true);
            } elseif ($key === 'rangeMin' || $key === 'rangeMax') {
                $chart[$key] = is_numeric($value) ? (float)$value : null;
            } else {
                $chart[$key] = $value;
            }
        }
        $chart = $this->sanitizeChartConfig($chart, $ctx['fields']);

        $chartType = $chart['chartType'] ?? '';
        $xField = $chart['xField'] ?? '';
        $yField = $chart['yField'] ?? '';
        if ($chartType === '' || $xField === '' || $yField === '') {
            Response::success([
                'labels' => [],
                'series' => [],
                'max' => 0,
                'ready' => false
            ]);
            return;
        }

        $xIdent = $this->quoteIdent($xField);
        $groupBy = $chart['groupBy'] ?? 'none';
        $hasGroup = $groupBy !== 'none' && $groupBy !== '' && $groupBy !== $xField;
        $groupIdent = $hasGroup ? $this->quoteIdent($groupBy) : null;

        if ($yField === 'count') {
            $yExpr = 'COUNT(*)';
        } else {
            $yIdent = $this->quoteIdent($yField);
            if ($yIdent === null) {
                $yExpr = 'COUNT(*)';
            } elseif ($this->isNumericChartField($ctx['fieldsByName'][$yField] ?? [])) {
                $yExpr = 'SUM(' . $yIdent . ')';
            } else {
                $yExpr = 'COUNT(' . $yIdent . ')';
            }
        }

        $select = "{$xIdent} AS xLabel, {$yExpr} AS yValue";
        if ($hasGroup) {
            $select .= ", {$groupIdent} AS seriesName";
        }

        $sql = "SELECT {$select} FROM {$ctx['fromSql']} WHERE 1=1";
        $params = [];
        $this->applySalesScope($sql, $params, $scope, $ctx['columnNames']);
        $this->applyWidgetSearch(
            $sql,
            $params,
            $ctx['columnNames'],
            trim((string)($_GET['search'] ?? ''))
        );
        $this->applyWidgetFilters($sql, $params, $ctx['columnNames'], $ctx['fieldsByName']);

        $sql .= " GROUP BY {$xIdent}";
        if ($hasGroup) {
            $sql .= ", {$groupIdent}";
        }
        $sql .= ' LIMIT 2000';

        $db = Database::getInstance();
        $grouped = $db->fetchAll($sql, $params);

        $omitZero = !empty($chart['omitZero']);
        $points = [];
        foreach ($grouped as $row) {
            $label = trim((string)($row['xLabel'] ?? ''));
            if ($label === '') {
                $label = '(empty)';
            }
            $seriesName = $hasGroup ? trim((string)($row['seriesName'] ?? '')) : 'Value';
            if ($hasGroup && $seriesName === '') {
                $seriesName = '(empty)';
            }
            $value = (float)($row['yValue'] ?? 0);
            if ($omitZero && $value == 0.0) {
                continue;
            }
            $points[] = [
                'label' => $label,
                'series' => $seriesName,
                'value' => $value
            ];
        }

        $totals = [];
        foreach ($points as $point) {
            $totals[$point['label']] = ($totals[$point['label']] ?? 0) + $point['value'];
        }

        $labels = array_keys($totals);
        $sortBy = $chart['sortBy'] ?? 'label_asc';
        $ySortBy = $chart['ySortBy'] ?? 'label_asc';
        $labelSort = $sortBy;
        if ($sortBy === 'manual' && in_array($ySortBy, ['value_asc', 'value_desc'], true)) {
            $labelSort = $ySortBy;
        }
        if ($labelSort !== 'manual') {
            usort($labels, function ($a, $b) use ($labelSort, $totals) {
                if ($labelSort === 'label_desc') {
                    return strcasecmp($b, $a);
                }
                if ($labelSort === 'value_asc') {
                    return $totals[$a] <=> $totals[$b];
                }
                if ($labelSort === 'value_desc') {
                    return $totals[$b] <=> $totals[$a];
                }
                return strcasecmp($a, $b);
            });
        }

        $totalLabels = count($labels);
        $allowedPerPage = [50, 100, 150];
        $perPage = (int)($_GET['per_page'] ?? ($chart['perPage'] ?? 50));
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }
        $page = max(1, (int)($_GET['page'] ?? ($chart['page'] ?? 1)));
        $totalPages = $totalLabels > 0 ? (int)ceil($totalLabels / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $labels = $totalLabels > 0 ? array_slice($labels, $offset, $perPage) : [];
        $truncated = $totalLabels > count($labels);

        $seriesNames = [];
        $seriesTotals = [];
        foreach ($points as $point) {
            $seriesNames[$point['series']] = true;
            $seriesTotals[$point['series']] = ($seriesTotals[$point['series']] ?? 0) + $point['value'];
        }
        $seriesNames = array_keys($seriesNames);
        $ySortBy = $chart['ySortBy'] ?? 'label_asc';
        if ($ySortBy !== 'manual') {
            usort($seriesNames, function ($a, $b) use ($ySortBy, $seriesTotals) {
                if ($ySortBy === 'label_desc') {
                    return strcasecmp($b, $a);
                }
                if ($ySortBy === 'value_asc') {
                    return $seriesTotals[$a] <=> $seriesTotals[$b];
                }
                if ($ySortBy === 'value_desc') {
                    return $seriesTotals[$b] <=> $seriesTotals[$a];
                }
                return strcasecmp($a, $b);
            });
        }
        if (!$seriesNames) {
            $seriesNames = ['Value'];
        }

        $lookup = [];
        foreach ($points as $point) {
            $lookup[$point['series'] . "\0" . $point['label']] = $point['value'];
        }

        $series = [];
        foreach ($seriesNames as $name) {
            $values = [];
            foreach ($labels as $label) {
                $values[] = (float)($lookup[$name . "\0" . $label] ?? 0);
            }
            if (!empty($chart['cumulative'])) {
                $running = 0;
                foreach ($values as $i => $value) {
                    $running += $value;
                    $values[$i] = $running;
                }
            }
            $series[] = [
                'name' => $name,
                'values' => $values
            ];
        }

        $max = $this->chartSeriesMax($series);
        $min = 0.0;
        $rangeMode = ($chart['range'] ?? 'auto') === 'custom' ? 'custom' : 'auto';
        if ($rangeMode === 'custom') {
            $rangeMin = array_key_exists('rangeMin', $chart) && is_numeric($chart['rangeMin'])
                ? (float)$chart['rangeMin']
                : null;
            $rangeMax = array_key_exists('rangeMax', $chart) && is_numeric($chart['rangeMax'])
                ? (float)$chart['rangeMax']
                : null;
            if ($rangeMin === null && $rangeMax === null) {
                $rangeMode = 'auto';
            } else {
                $min = $rangeMin ?? 0.0;
                if ($rangeMax !== null) {
                    $max = $rangeMax;
                }
                if (!($max > $min)) {
                    $rangeMode = 'auto';
                    $min = 0.0;
                    $max = $this->chartSeriesMax($series);
                }
            }
        }
        if ($rangeMode === 'auto' && $max > 0) {
            $magnitude = 10 ** floor(log10($max));
            $max = ceil($max / $magnitude) * $magnitude;
        }
        if ($max <= 0) {
            $max = 1;
        }

        Response::success([
            'labels' => $labels,
            'series' => $series,
            'truncated' => $truncated,
            'totalLabels' => $totalLabels,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'max' => $max,
            'min' => $min,
            'ready' => true,
            'chartType' => $chartType
        ]);
    }

    public function exportWidgetRows($reportId, $widgetId) {
        try {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';

            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $reportId = trim((string)$reportId);
            $widgetId = trim((string)$widgetId);
            if (!$this->isSafeId($reportId) || !$this->isSafeId($widgetId)) {
                Response::error('Invalid report or widget id', 400);
                return;
            }

            $active = CustomReportWidgetExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                CustomReportWidgetExportService::clearActive($adminUserId);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $search = function_exists('mb_substr')
                ? mb_substr(trim((string)($input['search'] ?? '')), 0, 255)
                : substr(trim((string)($input['search'] ?? '')), 0, 255);
            $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
            $sorts = is_array($input['sorts'] ?? null) ? $input['sorts'] : [];
            $columns = is_array($input['columns'] ?? null) ? array_slice($input['columns'], 0, 80) : [];
            $widgetName = trim((string)($input['widgetName'] ?? ''));
            if (function_exists('mb_substr')) {
                $widgetName = mb_substr($widgetName, 0, 120);
            } else {
                $widgetName = substr($widgetName, 0, 120);
            }

            $mode = strtolower(trim((string)($input['mode'] ?? 'all')));
            if ($mode !== 'selected') {
                $mode = 'all';
            }
            $selectedRows = [];
            if ($mode === 'selected') {
                $selectedRows = $this->sanitizeSelectedExportRows($input['rows'] ?? null, $columns);
                if (!$selectedRows) {
                    Response::error('No rows selected', 400);
                    return;
                }
            }

            $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
            $query = [
                'search' => $search,
                'filters' => $filters,
                'sorts' => $sorts,
                'columns' => $columns,
                'widgetName' => $widgetName,
                'mode' => $mode,
                'scope' => [
                    'scope' => (string)($scope['scope'] ?? 'none'),
                    'restrict_to_sales_id' => (int)($scope['restrict_to_sales_id'] ?? 0),
                ],
            ];

            $jobId = str_replace('.', '', uniqid('acrw_', true));
            $fileName = $this->exportFileName($widgetName);
            CustomReportWidgetExportService::ensureExportDir();
            CustomReportWidgetExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
                'fileName' => $fileName,
            ]);
            CustomReportWidgetExportService::writeActive($adminUserId, $jobId);

            if ($mode === 'selected') {
                try {
                    CustomReportWidgetExportService::writeSelectedRows($jobId, $selectedRows);
                } catch (Exception $e) {
                    CustomReportWidgetExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    CustomReportWidgetExportService::clearActive($adminUserId);
                    Response::error('Failed to store selected rows: ' . $e->getMessage(), 500);
                }
            }

            $payload = [
                'type' => 'export_custom_report_widget',
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'userId' => $adminUserId,
                'userType' => 'admin',
                'reportId' => $reportId,
                'widgetId' => $widgetId,
                'query' => $query,
                'requestedAt' => time(),
            ];

            try {
                $this->dispatchSwooleTask($payload);
            } catch (Exception $e) {
                CustomReportWidgetExportService::clearSelectedRows($jobId);
                CustomReportWidgetExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                CustomReportWidgetExportService::clearActive($adminUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    public function exportDataSourceRows($dataSourceId) {
        try {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';

            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $dataSourceId = trim((string)$dataSourceId);
            if (!$this->isSafeId($dataSourceId)) {
                Response::error('Invalid data source id', 400);
                return;
            }

            $db = Database::getInstance();
            if (!$this->requireSelectableDataSource($db, $dataSourceId)) {
                return;
            }

            $active = CustomReportWidgetExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->exportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                CustomReportWidgetExportService::clearActive($adminUserId);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $search = function_exists('mb_substr')
                ? mb_substr(trim((string)($input['search'] ?? '')), 0, 255)
                : substr(trim((string)($input['search'] ?? '')), 0, 255);
            $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
            $sorts = is_array($input['sorts'] ?? null) ? $input['sorts'] : [];
            $columns = is_array($input['columns'] ?? null) ? array_slice($input['columns'], 0, 80) : [];
            $widgetName = trim((string)($input['widgetName'] ?? ''));
            if (function_exists('mb_substr')) {
                $widgetName = mb_substr($widgetName, 0, 120);
            } else {
                $widgetName = substr($widgetName, 0, 120);
            }

            $mode = strtolower(trim((string)($input['mode'] ?? 'all')));
            if ($mode !== 'selected') {
                $mode = 'all';
            }
            $selectedRows = [];
            if ($mode === 'selected') {
                $selectedRows = $this->sanitizeSelectedExportRows($input['rows'] ?? null, $columns);
                if (!$selectedRows) {
                    Response::error('No rows selected', 400);
                    return;
                }
            }

            $scope = AdminSalesPermission::getClientDataScopeForPage('page_fundingreport');
            $query = [
                'search' => $search,
                'filters' => $filters,
                'sorts' => $sorts,
                'columns' => $columns,
                'widgetName' => $widgetName,
                'mode' => $mode,
                'scope' => [
                    'scope' => (string)($scope['scope'] ?? 'none'),
                    'restrict_to_sales_id' => (int)($scope['restrict_to_sales_id'] ?? 0),
                ],
            ];

            $jobId = str_replace('.', '', uniqid('acrs_', true));
            $fileName = $this->exportFileName($widgetName);
            CustomReportWidgetExportService::ensureExportDir();
            CustomReportWidgetExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
                'fileName' => $fileName,
            ]);
            CustomReportWidgetExportService::writeActive($adminUserId, $jobId);

            if ($mode === 'selected') {
                try {
                    CustomReportWidgetExportService::writeSelectedRows($jobId, $selectedRows);
                } catch (Exception $e) {
                    CustomReportWidgetExportService::writeProgress($jobId, [
                        'adminUserId' => $adminUserId,
                        'status' => 'error',
                        'cancelRequested' => false,
                        'percent' => 0,
                        'message' => $e->getMessage(),
                        'file' => null,
                    ]);
                    CustomReportWidgetExportService::clearActive($adminUserId);
                    Response::error('Failed to store selected rows: ' . $e->getMessage(), 500);
                }
            }

            $payload = [
                'type' => 'export_custom_report_widget',
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'userId' => $adminUserId,
                'userType' => 'admin',
                'dataSourceId' => $dataSourceId,
                'reportId' => '',
                'widgetId' => '',
                'query' => $query,
                'requestedAt' => time(),
            ];

            try {
                $this->dispatchSwooleTask($payload);
            } catch (Exception $e) {
                CustomReportWidgetExportService::clearSelectedRows($jobId);
                CustomReportWidgetExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                CustomReportWidgetExportService::clearActive($adminUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    public function exportWidgetActive() {
        try {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $progress = CustomReportWidgetExportService::getActiveForAdmin($adminUserId);
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportWidgetStatus() {
        try {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = CustomReportWidgetExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [CustomReportWidgetExportService::class, 'writeProgress'],
                [CustomReportWidgetExportService::class, 'clearActive']
            );
            Response::success($this->exportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportWidgetCancel() {
        try {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $rawBody = json_decode(file_get_contents('php://input'), true);
            $jobId = '';
            if (is_array($rawBody) && !empty($rawBody['jobId'])) {
                $jobId = trim((string)$rawBody['jobId']);
            } elseif (!empty($_GET['jobId'])) {
                $jobId = trim((string)$_GET['jobId']);
            }
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = CustomReportWidgetExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->exportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                CustomReportWidgetExportService::clearActive($adminUserId);
                Response::success($this->exportProgressPayload($progress), 'Export already failed');
            }
            CustomReportWidgetExportService::requestCancel($jobId);
            $updated = CustomReportWidgetExportService::readProgress($jobId);
            Response::success($this->exportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function exportWidgetDownload() {
        try {
            require_once __DIR__ . '/../services/CustomReportWidgetExportService.php';
            $admin = $this->requireAdmin();
            $adminUserId = (int)($admin['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = CustomReportWidgetExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = CustomReportWidgetExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            CustomReportWidgetExportService::clearActive($adminUserId);

            $filename = trim((string)($progress['fileName'] ?? ''));
            if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.csv$/', $filename)) {
                $filename = 'custom_report_' . date('Y-m-d') . '.csv';
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($csvFile));
            header('Cache-Control: no-store');
            readfile($csvFile);
            exit;
        } catch (Exception $e) {
            Response::error('Failed to download export: ' . $e->getMessage(), 500);
        }
    }

    public function fetchWidgetExportPage($reportId, $widgetId, array $query) {
        $ctx = $this->resolveWidgetQueryContext($reportId, $widgetId);
        $offset = max(0, (int)($query['offset'] ?? 0));
        $limit = max(0, min(1000, (int)($query['limit'] ?? 500)));
        $result = $this->queryWidgetRows(
            $ctx,
            $query['search'] ?? '',
            $query['filters'] ?? [],
            $query['sorts'] ?? [],
            is_array($query['scope'] ?? null) ? $query['scope'] : ['scope' => 'none'],
            $offset,
            $limit
        );
        $result['fieldsByName'] = $ctx['fieldsByName'];
        $result['columnNames'] = $ctx['columnNames'];
        return $result;
    }

    public function fetchDataSourceExportPage($dataSourceId, array $query) {
        $db = Database::getInstance();
        $dataSourceId = trim((string)$dataSourceId);
        $dataSource = $db->fetchOne(
            "SELECT id, is_detail_only AS isDetailOnly FROM report_data_sources WHERE id = :id",
            ['id' => $dataSourceId]
        );
        if (!$dataSource) {
            throw new RuntimeException('Data source not found', 404);
        }
        if ((int)($dataSource['isDetailOnly'] ?? 0) === 1) {
            throw new RuntimeException('This data source is only available as a row detail', 400);
        }
        $ctx = $this->loadDataSourceQueryContext($db, $dataSourceId);
        if ($ctx === null) {
            throw new RuntimeException('Data source not found', 404);
        }
        $offset = max(0, (int)($query['offset'] ?? 0));
        $limit = max(0, min(1000, (int)($query['limit'] ?? 500)));
        $result = $this->queryWidgetRows(
            $ctx,
            $query['search'] ?? '',
            $query['filters'] ?? [],
            $query['sorts'] ?? [],
            is_array($query['scope'] ?? null) ? $query['scope'] : ['scope' => 'none'],
            $offset,
            $limit
        );
        $result['fieldsByName'] = $ctx['fieldsByName'];
        $result['columnNames'] = $ctx['columnNames'];
        return $result;
    }

    private function loadWidgetQueryContext($reportId, $widgetId) {
        try {
            return $this->resolveWidgetQueryContext($reportId, $widgetId);
        } catch (RuntimeException $e) {
            $code = (int)$e->getCode();
            Response::error($e->getMessage(), $code >= 400 ? $code : 400);
            return null;
        }
    }

    private function resolveWidgetQueryContext($reportId, $widgetId) {
        $reportId = trim((string)$reportId);
        $widgetId = trim((string)$widgetId);
        if ($reportId === '' || $widgetId === '') {
            throw new RuntimeException('Report id and widget id are required', 400);
        }

        $db = Database::getInstance();
        $widget = $db->fetchOne(
            "SELECT
                w.id,
                w.data_source_id AS dataSourceId,
                w.view_config AS viewConfig,
                ds.object_name AS objectName,
                ds.schema_name AS schemaName
             FROM report_widgets w
             INNER JOIN report_data_sources ds ON ds.id = w.data_source_id
             WHERE w.id = :id AND w.report_id = :report_id",
            ['id' => $widgetId, 'report_id' => $reportId]
        );
        if (!$widget) {
            throw new RuntimeException('Widget not found', 404);
        }

        $objectName = trim((string)($widget['objectName'] ?? ''));
        $quotedObject = $this->quoteIdent($objectName);
        if ($quotedObject === null) {
            throw new RuntimeException('Invalid data source object', 400);
        }

        $fromSql = $quotedObject;
        $schemaName = trim((string)($widget['schemaName'] ?? ''));
        if ($schemaName !== '') {
            $quotedSchema = $this->quoteIdent($schemaName);
            $currentDb = $db->fetchOne('SELECT DATABASE() AS dbName');
            $currentDbName = trim((string)($currentDb['dbName'] ?? ''));
            if ($quotedSchema !== null && ($currentDbName === '' || $schemaName === $currentDbName)) {
                $fromSql = $quotedSchema . '.' . $quotedObject;
            }
        }

        $fields = $this->loadDataSourceFields($db, $widget['dataSourceId']);
        $columnNames = [];
        $fieldsByName = [];
        foreach ($fields as $field) {
            $columnNames[] = $field['columnName'];
            $fieldsByName[$field['columnName']] = $field;
        }

        return [
            'widget' => $widget,
            'fromSql' => $fromSql,
            'fields' => $fields,
            'columnNames' => $columnNames,
            'fieldsByName' => $fieldsByName,
            'viewConfig' => $this->parseViewConfig($widget['viewConfig'] ?? null)
        ];
    }

    private function queryWidgetRows($ctx, $search, $filters, $sorts, $scope, $offset, $limit) {
        $allowedFields = $ctx['columnNames'];
        if (!$allowedFields) {
            throw new RuntimeException('Data source has no fields', 400);
        }
        if (($scope['scope'] ?? '') === 'none') {
            return ['rows' => [], 'total' => 0];
        }

        $selectParts = [];
        foreach ($allowedFields as $col) {
            $ident = $this->quoteIdent($col);
            if ($ident !== null) {
                $selectParts[] = $ident;
            }
        }
        if (!$selectParts) {
            throw new RuntimeException('Data source has no fields', 400);
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . " FROM {$ctx['fromSql']} WHERE 1=1";
        $params = [];
        $this->applySalesScope($sql, $params, $scope, $allowedFields);
        $this->applyWidgetSearch($sql, $params, $allowedFields, $search);
        $this->applyWidgetFilters($sql, $params, $allowedFields, $ctx['fieldsByName'], $filters);
        $sortClauses = $this->buildWidgetSortClauses($allowedFields, $sorts);
        if (!$sortClauses) {
            $sortClauses[] = $this->quoteIdent($allowedFields[0]) . ' DESC';
        }
        $sql .= ' ORDER BY ' . implode(', ', $sortClauses);

        $db = Database::getInstance();
        $countSql = preg_replace('/^SELECT .+ FROM /', 'SELECT COUNT(*) as count FROM ', $sql, 1);
        $orderPos = strpos($countSql, 'ORDER BY');
        if ($orderPos !== false) {
            $countSql = substr($countSql, 0, $orderPos);
        }
        $countResult = $db->fetchOne($countSql, $params);
        $total = (int)($countResult['count'] ?? 0);

        $offset = max(0, (int)$offset);
        $limit = max(0, (int)$limit);
        if ($limit <= 0) {
            return ['rows' => [], 'total' => $total];
        }
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        return [
            'rows' => $db->fetchAll($sql, $params),
            'total' => $total
        ];
    }

    private function isSafeId($value) {
        $value = trim((string)$value);
        return $value !== '' && strlen($value) <= 64 && preg_match('/^[A-Za-z0-9_-]+$/', $value);
    }

    private function isSafeJobId($value) {
        $value = trim((string)$value);
        return $value !== '' && strlen($value) <= 80 && preg_match('/^[A-Za-z0-9._-]+$/', $value);
    }

    private function exportFileName($widgetName) {
        $base = preg_replace('/[^\w\-]+/', '_', (string)$widgetName);
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'custom_report';
        }
        if (strlen($base) > 40) {
            $base = substr($base, 0, 40);
        }
        return $base . '_' . date('Y-m-d') . '.csv';
    }

    private function sanitizeSelectedExportRows($raw, array $columns): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $allowed = [];
        foreach ($columns as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = trim((string)($item['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $allowed[$field] = true;
            if (count($allowed) >= 80) {
                break;
            }
        }
        $rows = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = [];
            if ($allowed) {
                foreach ($allowed as $field => $_) {
                    $row[$field] = $this->scalarExportCell($item[$field] ?? null);
                }
            } else {
                foreach ($item as $field => $value) {
                    $field = trim((string)$field);
                    if ($field === '') {
                        continue;
                    }
                    $row[$field] = $this->scalarExportCell($value);
                    if (count($row) >= 80) {
                        break;
                    }
                }
            }
            if ($row) {
                $rows[] = $row;
            }
            if (count($rows) >= 5000) {
                break;
            }
        }
        return $rows;
    }

    private function scalarExportCell($value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = $encoded === false ? '' : $encoded;
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value === null) {
            $value = '';
        } else {
            $value = (string)$value;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 2000);
        }
        return substr($value, 0, 2000);
    }

    private function dispatchSwooleTask(array $payload): void
    {
        $address = config_swoole_address();
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, 1.0);
        if (!$socket) {
            throw new Exception('Failed to connect myswoole: ' . $errstr . ' (' . $errno . ')');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fclose($socket);
            throw new Exception('Failed to encode task payload');
        }

        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new Exception('Failed to send task to myswoole');
        }
    }

    private function exportProgressPayload(?array $progress): array
    {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string)($progress['jobId'] ?? ''),
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
            'fileName' => (string)($progress['fileName'] ?? ''),
        ];
    }

    private function applySalesScope(&$sql, &$params, $scope, array $allowedFields) {
        if (($scope['scope'] ?? '') !== 'own') {
            return;
        }
        $scopeField = null;
        foreach (['userId', 'clientId'] as $candidate) {
            if (in_array($candidate, $allowedFields, true)) {
                $scopeField = $candidate;
                break;
            }
        }
        if ($scopeField === null) {
            return;
        }
        $sql .= ' AND ' . $this->quoteIdent($scopeField) . ' IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)';
        $params['restrict_to_sales_id'] = (int)$scope['restrict_to_sales_id'];
    }

    private function applyWidgetSearch(&$sql, &$params, array $allowedFields, $search) {
        $search = trim((string)$search);
        if ($search === '') {
            return;
        }

        $likeParts = [];
        foreach ($allowedFields as $index => $field) {
            $ident = $this->quoteIdent($field);
            if ($ident === null) {
                continue;
            }
            $key = 'search' . $index;
            $likeParts[] = "CAST({$ident} AS CHAR) LIKE :{$key}";
            $params[$key] = '%' . $search . '%';
        }
        if ($likeParts) {
            $sql .= ' AND (' . implode(' OR ', $likeParts) . ')';
        }
    }

    private function parseFiltersParam($raw = null) {
        if ($raw === null) {
            $raw = $_GET['filters'] ?? '[]';
        }
        if (is_array($raw)) {
            return $raw;
        }
        $filters = json_decode((string)$raw, true);
        return is_array($filters) ? $filters : [];
    }

    private function normalizeFilterValueList($raw) {
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $values = [];
        $seen = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                continue;
            }
            $value = trim((string)$item);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $values[] = function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
            if (count($values) >= 100) {
                break;
            }
        }
        return $values;
    }

    /**
     * @param array|null $filters When null, reads $_GET['filters']
     * @param string|null $excludeField Skip rules for this field (Excel Autofilter distinct values)
     */
    private function applyWidgetFilters(&$sql, &$params, array $allowedFields, array $fieldsByName, $filters = null, $excludeField = null) {
        $filters = $this->parseFiltersParam($filters);
        if (!$filters) {
            return;
        }

        $allowedOps = [
            'contains', 'not_contains', 'equals', 'not_equals',
            'starts_with', 'ends_with', 'is_empty', 'is_not_empty',
            'gt', 'lt', 'gte', 'lte', 'in', 'not_in'
        ];

        $filterIndex = 0;
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $field = $filter['field'] ?? '';
            $op = $filter['op'] ?? 'contains';
            if ($excludeField !== null && $field === $excludeField) {
                continue;
            }
            if (!in_array($field, $allowedFields, true) || !in_array($op, $allowedOps, true)) {
                continue;
            }

            $ident = $this->quoteIdent($field);
            if ($ident === null) {
                continue;
            }
            $key = 'f' . $filterIndex;
            $role = $fieldsByName[$field]['fieldRole'] ?? 'dimension';
            $dataType = $fieldsByName[$field]['dataType'] ?? 'string';

            if ($op === 'is_empty') {
                $sql .= " AND (TRIM(COALESCE(CAST({$ident} AS CHAR), '')) = '')";
                $filterIndex++;
                continue;
            }
            if ($op === 'is_not_empty') {
                $sql .= " AND (TRIM(COALESCE(CAST({$ident} AS CHAR), '')) <> '')";
                $filterIndex++;
                continue;
            }

            if ($op === 'in' || $op === 'not_in') {
                $values = $this->normalizeFilterValueList($filter['value'] ?? []);
                if (!$values) {
                    continue;
                }
                $placeholders = [];
                foreach ($values as $valueIndex => $value) {
                    $valueKey = $key . 'v' . $valueIndex;
                    $placeholders[] = ':' . $valueKey;
                    $params[$valueKey] = $value;
                }
                $connector = $op === 'in' ? 'IN' : 'NOT IN';
                $sql .= " AND TRIM(CAST({$ident} AS CHAR)) {$connector} (" . implode(', ', $placeholders) . ")";
                $filterIndex++;
                continue;
            }

            $value = is_array($filter['value'] ?? null)
                ? ''
                : trim((string)($filter['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            if (in_array($op, ['gt', 'lt', 'gte', 'lte', 'equals', 'not_equals'], true)
                && ($role === 'datetime' || $dataType === 'datetime' || $dataType === 'date')
            ) {
                $map = [
                    'equals' => '=',
                    'not_equals' => '<>',
                    'gt' => '>',
                    'lt' => '<',
                    'gte' => '>=',
                    'lte' => '<='
                ];
                $sql .= " AND DATE({$ident}) {$map[$op]} :{$key}";
                $params[$key] = $value;
                $filterIndex++;
                continue;
            }

            if (in_array($op, ['gt', 'lt', 'gte', 'lte', 'equals', 'not_equals'], true)
                && ($role === 'measure' || in_array($dataType, ['integer', 'decimal', 'number'], true))
            ) {
                if (!is_numeric($value)) {
                    continue;
                }
                $map = [
                    'equals' => '=',
                    'not_equals' => '<>',
                    'gt' => '>',
                    'lt' => '<',
                    'gte' => '>=',
                    'lte' => '<='
                ];
                $sql .= " AND {$ident} {$map[$op]} :{$key}";
                $params[$key] = (float)$value;
                $filterIndex++;
                continue;
            }

            if ($op === 'contains') {
                $sql .= " AND CAST({$ident} AS CHAR) LIKE :{$key}";
                $params[$key] = '%' . $value . '%';
            } elseif ($op === 'not_contains') {
                $sql .= " AND CAST({$ident} AS CHAR) NOT LIKE :{$key}";
                $params[$key] = '%' . $value . '%';
            } elseif ($op === 'equals') {
                $sql .= " AND TRIM(CAST({$ident} AS CHAR)) = :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'not_equals') {
                $sql .= " AND CAST({$ident} AS CHAR) <> :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'starts_with') {
                $sql .= " AND CAST({$ident} AS CHAR) LIKE :{$key}";
                $params[$key] = $value . '%';
            } elseif ($op === 'ends_with') {
                $sql .= " AND CAST({$ident} AS CHAR) LIKE :{$key}";
                $params[$key] = '%' . $value;
            } elseif ($op === 'gt') {
                $sql .= " AND CAST({$ident} AS CHAR) > :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'lt') {
                $sql .= " AND CAST({$ident} AS CHAR) < :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'gte') {
                $sql .= " AND CAST({$ident} AS CHAR) >= :{$key}";
                $params[$key] = $value;
            } elseif ($op === 'lte') {
                $sql .= " AND CAST({$ident} AS CHAR) <= :{$key}";
                $params[$key] = $value;
            }
            $filterIndex++;
        }
    }

    private function buildWidgetSortClauses(array $allowedFields, $sortsRaw = null) {
        $sortClauses = [];
        if ($sortsRaw === null) {
            $sortsRaw = $_GET['sorts'] ?? null;
        }
        $sorts = is_array($sortsRaw) ? $sortsRaw : json_decode((string)$sortsRaw, true);
        if (is_array($sorts)) {
            foreach ($sorts as $sortRule) {
                if (!is_array($sortRule)) {
                    continue;
                }
                $field = $sortRule['field'] ?? '';
                if (!in_array($field, $allowedFields, true)) {
                    continue;
                }
                $direction = strtolower((string)($sortRule['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
                $sortClauses[] = $this->quoteIdent($field) . ' ' . $direction;
            }
        }

        if (!$sortClauses) {
            $sortField = $_GET['sort_field'] ?? '';
            if (in_array($sortField, $allowedFields, true)) {
                $sortDirection = strtolower((string)($_GET['sort_direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
                $sortClauses[] = $this->quoteIdent($sortField) . ' ' . $sortDirection;
            }
        }

        return $sortClauses;
    }

    private function requireSelectableDataSource($db, $dataSourceId) {
        $dataSource = $db->fetchOne(
            "SELECT id, is_detail_only AS isDetailOnly FROM report_data_sources WHERE id = :id",
            ['id' => $dataSourceId]
        );
        if (!$dataSource) {
            Response::error('Data source not found', 404);
            return false;
        }
        if ((int)($dataSource['isDetailOnly'] ?? 0) === 1) {
            Response::error('This data source is only available as a row detail', 400);
            return false;
        }
        return true;
    }

    private function loadDetailPanels($db, $dataSourceId) {
        $dataSourceId = trim((string)$dataSourceId);
        if ($dataSourceId === '') {
            return [];
        }
        $rows = $db->fetchAll(
            "SELECT
                p.id,
                p.title,
                p.parent_field AS parentField,
                p.child_field AS childField,
                p.child_data_source_id AS childDataSourceId,
                ds.object_name AS childObjectName,
                ds.display_name AS childDisplayName
             FROM report_data_source_detail_panels p
             INNER JOIN report_data_sources ds ON ds.id = p.child_data_source_id
             WHERE p.parent_data_source_id = :parent_id
             ORDER BY p.sort_order ASC, p.title ASC",
            ['parent_id' => $dataSourceId]
        );
        foreach ($rows as &$row) {
            $row['fields'] = $this->loadDataSourceFields($db, $row['childDataSourceId'], true);
        }
        unset($row);
        return $rows;
    }

    private function findDetailPanel($db, $parentDataSourceId, $panelId) {
        $panels = $this->loadDetailPanels($db, $parentDataSourceId);
        foreach ($panels as $panel) {
            if ((string)($panel['id'] ?? '') === (string)$panelId) {
                return $panel;
            }
        }
        return null;
    }

    private function loadDataSourceQueryContext($db, $dataSourceId) {
        $dataSourceId = trim((string)$dataSourceId);
        if ($dataSourceId === '') {
            return null;
        }
        $dataSource = $db->fetchOne(
            "SELECT id, object_name AS objectName, schema_name AS schemaName
             FROM report_data_sources
             WHERE id = :id",
            ['id' => $dataSourceId]
        );
        if (!$dataSource) {
            return null;
        }

        $objectName = trim((string)($dataSource['objectName'] ?? ''));
        $quotedObject = $this->quoteIdent($objectName);
        if ($quotedObject === null) {
            return null;
        }

        $fromSql = $quotedObject;
        $schemaName = trim((string)($dataSource['schemaName'] ?? ''));
        if ($schemaName !== '') {
            $quotedSchema = $this->quoteIdent($schemaName);
            $currentDb = $db->fetchOne('SELECT DATABASE() AS dbName');
            $currentDbName = trim((string)($currentDb['dbName'] ?? ''));
            if ($quotedSchema !== null && ($currentDbName === '' || $schemaName === $currentDbName)) {
                $fromSql = $quotedSchema . '.' . $quotedObject;
            }
        }

        $fields = $this->loadDataSourceFields($db, $dataSourceId, true);
        $columnNames = [];
        $fieldsByName = [];
        foreach ($fields as $field) {
            $columnNames[] = $field['columnName'];
            $fieldsByName[$field['columnName']] = $field;
        }

        return [
            'dataSource' => $dataSource,
            'fromSql' => $fromSql,
            'fields' => $fields,
            'columnNames' => $columnNames,
            'fieldsByName' => $fieldsByName
        ];
    }

    private function loadDataSourceFields($db, $dataSourceId, $orderById = false) {
        $dataSourceId = trim((string)$dataSourceId);
        if ($dataSourceId === '') {
            return [];
        }
        $rows = $db->fetchAll(
            "SELECT
                display_name AS displayName,
                column_name AS columnName,
                data_type AS dataType,
                field_role AS fieldRole
             FROM report_data_source_fields
             WHERE data_source_id = :data_source_id
             ORDER BY " . ($orderById ? 'id ASC' : 'display_name ASC'),
            ['data_source_id' => $dataSourceId]
        );
        return array_values(array_filter($rows, function ($row) {
            return $this->quoteIdent($row['columnName'] ?? '') !== null;
        }));
    }

    private function defaultChartView() {
        return [
            'chartType' => '',
            'xField' => '',
            'yField' => '',
            'sortBy' => 'label_asc',
            'ySortBy' => 'label_asc',
            'omitZero' => false,
            'groupBy' => 'none',
            'cumulative' => false,
            'range' => 'auto',
            'rangeMin' => null,
            'rangeMax' => null,
            'colorScheme' => 'auto'
        ];
    }

    private function defaultViewConfig() {
        return [
            'activeView' => '',
            'types' => [],
            'views' => new \stdClass()
        ];
    }

    private function parseViewConfig($raw) {
        $defaults = $this->defaultViewConfig();
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        } else {
            return $defaults;
        }
        if (!is_array($decoded)) {
            return $defaults;
        }
        return $this->sanitizeViewConfig($decoded, null);
    }

    private function sanitizeViewConfig($input, $fields) {
        $defaults = $this->defaultViewConfig();
        if (!is_array($input)) {
            return $defaults;
        }

        $types = array_key_exists('types', $input)
            ? $this->sanitizeWidgetTypes($input['types'])
            : [];
        $viewsIn = isset($input['views']) && is_array($input['views']) ? $input['views'] : [];
        $views = [];
        foreach ($types as $type) {
            $typeId = $type['id'];
            $raw = isset($viewsIn[$typeId]) && is_array($viewsIn[$typeId]) ? $viewsIn[$typeId] : [];
            $views[$typeId] = $type['kind'] === 'chart'
                ? $this->sanitizeChartConfig($raw, $fields)
                : $this->sanitizeTableView($raw, $fields);
        }

        $activeView = trim((string)($input['activeView'] ?? ''));
        $typeIds = array_column($types, 'id');
        if (!$typeIds) {
            $activeView = '';
        } elseif (!in_array($activeView, $typeIds, true)) {
            $activeView = $typeIds[0];
        }

        return [
            'activeView' => $activeView,
            'types' => $types,
            'views' => $views
        ];
    }

    private function sanitizeWidgetTypes($raw) {
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        $seen = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string)($item['id'] ?? ''));
            if ($this->quoteIdent($id) === null || isset($seen[$id])) {
                continue;
            }
            $kind = (string)($item['kind'] ?? '');
            if ($kind !== 'table' && $kind !== 'chart') {
                continue;
            }
            $label = trim((string)($item['label'] ?? $item['name'] ?? ''));
            if ($label === '') {
                continue;
            }
            $label = function_exists('mb_substr') ? mb_substr($label, 0, 255) : substr($label, 0, 255);
            $seen[$id] = true;
            $type = [
                'id' => $id,
                'label' => $label,
                'kind' => $kind
            ];
            $createdBy = trim((string)($item['createdBy'] ?? ''));
            if ($createdBy !== '') {
                $createdBy = function_exists('mb_substr') ? mb_substr($createdBy, 0, 36) : substr($createdBy, 0, 36);
                $type['createdBy'] = $createdBy;
            }
            $createdByName = trim((string)($item['createdByName'] ?? ''));
            if ($createdByName !== '') {
                $createdByName = function_exists('mb_substr') ? mb_substr($createdByName, 0, 255) : substr($createdByName, 0, 255);
                $type['createdByName'] = $createdByName;
            }
            $createdAt = trim((string)($item['createdAt'] ?? ''));
            if ($createdAt !== '') {
                $createdAt = function_exists('mb_substr') ? mb_substr($createdAt, 0, 32) : substr($createdAt, 0, 32);
                $type['createdAt'] = $createdAt;
            }
            $clean[] = $type;
        }
        return $clean;
    }

    private function allowedColumnNames($fields) {
        if (!is_array($fields)) {
            return null;
        }
        $allowed = [];
        foreach ($fields as $field) {
            $name = (string)($field['columnName'] ?? '');
            if ($name !== '' && $this->quoteIdent($name) !== null) {
                $allowed[$name] = true;
            }
        }
        return $allowed;
    }

    private function sanitizeSavedFilters($raw, $fields) {
        if (!is_array($raw)) {
            return [];
        }
        $allowed = $this->allowedColumnNames($fields);
        $ops = [
            'contains', 'not_contains', 'equals', 'not_equals',
            'starts_with', 'ends_with', 'is_empty', 'is_not_empty',
            'gt', 'lt', 'gte', 'lte', 'in', 'not_in'
        ];
        $clean = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = trim((string)($item['field'] ?? ''));
            $op = trim((string)($item['op'] ?? ''));
            if ($this->quoteIdent($field) === null || !in_array($op, $ops, true)) {
                continue;
            }
            if ($allowed !== null && !isset($allowed[$field])) {
                continue;
            }
            if ($op === 'in' || $op === 'not_in') {
                $values = $this->normalizeFilterValueList($item['value'] ?? []);
                if (!$values && $op === 'in') {
                    continue;
                }
                $clean[] = [
                    'field' => $field,
                    'op' => $op,
                    'value' => $values
                ];
            } else {
                $value = is_array($item['value'] ?? null)
                    ? ''
                    : (string)($item['value'] ?? '');
                $value = function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
                $clean[] = [
                    'field' => $field,
                    'op' => $op,
                    'value' => $value
                ];
            }
            if (count($clean) >= 50) {
                break;
            }
        }
        return $clean;
    }

    private function sanitizeSavedSorts($raw, $fields) {
        if (!is_array($raw)) {
            return [];
        }
        $allowed = $this->allowedColumnNames($fields);
        $clean = [];
        $seen = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = trim((string)($item['field'] ?? ''));
            if ($this->quoteIdent($field) === null || isset($seen[$field])) {
                continue;
            }
            if ($allowed !== null && !isset($allowed[$field])) {
                continue;
            }
            $seen[$field] = true;
            $clean[] = [
                'field' => $field,
                'direction' => (($item['direction'] ?? '') === 'asc') ? 'asc' : 'desc'
            ];
            if (count($clean) >= 20) {
                break;
            }
        }
        return $clean;
    }

    private function attachQueryState(array $view, $raw, $fields) {
        $source = is_array($raw) ? $raw : [];
        $view['filters'] = $this->sanitizeSavedFilters($source['filters'] ?? [], $fields);
        $view['sorts'] = $this->sanitizeSavedSorts($source['sorts'] ?? [], $fields);
        $view['sortActive'] = !empty($source['sortActive']) && count($view['sorts']) > 0;
        return $view;
    }

    private function attachPageState(array $view, $raw, $kind) {
        $source = is_array($raw) ? $raw : [];
        $isChart = $kind === 'chart';
        $allowed = $isChart ? [50, 100, 150] : [50, 100, 200];
        $defaultPerPage = $isChart ? 50 : 100;
        $perPage = (int)($source['perPage'] ?? $defaultPerPage);
        if (!in_array($perPage, $allowed, true)) {
            $perPage = $defaultPerPage;
        }
        $view['perPage'] = $perPage;
        $view['page'] = max(1, (int)($source['page'] ?? 1));
        return $view;
    }

    private function sanitizeColumnNameList($raw, $fields) {
        if (!is_array($raw)) {
            return [];
        }
        $allowed = $this->allowedColumnNames($fields);
        $seen = [];
        $names = [];
        foreach ($raw as $name) {
            $name = trim((string)$name);
            if ($name === '' || isset($seen[$name]) || $this->quoteIdent($name) === null) {
                continue;
            }
            if ($allowed !== null && !isset($allowed[$name])) {
                continue;
            }
            $seen[$name] = true;
            $names[] = $name;
        }
        return $names;
    }

    private function sanitizeTableView($view, $fields) {
        $source = is_array($view) ? $view : [];
        $clean = [
            'columnOrder' => $this->sanitizeColumnNameList($source['columnOrder'] ?? [], $fields)
        ];
        if (array_key_exists('visibleColumns', $source) && is_array($source['visibleColumns'])) {
            $visible = $this->sanitizeColumnNameList($source['visibleColumns'], $fields);
            $visible = array_slice($visible, 0, self::MAX_TABLE_VISIBLE_COLUMNS);
            if (!$visible && $clean['columnOrder']) {
                $visible = [ $clean['columnOrder'][0] ];
            }
            $clean['visibleColumns'] = $visible;
        }
        $clean = $this->attachQueryState($clean, $source, $fields);
        return $this->attachPageState($clean, $source, 'table');
    }

    private function sanitizeChartConfig($chart, $fields) {
        $clean = [
            'chartType' => '',
            'xField' => '',
            'yField' => '',
            'sortBy' => 'label_asc',
            'ySortBy' => 'label_asc',
            'omitZero' => false,
            'groupBy' => 'none',
            'cumulative' => false,
            'range' => 'auto',
            'rangeMin' => null,
            'rangeMax' => null,
            'colorScheme' => 'auto'
        ];
        if (!is_array($chart)) {
            $clean = $this->attachQueryState($clean, [], $fields);
            return $this->attachPageState($clean, [], 'chart');
        }

        $type = (string)($chart['chartType'] ?? '');
        if (in_array($type, ['bar_vertical', 'bar_horizontal'], true)) {
            $clean['chartType'] = $type;
        }

        $sortBy = (string)($chart['sortBy'] ?? 'label_asc');
        if (in_array($sortBy, ['manual', 'label_asc', 'label_desc', 'value_asc', 'value_desc'], true)) {
            $clean['sortBy'] = $sortBy;
        }
        $ySortBy = (string)($chart['ySortBy'] ?? 'label_asc');
        if (in_array($ySortBy, ['manual', 'label_asc', 'label_desc', 'value_asc', 'value_desc'], true)) {
            $clean['ySortBy'] = $ySortBy;
        }
        $colorScheme = (string)($chart['colorScheme'] ?? 'auto');
        $allowedColors = [
            'auto', 'colorful', 'colorless', 'blue', 'yellow', 'green',
            'purple', 'teal', 'orange', 'pink', 'red'
        ];
        if (in_array($colorScheme, $allowedColors, true)) {
            $clean['colorScheme'] = $colorScheme;
        }
        $clean['omitZero'] = !empty($chart['omitZero']);
        $clean['cumulative'] = !empty($chart['cumulative']);
        $range = (string)($chart['range'] ?? 'auto');
        $clean['range'] = $range === 'custom' ? 'custom' : 'auto';
        $rangeMin = $this->parseOptionalChartNumber($chart['rangeMin'] ?? null);
        $rangeMax = $this->parseOptionalChartNumber($chart['rangeMax'] ?? null);
        if ($rangeMin !== null) {
            $clean['rangeMin'] = $rangeMin;
        }
        if ($rangeMax !== null) {
            $clean['rangeMax'] = $rangeMax;
        }
        if ($clean['range'] === 'custom' && $rangeMin === null && $rangeMax === null) {
            $clean['range'] = 'auto';
            $clean['rangeMin'] = null;
            $clean['rangeMax'] = null;
        } elseif ($clean['range'] === 'custom'
            && $rangeMin !== null
            && $rangeMax !== null
            && !($rangeMax > $rangeMin)
        ) {
            $clean['range'] = 'auto';
            $clean['rangeMin'] = null;
            $clean['rangeMax'] = null;
        }

        $dimensionCols = [];
        $measureCols = [];
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $name = $field['columnName'] ?? '';
                $role = $field['fieldRole'] ?? '';
                if ($name === '') {
                    continue;
                }
                if ($role === 'measure') {
                    $measureCols[] = $name;
                } else {
                    $dimensionCols[] = $name;
                }
            }
        }

        $xField = (string)($chart['xField'] ?? '');
        $groupBy = (string)($chart['groupBy'] ?? 'none');
        $yField = (string)($chart['yField'] ?? '');

        if ($fields === null) {
            $clean['xField'] = $xField;
            $clean['yField'] = $yField;
            $clean['groupBy'] = $groupBy !== '' ? $groupBy : 'none';
            $clean = $this->attachQueryState($clean, $chart, $fields);
            return $this->attachPageState($clean, $chart, 'chart');
        }

        if (in_array($xField, $dimensionCols, true)) {
            $clean['xField'] = $xField;
        }
        $allCols = array_merge($dimensionCols, $measureCols);
        if ($yField === 'count' || in_array($yField, $allCols, true)) {
            $clean['yField'] = $yField;
        }
        if ($groupBy === 'none' || in_array($groupBy, $dimensionCols, true)) {
            $clean['groupBy'] = $groupBy === $xField ? 'none' : $groupBy;
        }
        $clean = $this->attachQueryState($clean, $chart, $fields);
        return $this->attachPageState($clean, $chart, 'chart');
    }

    private function chartSeriesMax(array $series) {
        $max = 0.0;
        if (count($series) > 1) {
            $labelCount = count($series[0]['values'] ?? []);
            for ($i = 0; $i < $labelCount; $i++) {
                $total = 0.0;
                foreach ($series as $item) {
                    $total += (float)($item['values'][$i] ?? 0);
                }
                if ($total > $max) {
                    $max = $total;
                }
            }
            return $max;
        }
        foreach ($series as $item) {
            foreach ($item['values'] as $value) {
                if ($value > $max) {
                    $max = $value;
                }
            }
        }
        return $max;
    }

    private function parseOptionalChartNumber($value) {
        if ($value === null || $value === '') {
            return null;
        }
        $text = strtolower(trim((string)$value));
        if ($text === '' || $text === 'none' || $text === 'null') {
            return null;
        }
        return is_numeric($value) ? (float)$value : null;
    }

    private function isNumericChartField(array $field) {
        $role = (string)($field['fieldRole'] ?? '');
        $dataType = strtolower((string)($field['dataType'] ?? ''));
        return $role === 'measure' || in_array($dataType, ['integer', 'decimal', 'number'], true);
    }

    private function quoteIdent($name) {
        $name = trim((string)$name);
        if ($name === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            return null;
        }
        return '`' . str_replace('`', '', $name) . '`';
    }

    /**
     * Build unique copy name: "Name (Copy)", then "Name (Copy 2)", "Name (Copy 3)", ...
     * Strips an existing trailing " (Copy)" / " (Copy N)" from the source name first.
     */
    private function nextUniqueWidgetCopyName($db, $reportId, $sourceName) {
        $stem = preg_replace('/\s+\(Copy(?:\s+\d+)?\)$/i', '', trim((string)$sourceName));
        if ($stem === '') {
            $stem = 'Widget';
        }

        $existingRows = $db->fetchAll(
            "SELECT name FROM report_widgets WHERE report_id = :report_id",
            ['report_id' => $reportId]
        );
        $existing = [];
        foreach ($existingRows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                $existing[$name] = true;
            }
        }

        $candidate = $stem . ' (Copy)';
        if (!isset($existing[$candidate])) {
            return $candidate;
        }

        $n = 2;
        while ($n < 10000) {
            $candidate = $stem . ' (Copy ' . $n . ')';
            if (!isset($existing[$candidate])) {
                return $candidate;
            }
            $n++;
        }

        return $stem . ' (Copy ' . time() . ')';
    }

    private function generateUuid() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return [
            'userId' => $userId
        ];
    }
}
