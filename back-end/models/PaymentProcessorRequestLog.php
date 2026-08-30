<?php
/**
 * Payment Processor Request Log Model
 * Outbound PSP request attempt history (soft refs to deposit/withdrawal).
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentProcessorRequestLog extends BaseModel {
    protected $table = 'paymentProcessorRequestLogs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'provider',
        'environment',
        'transactionType',
        'operation',
        'deliveryMode',
        'depositId',
        'withdrawalId',
        'localOrderId',
        'providerOrderId',
        'providerRequestId',
        'attemptNo',
        'idempotencyKey',
        'amount',
        'currencyCode',
        'requestMethod',
        'endpointPath',
        'requestPayload',
        'responseHttpStatus',
        'providerStatus',
        'responsePayload',
        'requestStatus',
        'errorCode',
        'errorMessage',
        'requestId',
        'correlationId',
        'startedAt',
        'completedAt',
        'durationMs',
        'createdAt',
        'updatedAt',
    ];

    public function getTableName() {
        return $this->table;
    }

    /**
     * Next attempt number for the same provider/operation/local order.
     *
     * @return int
     */
    public function nextAttemptNo($provider, $operation, $localOrderId) {
        $row = $this->queryOne(
            "SELECT MAX(attemptNo) AS maxAttempt
             FROM {$this->table}
             WHERE provider = :provider
               AND operation = :operation
               AND localOrderId = :localOrderId",
            [
                'provider' => (string) $provider,
                'operation' => (string) $operation,
                'localOrderId' => (string) $localOrderId,
            ]
        );

        $max = isset($row['maxAttempt']) ? (int) $row['maxAttempt'] : 0;
        return $max > 0 ? $max + 1 : 1;
    }
}
