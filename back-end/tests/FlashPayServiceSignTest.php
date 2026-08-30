<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/FlashPayService.php';

class FlashPayServiceSignTest extends TestCase
{
    public function testBuildSignStringSortsAndSkipsEmpty(): void
    {
        $params = [
            'z' => 'last',
            'a' => 'first',
            'empty' => '',
            'sign' => 'should-be-removed',
            'channelExtra' => '{"firstName":"John Smith"}',
            'n' => null,
        ];

        $expected = 'a=first&channelExtra={"firstName":"John Smith"}&z=last';
        $this->assertSame($expected, FlashPayService::buildSignString($params));
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
        $publicPem = $details['key'];

        $gateway = [
            'apiKey' => 'MTEST',
            'appId' => 'appidtest12345678901234',
            'secretKey' => $privatePem,
            'configData' => [
                'providerKey' => 'flashpay',
                'base_url' => 'https://pay.flashpay.fit',
                'platform_public_key' => $publicPem,
            ],
        ];

        $service = new FlashPayService($gateway);
        $this->assertTrue($service->isConfigured());

        $params = [
            'amount' => '10000',
            'appId' => 'appidtest12345678901234',
            'currency' => '410',
            'mchNo' => 'MTEST',
            'mchOrderNo' => 'ORDER123',
            'channelExtra' => '{"firstName":"John Smith"}',
            'reqTime' => '1756809333043',
            'signType' => 'RSA',
            'version' => '1.0',
            'wayCode' => 'WAKRWPUL_CARD',
        ];

        $sign = $service->generateSign($params);
        $this->assertNotSame('', $sign);
        $this->assertTrue($service->verifySign(array_merge($params, ['sign' => $sign])));
        $this->assertFalse($service->verifySign(array_merge($params, ['sign' => $sign, 'amount' => '1'])));
    }

    public function testStatusMaps(): void
    {
        $this->assertSame('success', FlashPayService::mapPayInState(2));
        $this->assertSame('processing', FlashPayService::mapPayInState(1));
        $this->assertSame('failed', FlashPayService::mapPayInState(3));
        $this->assertSame('success', FlashPayService::mapPayOutState(2));
        $this->assertSame('failed', FlashPayService::mapPayOutState(4));
    }

    public function testBuildMchOrderNoMaxLength(): void
    {
        $id = 'abc-def-ghi-jkl-mno-pqr-stu-vwx-yz0123456789';
        $orderNo = FlashPayService::buildMchOrderNo($id);
        $this->assertSame(30, strlen($orderNo));
        $this->assertStringNotContainsString('-', $orderNo);
    }

    public function testNormalizeBankcardPayDataFlattensNestedBankInfoAndCashier(): void
    {
        $payData = [
            'cashier' => [
                'link' => 'https://pay.flashpay.fit/deposits/cashier/PFIN123',
            ],
            'bankInfo' => [
                'name' => 'NI EKATERINA',
                'amount' => '148025',
                'bankNo' => '15911440501017',
                'bankName' => 'IBK Industrial Bank',
                'expireTime' => '2026-08-03T07:28:39.528Z',
            ],
        ];

        $normalized = FlashPayService::normalizeBankcardPayData($payData);
        $this->assertSame('IBK Industrial Bank', $normalized['bankName']);
        $this->assertSame('15911440501017', $normalized['accountNo']);
        $this->assertSame('NI EKATERINA', $normalized['accountName']);
        $this->assertSame('148025', $normalized['amount']);
        $this->assertSame('2026-08-03T07:28:39.528Z', $normalized['expireTime']);
        $this->assertSame('https://pay.flashpay.fit/deposits/cashier/PFIN123', $normalized['cashierLink']);
        $this->assertSame($payData, $normalized['bankcard']);
    }

    public function testNormalizeBankcardPayDataSupportsFlatPayloadAndJsonString(): void
    {
        $flat = FlashPayService::normalizeBankcardPayData([
            'bankName' => 'BRI',
            'accountNo' => '1234567890123456',
            'accountName' => 'UTRADA CLIENT',
            'amount' => '150000',
        ]);
        $this->assertSame('BRI', $flat['bankName']);
        $this->assertSame('1234567890123456', $flat['accountNo']);
        $this->assertSame('UTRADA CLIENT', $flat['accountName']);
        $this->assertSame('150000', $flat['amount']);
        $this->assertArrayNotHasKey('cashierLink', $flat);

        $json = FlashPayService::normalizeBankcardPayData(json_encode([
            'bankInfo' => [
                'bankName' => 'Bank',
                'bankNo' => '92050112345678',
                'name' => 'Kim',
            ],
            'cashier' => ['link' => 'https://example.com/cashier'],
        ], JSON_UNESCAPED_SLASHES));
        $this->assertSame('Bank', $json['bankName']);
        $this->assertSame('92050112345678', $json['accountNo']);
        $this->assertSame('Kim', $json['accountName']);
        $this->assertSame('https://example.com/cashier', $json['cashierLink']);
    }

    public function testNormalizeBankOptionRowHandlesDocAndSwappedFields(): void
    {
        $this->assertSame(
            ['code' => '004', 'name' => 'KB Kookmin Bank'],
            FlashPayService::normalizeBankOptionRow(['code' => '004', 'name' => 'KB Kookmin Bank'])
        );
        $this->assertSame(
            ['code' => '092', 'name' => 'Toss Bank'],
            FlashPayService::normalizeBankOptionRow(['bankCode' => 'Toss Bank', 'bankName' => '092'])
        );
        $this->assertSame(
            ['code' => '088', 'name' => 'Shinhan Bank'],
            FlashPayService::normalizeBankOptionRow(['bankCode' => '088', 'bankName' => 'Shinhan Bank'])
        );
        $this->assertNull(
            FlashPayService::normalizeBankOptionRow(['bankCode' => 'Toss Bank', 'bankName' => 'KakaoBank'])
        );
    }

    public function testJavaStyleListSignStringMatchesProtocolNesting(): void
    {
        $list = [
            ['bankName' => '092', 'bankCode' => 'Toss Bank'],
            ['bankCode' => '088', 'bankName' => 'Shinhan Bank'],
        ];
        $expected = '[{bankCode=Toss Bank&bankName=092},{bankCode=088&bankName=Shinhan Bank}]';
        $this->assertSame($expected, FlashPayService::toJavaStyleListSignString($list));
    }

    public function testToFlashPayAmountCentsConvertsKrwMajorToCents(): void
    {
        $this->assertSame(1000000, FlashPayService::toFlashPayAmountCents(10000));
        $this->assertSame(1000040, FlashPayService::toFlashPayAmountCents(10000.4));
        $this->assertSame(793294900, FlashPayService::toFlashPayAmountCents(7932949));
    }

    public function testParseFlashPayAmountCentsDoesNotScale(): void
    {
        $this->assertSame(1000000, FlashPayService::parseFlashPayAmountCents('1000000'));
        $this->assertSame(1000000, FlashPayService::parseFlashPayAmountCents(1000000));
        $this->assertNotSame(
            FlashPayService::parseFlashPayAmountCents(10000),
            FlashPayService::toFlashPayAmountCents(10000)
        );
    }
}
