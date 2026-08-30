<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/FivePayService.php';

class FivePayServiceSignTest extends TestCase
{
    public function testBuildSignValueConcatenatesSortedNonEmptyValuesOnly(): void
    {
        $params = [
            'MerchantId' => 'M123456',
            'OrderNo' => 'ORD10001',
            'OrderAmount' => '100.50',
            'Currency' => 'MYR',
            'BankCode' => '',
            'NotifyUrl' => 'https://example.com/notify',
            'ReturnUrl' => 'https://example.com/return',
            'TimeStamp' => '20260615084519',
            'Sign' => 'ignore-me',
        ];

        $this->assertSame(
            'MYRM123456https://example.com/notify100.50ORD10001https://example.com/return20260615084519',
            FivePayService::buildSignValue($params)
        );
    }

    public function testSignAndVerifyRoundTrip(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($keyPair);
        openssl_pkey_export($keyPair, $privatePem);
        $details = openssl_pkey_get_details($keyPair);

        $service = new FivePayService([
            'apiKey' => '53795',
            'secretKey' => $privatePem,
            'configData' => [
                'providerKey' => '5pay',
                'base_url' => 'http://uat.en-payment.my5pay.com',
                'platform_public_key' => $details['key'],
            ],
        ]);
        $this->assertTrue($service->isConfigured());

        $params = [
            'MerchantId' => '53795',
            'OrderAmount' => '100.50',
            'MerchantOrderNo' => 'ORDER123',
            'CurrencyCode' => 'HKD',
            'TimeStamp' => '20260814084519',
        ];
        $sign = $service->generateSign($params);
        $this->assertNotSame('', $sign);
        $this->assertTrue($service->verifySign(array_merge($params, ['Sign' => $sign])));
        $this->assertFalse($service->verifySign(array_merge($params, ['Sign' => $sign, 'OrderAmount' => '1'])));
    }

    public function testStatusMaps(): void
    {
        $this->assertSame('processing', FivePayService::mapDepositStatus('1'));
        $this->assertSame('processing', FivePayService::mapDepositStatus('2'));
        $this->assertSame('processing', FivePayService::mapDepositStatus('3'));
        $this->assertSame('success', FivePayService::mapDepositStatus('4'));
        $this->assertSame('failed', FivePayService::mapDepositStatus('6'));
        $this->assertSame('failed', FivePayService::mapDepositStatus('7'));
        $this->assertSame('processing', FivePayService::mapDepositStatus('9'));
        $this->assertSame('success', FivePayService::mapPayoutStatus('3'));
        $this->assertSame('failed', FivePayService::mapPayoutStatus('4'));
        $this->assertSame('processing', FivePayService::mapPayoutStatus('2'));
    }

    public function testFormatAmountKeepsTwoDecimalsForHkd(): void
    {
        $this->assertSame('100.50', FivePayService::formatAmount(100.5));
        $this->assertSame('100.00', FivePayService::formatAmount(100));
    }

    public function testUtcTimestampFormat(): void
    {
        $this->assertMatchesRegularExpression('/^\d{14}$/', FivePayService::utcTimestamp());
    }

    public function testCallbackTimestampAcceptsObservedHongKongTimeOffset(): void
    {
        $callbackReceivedAt = strtotime('2026-08-18 06:53:53 UTC');

        $this->assertTrue(
            FivePayService::isTimestampFresh('20260818145353', $callbackReceivedAt)
        );
    }
}
