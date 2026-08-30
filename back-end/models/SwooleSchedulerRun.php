<?php
/**
 * Execution history for Swoole schedulers (swooleSchedulerRuns).
 */

require_once __DIR__ . '/BaseModel.php';

class SwooleSchedulerRun extends BaseModel {
    protected $table = 'swooleSchedulerRuns';
    protected $primaryKey = 'id';
    protected $fillable = [
        'schedulerId',
        'runId',
        'serverInstanceId',
        'status',
        'startedAt',
        'finishedAt',
        'durationMs',
        'discoveredCount',
        'dispatchedCount',
        'failedCount',
        'errorMessage',
        'createdAt',
    ];

    /**
     * @return array<string,mixed>|null
     */
    public function findByRunId($runId) {
        $runId = trim((string) $runId);
        if ($runId === '') {
            return null;
        }
        return $this->findOne(['runId' => $runId]);
    }
}
