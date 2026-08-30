<?php
/**
 * IB Invitation 模型
 * 对应表：ibInvitations
 */

require_once __DIR__ . '/BaseModel.php';

class IbInvitation extends BaseModel {
    protected $table = 'ibInvitations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'invitationCode', 'clientId', 'invitedBy', 'invitationMessage',
        'selectedDocuments', 'invitationStatus', 'sentAt', 'viewedAt',
        'respondedAt', 'expiresAt', 'createdIbPartnerId'
    ];

    /**
     * 创建邀请（自动生成邀请码）
     */
    public function createInvitation($clientId, $invitedBy, $message = null, $documentIds = []) {
        // 生成唯一邀请码
        $invitationCode = $this->generateInvitationCode();

        // 设置过期时间：当前日期的月份加 1（按自然月，如 1 月 15 日 -> 2 月 15 日）
        $expiresAt = (new \DateTime())->modify('+1 month')->format('Y-m-d H:i:s');

        $data = [
            'invitationCode' => $invitationCode,
            'clientId' => $clientId,
            'invitedBy' => $invitedBy,
            'invitationMessage' => $message,
            'selectedDocuments' => json_encode($documentIds),
            'invitationStatus' => 'sent',
            'sentAt' => date('Y-m-d H:i:s'),
            'expiresAt' => $expiresAt
        ];

        $id = $this->create($data);

        return [
            'id' => $id,
            'invitationCode' => $invitationCode,
            'expiresAt' => $expiresAt
        ];
    }

    /**
     * 生成唯一邀请码
     */
    private function generateInvitationCode() {
        do {
            $code = 'IB-INV-' . strtoupper(bin2hex(random_bytes(6)));
            $exists = $this->findOne(['invitationCode' => $code]);
        } while ($exists);

        return $code;
    }

    /**
     * 根据邀请码获取邀请
     */
    public function getByCode($invitationCode) {
        $sql = "SELECT
                    inv.*,
                    cu.firstName as clientFirstName,
                    cu.lastName as clientLastName,
                    cu.email as clientEmail,
                    au.fullName as invitedByName
                FROM ibInvitations inv
                INNER JOIN clientUsers cu ON inv.clientId = cu.id
                LEFT JOIN adminUsers au ON inv.invitedBy = au.id
                WHERE inv.invitationCode = :code
                LIMIT 1";

        $invitation = $this->db->fetchOne($sql, ['code' => $invitationCode]);

        if ($invitation && $invitation['selectedDocuments']) {
            $invitation['selectedDocuments'] = json_decode($invitation['selectedDocuments'], true);
        }

        return $invitation;
    }

    /**
     * 验证邀请码是否有效
     */
    public function validateInvitation($invitationCode) {
        $invitation = $this->getByCode($invitationCode);

        if (!$invitation) {
            return ['valid' => false, 'reason' => 'Invitation not found'];
        }

        if ($invitation['invitationStatus'] === 'expired' || strtotime($invitation['expiresAt']) < time()) {
            return ['valid' => false, 'reason' => 'Invitation expired'];
        }

        if ($invitation['invitationStatus'] === 'accepted') {
            return ['valid' => false, 'reason' => 'Invitation already accepted'];
        }

        if ($invitation['invitationStatus'] === 'declined') {
            return ['valid' => false, 'reason' => 'Invitation was declined'];
        }

        return ['valid' => true, 'invitation' => $invitation];
    }

    /**
     * 标记邀请为已查看
     */
    public function markAsViewed($invitationId) {
        // 先检查邀请状态是否为 sent
        $invitation = $this->findById($invitationId);
        if (!$invitation || $invitation['invitationStatus'] !== 'sent') {
            return false;
        }

        return $this->update($invitationId, [
            'invitationStatus' => 'viewed',
            'viewedAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 接受邀请
     */
    public function acceptInvitation($invitationId, $createdIbPartnerId) {
        $data = [
            'invitationStatus' => 'accepted',
            'respondedAt' => date('Y-m-d H:i:s'),
            'createdIbPartnerId' => $createdIbPartnerId
        ];

        return $this->update($invitationId, $data);
    }

    /**
     * 拒绝邀请
     */
    public function declineInvitation($invitationId) {
        $data = [
            'invitationStatus' => 'declined',
            'respondedAt' => date('Y-m-d H:i:s')
        ];

        return $this->update($invitationId, $data);
    }

    /**
     * 标记过期的邀请
     */
    public function markExpiredInvitations() {
        $sql = "UPDATE {$this->table}
                SET invitationStatus = 'expired'
                WHERE invitationStatus = 'sent'
                  AND expiresAt < NOW()";

        return $this->db->execute($sql);
    }
}
