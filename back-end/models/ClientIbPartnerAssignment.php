<?php
/**
 * Client IB Partner Assignment 模型
 * 对应表：clientIbPartnerAssignments
 */

require_once __DIR__ . '/BaseModel.php';

class ClientIbPartnerAssignment extends BaseModel {
    protected $table = 'clientIbPartnerAssignments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'clientId', 'ibPartnerId', 'referralCode', 'assignedAt', 'isActive'
    ];

    /**
     * 获取直接客户数（来自 ib_partner_bind，IB 绑普通用户仅存该表）
     */
    public function getDirectClientsCount($ibPartnerId, $beforeDate = null) {
        $params = ['ibPartnerId' => $ibPartnerId];
        $whereClause = "WHERE parentId = :ibPartnerId AND isClient = 1";
        if ($beforeDate) {
            $whereClause .= " AND (b.createdAt <= :beforeDate OR b.createdAt IS NULL)";
            $params['beforeDate'] = $beforeDate;
        }
        $sql = "SELECT COUNT(*) as count FROM ib_partner_bind b " . $whereClause;
        $result = $this->db->fetchOne($sql, $params);
        return (int)($result['count'] ?? 0);
    }

    /**
     * 获取直接客户列表（来自 ib_partner_bind）
     */
    public function getDirectClients($ibPartnerId) {
        $sql = "SELECT
                    c.id,
                    c.firstName,
                    c.lastName,
                    c.email,
                    CONCAT(c.firstName, ' ', c.lastName) as fullName
                FROM ib_partner_bind b
                INNER JOIN clientUsers c ON b.childClientId = c.id
                WHERE b.parentId = :ibPartnerId AND b.isClient = 1
                ORDER BY c.email ASC";
        return $this->db->fetchAll($sql, ['ibPartnerId' => $ibPartnerId]);
    }
}
