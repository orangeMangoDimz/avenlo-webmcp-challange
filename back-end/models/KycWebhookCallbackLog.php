<?php
/**
 * KYC Webhook Callback Log Model
 * 对应表: kycWebhookCallbackLogs
 *
 * 每次第三方 KYC 平台（Sumsub 等）的回调都会先写一条记录到这里，
 * 不管验签成功失败、不管是否找到 submission、不管是否真的更新了状态。
 * 出问题时直接拿 rawPayload 比对排查。
 */

require_once __DIR__ . '/BaseModel.php';

class KycWebhookCallbackLog extends BaseModel
{
    protected $table = 'kycWebhookCallbackLogs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'provider',
        'eventType',
        'externalUserId',
        'applicantId',
        'reviewStatus',
        'reviewAnswer',
        'submissionId',
        'ip',
        'isValid',
        'isProcessed',
        'processResult',
        'errorMessage',
        'rawPayload',
        'processedAt',
    ];
}
