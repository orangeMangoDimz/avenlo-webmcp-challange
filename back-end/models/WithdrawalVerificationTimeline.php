<?php
/**
 * Withdrawal Verification Timeline Model
 * 对应表: withdrawalVerificationTimeline
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalVerificationTimeline extends BaseModel {
    protected $table = 'withdrawalVerificationTimeline';
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

    public function getSubmissionTimeline($submissionId) {
        return $this->findAll(['submissionId' => $submissionId], 'createdAt ASC');
    }

    public function getClientTimeline($clientId) {
        return $this->findAll(['clientId' => $clientId], 'createdAt DESC');
    }

    public function updateCurrentEventToRejected($submissionId, $rejectionReason, $adminId = null) {
        $currentEvent = $this->queryOne(
            "SELECT * FROM {$this->table} WHERE submissionId = :submissionId AND eventStatus = 'current' LIMIT 1",
            ['submissionId' => $submissionId]
        );

        if (!$currentEvent) {
            return false;
        }

        return $this->update($currentEvent['id'], [
            'eventType' => 'rejected',
            'eventTitle' => 'Rejected',
            'eventDescription' => $rejectionReason,
            'eventStatus' => 'completed',
            'eventData' => json_encode([
                'rejectedBy' => $adminId,
                'rejectionReason' => $rejectionReason
            ]),
            'createdBy' => $adminId
        ]);
    }

    public function updateCurrentEventToApproved($submissionId, $adminId = null, $notes = null) {
        $currentEvent = $this->queryOne(
            "SELECT * FROM {$this->table} WHERE submissionId = :submissionId AND eventStatus = 'current' LIMIT 1",
            ['submissionId' => $submissionId]
        );

        if (!$currentEvent) {
            return false;
        }

        return $this->update($currentEvent['id'], [
            'eventType' => 'approved',
            'eventTitle' => 'Approved',
            'eventDescription' => 'Your verification has been approved' . ($notes ? ": {$notes}" : ''),
            'eventStatus' => 'completed',
            'eventData' => json_encode([
                'approvedBy' => $adminId,
                'notes' => $notes
            ]),
            'createdBy' => $adminId
        ]);
    }

    public function deletePendingEvents($submissionId) {
        $pendingEvents = $this->findAll([
            'submissionId' => $submissionId,
            'eventStatus' => 'pending'
        ]);

        foreach ($pendingEvents as $event) {
            $this->delete($event['id']);
        }

        return count($pendingEvents);
    }

    public function updateCurrentEventToResubmitRequired($submissionId, $clientId, $eventTitle, $eventDescription, $eventData = null, $adminId = null) {
        $currentEvent = $this->queryOne(
            "SELECT * FROM {$this->table} WHERE submissionId = :submissionId AND eventStatus = 'current' LIMIT 1",
            ['submissionId' => $submissionId]
        );

        if ($currentEvent) {
            return $this->update($currentEvent['id'], [
                'eventType' => 'resubmit_required',
                'eventTitle' => $eventTitle,
                'eventDescription' => $eventDescription,
                'eventStatus' => 'current',
                'eventData' => $eventData ? json_encode($eventData) : null,
                'createdBy' => $adminId
            ]);
        }

        return $this->addEvent(
            $submissionId,
            $clientId,
            'resubmit_required',
            $eventTitle,
            $eventDescription,
            $eventData,
            $adminId,
            'current'
        );
    }
}
