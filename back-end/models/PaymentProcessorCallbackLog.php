<?php
/**
 * Payment Processor Callback Log Model
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentProcessorCallbackLog extends BaseModel {
    protected $table = 'paymentProcessorCallbackLogs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'provider',
        'transactionType',
        'orderId',
        'callbackStatus',
        'amount',
        'ip',
        'rawPayload',
        'isValid',
        'isProcessed',
        'processResult',
        'errorMessage',
        'depositId',
        'withdrawalId',
        'requestLogId',
        'correlationMethod',
        'processedAt'
    ];
}
