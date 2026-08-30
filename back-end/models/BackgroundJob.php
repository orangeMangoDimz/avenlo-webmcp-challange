<?php
/**
 * Individual Swoole task / background job tracking (backgroundJobs).
 */

require_once __DIR__ . '/BaseModel.php';

class BackgroundJob extends BaseModel {
    protected $table = 'backgroundJobs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'jobId',
        'schedulerRunId',
        'type',
        'status',
        'taskId',
        'serverInstanceId',
        'requestId',
        'correlationId',
        'userId',
        'userType',
        'workerId',
        'processId',
        'progressPercent',
        'processed',
        'total',
        'currentStep',
        'queuedAt',
        'startedAt',
        'lastHeartbeatAt',
        'finishedAt',
        'errorMessage',
        'result',
        'createdAt',
        'updatedAt',
    ];

    /**
     * @return array<string,mixed>|null
     */
    public function findByJobId($jobId) {
        $jobId = trim((string) $jobId);
        if ($jobId === '') {
            return null;
        }
        return $this->findOne(['jobId' => $jobId]);
    }

    /**
     * Mark running jobs stale when heartbeat/start exceeds type-specific timeout.
     *
     * @param array<string,int> $typeTimeoutsSeconds type => seconds
     */
    public function markStaleByHeartbeat(array $typeTimeoutsSeconds, $defaultTimeoutSeconds = 300) {
        $defaultTimeoutSeconds = max(60, (int) $defaultTimeoutSeconds);
        $updatedAt = gmdate('Y-m-d H:i:s');
        $total = 0;
        $handledTypes = [];

        foreach ($typeTimeoutsSeconds as $type => $seconds) {
            $type = trim((string) $type);
            $seconds = max(60, (int) $seconds);
            if ($type === '') {
                continue;
            }
            $handledTypes[] = $type;
            $total += $this->markStaleForTimeout($seconds, $updatedAt, $type, null);
        }

        $total += $this->markStaleForTimeout(
            $defaultTimeoutSeconds,
            $updatedAt,
            null,
            $handledTypes
        );

        return $total;
    }

    /**
     * @param string|null $onlyType
     * @param array<int,string>|null $excludeTypes
     */
    private function markStaleForTimeout($timeoutSeconds, $updatedAt, $onlyType = null, $excludeTypes = null) {
        $where = [
            "status = 'running'",
            '(
                (
                  lastHeartbeatAt IS NOT NULL
                  AND TIMESTAMPDIFF(SECOND, lastHeartbeatAt, UTC_TIMESTAMP()) > :timeoutSeconds
                )
                OR (
                  lastHeartbeatAt IS NULL
                  AND startedAt IS NOT NULL
                  AND TIMESTAMPDIFF(SECOND, startedAt, UTC_TIMESTAMP()) > :timeoutSeconds2
                )
             )',
        ];
        $params = [
            'timeoutSeconds' => (int) $timeoutSeconds,
            'timeoutSeconds2' => (int) $timeoutSeconds,
            'updatedAt' => $updatedAt,
        ];

        if ($onlyType !== null) {
            $where[] = '`type` = :onlyType';
            $params['onlyType'] = $onlyType;
        } elseif (is_array($excludeTypes) && !empty($excludeTypes)) {
            $placeholders = [];
            foreach (array_values($excludeTypes) as $i => $type) {
                $key = 'ex' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $type;
            }
            $where[] = '`type` NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $sql = "UPDATE {$this->table}
                SET status = 'stale',
                    finishedAt = UTC_TIMESTAMP(),
                    updatedAt = :updatedAt,
                    errorMessage = COALESCE(errorMessage, 'Marked stale by reaper (heartbeat timeout)')
                WHERE " . implode(' AND ', $where);

        $stmt = $this->db->query($sql, $params);
        return (int) $stmt->rowCount();
    }
}
