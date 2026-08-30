<?php
/**
 * KYC Timeline Model
 * 对应表: kycTimeline
 */

require_once __DIR__ . '/BaseModel.php';

class KycTimeline extends BaseModel {
    protected $table = 'kycTimeline';
    protected $primaryKey = 'id';

    protected $fillable = [
        'submissionId',
        'clientId',
        'eventType',
        'eventTitle',
        'eventDescription',
        'eventStatus',
        'eventData',
        'createdBy'
    ];

    /**
     * 添加时间线事件
     */
    public function addEvent($submissionId, $clientId, $eventType, $eventTitle, $eventDescription, $eventData = null, $createdBy = null, $eventStatus = 'completed') {
        return $this->create([
            'submissionId' => $submissionId,
            'clientId' => $clientId,
            'eventType' => $eventType,
            'eventTitle' => $eventTitle,
            'eventDescription' => $eventDescription,
            'eventStatus' => $eventStatus,
            'eventData' => $eventData ? json_encode($eventData) : null,
            'createdBy' => $createdBy
        ]);
    }

    /**
     * 获取提交的时间线
     */
    public function getSubmissionTimeline($submissionId) {
        return $this->findAll(
            ['submissionId' => $submissionId],
            'createdAt ASC'
        );
    }

    /**
     * 获取客户的时间线
     */
    public function getClientTimeline($clientId) {
        return $this->findAll(
            ['clientId' => $clientId],
            'createdAt DESC'
        );
    }

    /**
     * 生成时间线：仅基于实际发生的事件记录，不使用模板
     */
    public function generateTimelineForStatus($submissionId, $clientId, $statusType) {
        // 获取实际的时间线记录（按时间升序）
        $actualEvents = $this->getSubmissionTimeline($submissionId);

        $timeline = [];
        foreach ($actualEvents as $event) {
            $timeline[] = [
                'id' => $event['id'],
                'title' => $event['eventTitle'],
                'description' => $event['eventDescription'],
                'date' => $this->formatDate($event['createdAt']),
                'completed' => $event['eventStatus'] === 'completed',
                'current' => $event['eventStatus'] === 'current',
                'eventType' => $event['eventType'],
                'eventStatus' => $event['eventStatus']
            ];
        }

        return $timeline;
    }

    /**
     * 判断是否为当前事件
     */
    private function isCurrentEvent($eventType, $statusType, $completedEventTypes) {
        // 根据状态和已完成的事件类型判断当前事件
        $statusEventMapping = [
            'incomplete' => 'financial_info_submitted',
            'under_review' => 'under_review',
            'pending' => 'under_review',
            'pending_documents' => 'documents_uploaded'
        ];

        return isset($statusEventMapping[$statusType]) &&
               $statusEventMapping[$statusType] === $eventType &&
               !in_array($eventType, $completedEventTypes);
    }

    /**
     * 格式化日期
     */
    private function formatDate($dateString) {
        if (!$dateString) return 'N/A';

        $date = new DateTime($dateString);
        return $date->format('F j, Y - g:i A');
    }

    /**
     * 更新当前事件状态
     */
    public function updateCurrentEvent($submissionId, $eventType, $eventTitle, $eventDescription, $eventStatus = 'completed') {
        // 查找eventStatus为current的记录
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId AND eventStatus = 'current'
                LIMIT 1";
        $currentEvent = $this->queryOne($sql, ['submissionId' => $submissionId]);

        if ($currentEvent) {
            // 更新当前事件
            return $this->update($currentEvent['id'], [
                'eventType' => $eventType,
                'eventTitle' => $eventTitle,
                'eventDescription' => $eventDescription,
                'eventStatus' => $eventStatus
            ]);
        }

        return false;
    }

    /**
     * 更新当前事件状态为rejected
     */
    public function updateCurrentEventToRejected($submissionId, $rejectionReason, $adminId = null) {
        // 查找eventStatus为current的记录
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId AND eventStatus = 'current'
                LIMIT 1";
        $currentEvent = $this->queryOne($sql, ['submissionId' => $submissionId]);

        if ($currentEvent) {
            // 准备事件数据
            $eventData = [
                'rejectedBy' => $adminId,
                'rejectionReason' => $rejectionReason
            ];

            // 更新当前事件为rejected
            return $this->update($currentEvent['id'], [
                'eventType' => 'rejected',
                'eventTitle' => 'Rejected',
                'eventDescription' => $rejectionReason,
                'eventStatus' => 'completed',
                'eventData' => json_encode($eventData),
                'createdBy' => $adminId
            ]);
        }

        return false;
    }

