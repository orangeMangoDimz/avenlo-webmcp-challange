<?php
/**
 * Current state of each logical Swoole scheduler (swooleSchedulerStatus).
 */

require_once __DIR__ . '/BaseModel.php';

class SwooleSchedulerStatus extends BaseModel {
    protected $table = 'swooleSchedulerStatus';
    protected $primaryKey = 'id';
    protected $fillable = [
        'schedulerKey',
        'name',
        'schedulerType',
        'intervalMs',
        'status',
        'workerId',
        'processId',
        'serverInstanceId',
        'timerId',
        'lastTickAt',
        'lastStartedAt',
        'lastCompletedAt',
        'nextRunAt',
        'lastDispatchedCount',
        'lastErrorMessage',
        'heartbeatAt',
        'metadata',
        'createdAt',
        'updatedAt',
    ];

    /**
     * @param array<string,mixed> $data
     * @return int|null Row id
     */
    public function upsertByKey($schedulerKey, array $data) {
        $schedulerKey = trim((string) $schedulerKey);
        if ($schedulerKey === '') {
            return null;
        }

        $existing = $this->findOne(['schedulerKey' => $schedulerKey]);
        $now = gmdate('Y-m-d H:i:s');
        $data['schedulerKey'] = $schedulerKey;
        $data['updatedAt'] = $now;

        if ($existing) {
            $id = (int) $existing['id'];
            $this->update($id, $data);
            return $id;
        }

        if (!isset($data['createdAt'])) {
            $data['createdAt'] = $now;
        }
        $id = $this->create($data);
        return $id !== false && $id !== null ? (int) $id : null;
    }

    /**
     * Mark active schedulers from other Swoole generations as stale.
     */
    public function markOtherInstancesStale($serverInstanceId) {
        $serverInstanceId = trim((string) $serverInstanceId);
        if ($serverInstanceId === '') {
            return 0;
        }

        $sql = "UPDATE {$this->table}
                SET status = 'stale',
                    updatedAt = :updatedAt
                WHERE status = 'active'
                  AND serverInstanceId IS NOT NULL
                  AND serverInstanceId <> :serverInstanceId";

        $stmt = $this->db->query($sql, [
            'updatedAt' => gmdate('Y-m-d H:i:s'),
            'serverInstanceId' => $serverInstanceId,
        ]);
        return (int) $stmt->rowCount();
    }

    /**
     * Mark active schedulers stale when heartbeat exceeds multiplier * interval.
     */
    public function markStaleByHeartbeat($intervalMultiplier = 3) {
        $multiplier = max(1, (int) $intervalMultiplier);
        $sql = "UPDATE {$this->table}
                SET status = 'stale',
                    updatedAt = :updatedAt
                WHERE status = 'active'
                  AND heartbeatAt IS NOT NULL
                  AND intervalMs > 0
                  AND TIMESTAMPDIFF(SECOND, heartbeatAt, UTC_TIMESTAMP())
                      > CEIL(intervalMs / 1000) * {$multiplier}";

        $stmt = $this->db->query($sql, [
            'updatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        return (int) $stmt->rowCount();
    }
}
