<?php
/**
 * Application-level ERROR / WARNING / INFO logs (applicationLogs).
 */

require_once __DIR__ . '/BaseModel.php';

class ApplicationLog extends BaseModel {
    protected $table = 'applicationLogs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'level',
        'service',
        'environment',
        'message',
        'context',
        'exceptionClass',
        'stackTrace',
        'requestId',
        'correlationId',
        'userId',
        'userType',
        'jobId',
        'source',
        'requestMethod',
        'requestPath',
        'route',
        'actualHttpStatus',
        'applicationStatusCode',
        'errorCode',
        'failureType',
        'durationMs',
        'createdAt',
    ];

    /**
     * @param array $filters level, service, environment, requestId, startDate, endDate
     * @return array{where:string,params:array}
     */
    public function buildListWhere(array $filters) {
        $where = ['1=1'];
        $params = [];

        $level = strtoupper(trim((string) ($filters['level'] ?? '')));
        if ($level !== '' && in_array($level, ['ERROR', 'WARNING', 'INFO'], true)) {
            $where[] = 'level = :level';
            $params['level'] = $level;
        }

        $service = trim((string) ($filters['service'] ?? ''));
        if ($service !== '') {
            $where[] = 'service = :service';
            $params['service'] = $service;
        }

        $environment = trim((string) ($filters['environment'] ?? ''));
        if ($environment !== '') {
            $where[] = 'environment = :environment';
            $params['environment'] = $environment;
        }

        $requestId = trim((string) ($filters['requestId'] ?? ''));
        if ($requestId !== '') {
            $where[] = 'requestId = :requestId';
            $params['requestId'] = $requestId;
        }

        $startDate = trim((string) ($filters['startDate'] ?? ''));
        if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $where[] = 'createdAt >= :startAt';
            $params['startAt'] = $startDate . ' 00:00:00';
        }

        $endDate = trim((string) ($filters['endDate'] ?? ''));
        if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $where[] = 'createdAt <= :endAt';
            $params['endAt'] = $endDate . ' 23:59:59';
        }

        return [
            'where' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    public function countByFilters(array $filters) {
        $built = $this->buildListWhere($filters);
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE {$built['where']}";
        $row = $this->db->fetchOne($sql, $built['params']);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @return array{items:array,total:int,page:int,per_page:int,total_pages:int}
     */
    public function findByFilters(array $filters, $page = 1, $perPage = 10) {
        $built = $this->buildListWhere($filters);
        $page = max(1, (int) $page);
        $perPage = max(1, min(100, (int) $perPage));
        $offset = ($page - 1) * $perPage;
        $total = $this->countByFilters($filters);

        $sql = "SELECT * FROM {$this->table}
                WHERE {$built['where']}
                ORDER BY createdAt DESC, id DESC
                LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

        $rows = $this->db->fetchAll($sql, $built['params']) ?: [];
        $items = array_map([$this, 'normalizeRow'], $rows);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRow(array $row) {
        if (isset($row['context']) && is_string($row['context']) && $row['context'] !== '') {
            $decoded = json_decode($row['context'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['context'] = $decoded;
            }
        }
        if (isset($row['userId']) && $row['userId'] !== null) {
            $row['userId'] = (int) $row['userId'];
        }
        return $row;
    }
}