    /**
     * 更新当前事件状态为approved
     */
    public function updateCurrentEventToApproved($submissionId, $adminId = null, $notes = null) {
        // 查找eventStatus为current的记录
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId AND eventStatus = 'current'
                LIMIT 1";
        $currentEvent = $this->queryOne($sql, ['submissionId' => $submissionId]);

        if ($currentEvent) {
            // 准备事件数据
            $eventData = [
                'approvedBy' => $adminId,
                'notes' => $notes
            ];

            // 更新当前事件为approved
            return $this->update($currentEvent['id'], [
                'eventType' => 'approved',
                'eventTitle' => 'Approved',
                'eventDescription' => 'Your KYC application has been approved' . ($notes ? ": {$notes}" : ''),
                'eventStatus' => 'completed',
                'eventData' => json_encode($eventData),
                'createdBy' => $adminId
            ]);
        }

        return false;
    }

    /**
     * 删除pending状态的事件
     */
    public function deletePendingEvents($submissionId) {
        // 查找所有pending状态的事件
        $pendingEvents = $this->findAll([
            'submissionId' => $submissionId,
            'eventStatus' => 'pending'
        ]);

        // 删除每个pending事件
        foreach ($pendingEvents as $event) {
            $this->delete($event['id']);
        }

        return count($pendingEvents);
    }

    /**
     * 更新当前事件状态为resubmit_required
     * 如果有current事件则更新，否则创建新事件
     */
    public function updateCurrentEventToResubmitRequired($submissionId, $clientId, $eventTitle, $eventDescription, $eventData = null, $adminId = null) {
        // 查找eventStatus为current的记录
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId AND eventStatus = 'current'
                LIMIT 1";
        $currentEvent = $this->queryOne($sql, ['submissionId' => $submissionId]);

        if ($currentEvent) {
            // 更新当前事件为resubmit_required
            return $this->update($currentEvent['id'], [
                'eventType' => 'resubmit_required',
                'eventTitle' => $eventTitle,
                'eventDescription' => $eventDescription,
                'eventStatus' => 'current',
                'eventData' => $eventData ? json_encode($eventData) : null,
                'createdBy' => $adminId
            ]);
        } else {
            // 如果没有current事件，创建新事件
            return $this->addEvent(
                $submissionId,
                $clientId,
                'resubmit_required',
                $eventTitle,
                $eventDescription,
                $eventData,
                $adminId,
                'completed'
            );
        }
    }

    /**
     * 更新当前事件状态为pending（用户重新提交后）
     * 如果有current事件则更新，否则创建新事件
     */
    public function updateCurrentEventToPending($submissionId, $clientId, $eventTitle, $eventDescription, $eventData = null) {
        // 查找eventStatus为current的记录
        $sql = "SELECT * FROM {$this->table}
                WHERE submissionId = :submissionId AND eventStatus = 'current'
                LIMIT 1";
        $currentEvent = $this->queryOne($sql, ['submissionId' => $submissionId]);

        if ($currentEvent) {
            // 更新当前事件
            return $this->update($currentEvent['id'], [
                'eventType' => 'application_submitted',
                'eventTitle' => $eventTitle,
                'eventDescription' => $eventDescription,
                'eventStatus' => 'completed',
                'eventData' => $eventData ? json_encode($eventData) : null
            ]);
        } else {
            // 如果没有current事件，创建新事件
            return $this->addEvent(
                $submissionId,
                $clientId,
                'application_submitted',
                $eventTitle,
                $eventDescription,
                $eventData,
                null,
                'completed'
            );
        }
    }
}

/**
 * KYC Timeline Template Model
 * 对应表: kycTimelineTemplates
 */
class KycTimelineTemplate extends BaseModel {
    protected $table = 'kycTimelineTemplates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'statusType',
        'eventType',
        'eventTitle',
        'eventDescription',
        'displayOrder',
        'isRequired',
        'isActive'
    ];

    /**
     * 根据状态获取时间线模板
     */
    public function getTemplatesByStatus($statusType) {
        return $this->findAll([
            'statusType' => $statusType,
            'isActive' => 1
        ], 'displayOrder ASC');
    }

    /**
     * 获取所有活跃的模板
     */
    public function getActiveTemplates() {
        return $this->findAll(['isActive' => 1], 'statusType ASC, displayOrder ASC');
    }
}
