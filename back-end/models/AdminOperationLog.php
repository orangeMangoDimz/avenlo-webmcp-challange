<?php
/**
 * 后台操作日志 adminOperationLogs
 */

require_once __DIR__ . '/BaseModel.php';

class AdminOperationLog extends BaseModel {
    protected $table = 'adminOperationLogs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'operatorId',
        'modelKey',
        'moduleNameZh',
        'moduleNameEn',
        'subModuleKey',
        'subModuleNameZh',
        'subModuleNameEn',
        'operationTypeKey',
        'targetId',
        'detailZh',
        'detailEn',
        'ipAddress',
        'operatedAt',
        'createdAt',
    ];

    public const MAX_EXPORT = 5000;

    /**
     * @param array $filters modelKey, startDate, endDate, keyword, subModule, operationType
     * @return array{where:string,params:array,binds:array}
     */
    public function buildListWhere(array $filters) {
        $where = [];
        $params = [];

        $modelKey = trim((string) ($filters['modelKey'] ?? ''));
        if ($modelKey !== '' && $modelKey !== 'all') {
            $where[] = 'l.modelKey = :modelKey';
            $params['modelKey'] = $modelKey;
        } elseif ($modelKey === 'all' && !empty($filters['modelKeys']) && is_array($filters['modelKeys'])) {
            $modelConditions = [];
            foreach (array_values($filters['modelKeys']) as $index => $visibleModelKey) {
                $visibleModelKey = trim((string)$visibleModelKey);
                if ($visibleModelKey === '') {
                    continue;
                }
                $param = 'visibleModelKey' . $index;
                $modelConditions[] = "l.modelKey = :{$param}";
                $params[$param] = $visibleModelKey;
            }
            if ($modelConditions) {
                $where[] = '(' . implode(' OR ', $modelConditions) . ')';
            }
        }

        $logId = (int)($filters['logId'] ?? 0);
        if ($logId > 0) {
            $where[] = 'l.id = :logId';
            $params['logId'] = $logId;
        }

        $operatorId = (int)($filters['operatorId'] ?? 0);
        if ($operatorId > 0) {
            $where[] = 'l.operatorId = :operatorId';
            $params['operatorId'] = $operatorId;
        }

        $targetId = (int)($filters['targetId'] ?? 0);
        if ($targetId > 0) {
            $where[] = 'l.targetId = :targetId';
            $params['targetId'] = $targetId;
        }

        $startDate = trim((string) ($filters['startDate'] ?? ''));
        if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $where[] = 'l.operatedAt >= :startAt';
            $params['startAt'] = $startDate . ' 00:00:00';
        }

        $endDate = trim((string) ($filters['endDate'] ?? ''));
        if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $where[] = 'l.operatedAt <= :endAt';
            $params['endAt'] = $endDate . ' 23:59:59';
        }

        $subModule = trim((string) ($filters['subModule'] ?? ''));
        if ($subModule !== '' && $subModule !== 'all') {
            $where[] = 'l.subModuleKey = :subModuleKey';
            $params['subModuleKey'] = $subModule;
        }

        $operationType = trim((string) ($filters['operationType'] ?? ''));
        if ($operationType !== '' && $operationType !== 'all') {
            $where[] = 'l.operationTypeKey = :operationTypeKey';
            $params['operationTypeKey'] = $operationType;
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $where[] = '(CAST(l.operatorId AS CHAR) LIKE :kw OR au.fullName LIKE :kw)';
            $params['kw'] = '%' . $keyword . '%';
        }

        $query = trim((string)($filters['query'] ?? ''));
        if ($query !== '') {
            $where[] = '(CAST(l.id AS CHAR) LIKE :auditQuery
                OR CAST(l.operatorId AS CHAR) LIKE :auditQuery
                OR CAST(l.targetId AS CHAR) LIKE :auditQuery
                OR au.fullName LIKE :auditQuery
                OR l.moduleNameEn LIKE :auditQuery
                OR l.moduleNameZh LIKE :auditQuery
                OR l.subModuleNameEn LIKE :auditQuery
                OR l.subModuleNameZh LIKE :auditQuery
                OR l.detailEn LIKE :auditQuery
                OR l.detailZh LIKE :auditQuery)';
            $params['auditQuery'] = '%' . $query . '%';
        }

        $targetScopes = is_array($filters['targetScopes'] ?? null)
            ? $filters['targetScopes']
            : [];
        if ($targetScopes) {
            $scopeConditions = [];
            foreach (array_values($targetScopes) as $index => $scope) {
                $scopeModelKey = trim((string)($scope['modelKey'] ?? ''));
                $scopeSubModuleKey = trim((string)($scope['subModuleKey'] ?? ''));
                if ($scopeModelKey === '' || $scopeSubModuleKey === '') {
                    continue;
                }
                $modelParam = 'targetScopeModel' . $index;
                $subModuleParam = 'targetScopeSubModule' . $index;
                $scopeConditions[] = "(l.modelKey = :{$modelParam} AND l.subModuleKey = :{$subModuleParam})";
                $params[$modelParam] = $scopeModelKey;
                $params[$subModuleParam] = $scopeSubModuleKey;
            }
            if ($scopeConditions) {
                $where[] = '(' . implode(' OR ', $scopeConditions) . ')';
            }
        }

        if (!$where) {
            $where[] = '1 = 1';
        }

        return [
            'where' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    public function countByFilters(array $filters) {
        $built = $this->buildListWhere($filters);
        $sql = "SELECT COUNT(*) AS cnt
                FROM {$this->table} l
                LEFT JOIN adminUsers au ON l.operatorId = au.id
                WHERE {$built['where']}";
        $row = $this->db->fetchOne($sql, $built['params']);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findByFilters(array $filters, $page = 1, $perPage = 10, $limitCap = null) {
        $built = $this->buildListWhere($filters);
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $limitSql = '';
        $params = $built['params'];
        if ($limitCap !== null) {
            $limitSql = ' LIMIT ' . (int) $limitCap;
        } else {
            $limitSql = ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        }

        $sql = "SELECT l.*,
                       COALESCE(au.fullName, '') AS operatorFullName,
                       CASE
                           WHEN l.subModuleKey = 'kyc_templates' THEN COALESCE(kt.templateName, '')
                           WHEN l.subModuleKey = 'role_management' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tar.roleDisplayName, '')), ''),
                               NULLIF(TRIM(COALESCE(tar.roleName, '')), ''),
                               ''
                           )
                           WHEN l.subModuleKey = 'accounts' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tau.fullName, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.username, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.email, '')), ''),
                               ''
                           )
                           WHEN l.modelKey = 'log_sales' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tau.fullName, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.username, '')), ''),
                               ''
                           )
                           ELSE COALESCE(
                               NULLIF(TRIM(CONCAT(COALESCE(cu.firstName, ''), ' ', COALESCE(cu.lastName, ''))), ''),
                               NULLIF(TRIM(COALESCE(cu.email, '')), ''),
                               ''
                           )
                       END AS targetDisplayNameZh,
                       CASE
                           WHEN l.subModuleKey = 'kyc_templates' THEN COALESCE(kt.templateName, '')
                           WHEN l.subModuleKey = 'role_management' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tar.roleName, '')), ''),
                               NULLIF(TRIM(COALESCE(tar.roleDisplayName, '')), ''),
                               ''
                           )
                           WHEN l.subModuleKey = 'accounts' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tau.username, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.email, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.fullName, '')), ''),
                               ''
                           )
                           WHEN l.modelKey = 'log_sales' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tau.fullName, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.username, '')), ''),
                               ''
                           )
                           ELSE COALESCE(
                               NULLIF(TRIM(CONCAT(COALESCE(cu.firstName, ''), ' ', COALESCE(cu.lastName, ''))), ''),
                               NULLIF(TRIM(COALESCE(cu.email, '')), ''),
                               ''
                           )
                       END AS targetDisplayNameEn,
                       CASE
                           WHEN l.subModuleKey = 'kyc_templates' THEN COALESCE(kt.templateName, '')
                           WHEN l.subModuleKey = 'role_management' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tar.roleDisplayName, '')), ''),
                               NULLIF(TRIM(COALESCE(tar.roleName, '')), ''),
                               ''
                           )
                           WHEN l.subModuleKey = 'accounts' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tau.fullName, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.username, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.email, '')), ''),
                               ''
                           )
                           WHEN l.modelKey = 'log_sales' THEN COALESCE(
                               NULLIF(TRIM(COALESCE(tau.fullName, '')), ''),
                               NULLIF(TRIM(COALESCE(tau.username, '')), ''),
                               ''
                           )
                           ELSE COALESCE(
                               NULLIF(TRIM(CONCAT(COALESCE(cu.firstName, ''), ' ', COALESCE(cu.lastName, ''))), ''),
                               NULLIF(TRIM(COALESCE(cu.email, '')), ''),
                               ''
                           )
                       END AS targetDisplayName
                FROM {$this->table} l
                LEFT JOIN adminUsers au ON l.operatorId = au.id
                LEFT JOIN adminUsers tau ON l.targetId = tau.id
                    AND (l.modelKey = 'log_sales' OR l.subModuleKey = 'accounts')
                LEFT JOIN adminRoles tar ON l.targetId = tar.id AND l.subModuleKey = 'role_management'
                LEFT JOIN clientUsers cu ON l.targetId = cu.id
                    AND l.subModuleKey NOT IN ('kyc_templates', 'kyc_settings', 'pm_products', 'pm_categories', 'accounts', 'role_management', 'login_page_settings', 'log_settings', 'email_settings', 'email_templates', 'platform_settings')
                    AND l.modelKey <> 'log_sales'
                LEFT JOIN kycTemplates kt ON l.targetId = kt.id AND l.subModuleKey = 'kyc_templates'
                WHERE {$built['where']}
                ORDER BY l.operatedAt DESC, l.id DESC
                {$limitSql}";

        return $this->db->fetchAll($sql, $params);
    }
}
