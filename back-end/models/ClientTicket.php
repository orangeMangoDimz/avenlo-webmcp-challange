<?php
/**
 * 客户端工单模型
 */

require_once __DIR__ . '/BaseModel.php';

class ClientTicket extends BaseModel {
    protected $table = 'clientTickets';
    protected $primaryKey = 'id';

    protected $fillable = [
        'clientId',
        'title',
        'content',
        'status',
        'priority',
        'createdAt',
        'updatedAt'
    ];

    /**
     * 获取工单列表（分页）
     */
    public function getTickets($page = 1, $perPage = 10, $filters = []) {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['restrict_to_sales_id']) && (int)$filters['restrict_to_sales_id'] > 0) {
            $conditions[] = "ct.clientId IN (SELECT clientId FROM sales_bind WHERE salesId = :restrict_to_sales_id)";
            $params['restrict_to_sales_id'] = (int)$filters['restrict_to_sales_id'];
        }

        if (!empty($filters['startDate'])) {
            $conditions[] = "DATE(ct.createdAt) >= :startDate";
            $params['startDate'] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $conditions[] = "DATE(ct.createdAt) <= :endDate";
            $params['endDate'] = $filters['endDate'];
        }

        if (!empty($filters['clientId'])) {
            $conditions[] = "ct.clientId = :clientId";
            $params['clientId'] = $filters['clientId'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT SQL_CALC_FOUND_ROWS
                    ct.*,
                    cu.firstName,
                    cu.lastName,
                    cu.email
                FROM {$this->table} ct
                INNER JOIN clientUsers cu ON ct.clientId = cu.id
                {$whereClause}
                ORDER BY ct.createdAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->db->fetchAll($sql, $params);

        $totalResult = $this->db->fetchOne("SELECT FOUND_ROWS() as total");
        $total = (int)($totalResult['total'] ?? 0);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 获取单个工单详情
     */
    public function getTicketDetails($id) {
        $sql = "SELECT
                    ct.*,
                    cu.firstName,
                    cu.lastName,
                    cu.email,
                    cu.phone
                FROM {$this->table} ct
                INNER JOIN clientUsers cu ON ct.clientId = cu.id
                WHERE ct.id = :id";

        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
