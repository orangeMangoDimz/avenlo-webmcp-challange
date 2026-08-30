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
        $where = ['l.modelKey = :modelKey'];
        $params = ['modelKey' => trim((string) ($filters['modelKey'] ?? ''))];

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
