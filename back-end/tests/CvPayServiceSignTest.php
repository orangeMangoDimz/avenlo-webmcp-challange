<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/CvPayService.php';

class CvPayServiceSignTest extends TestCase
{
    public function testBuildSignStringSortsAndSkipsEmpty(): void
    {
        $params = [
            'z' => 'last',
            'a' => 'first',
            'empty' => '',
            'sign' => 'should-be-removed',
            'n' => null,
        ];

        $expected = 'a=first&z=last';
        $this->assertSame($expected, CvPayService::buildSignString($params));
    }

    public function testSignAndVerifyRoundTrip(): void
    {
        $gateway = [
            'apiKey' => 'MTEST',
            'appId' => 'app001',
            'secretKey' => 'YOUR_PRIVATE_KEY',
            'configData' => [
                'providerKey' => 'cvpay',
                'base_url' => 'https://sandbox-payapi.cvpay.info',
                'default_way_code' => 'QRCODE247',
                'currency' => 'vnd',
            ],
        ];

        $service = new CvPayService($gateway);
        $this->assertTrue($service->isConfigured());

        $params = [
            'amount' => '100000',
            'appId' => 'app001',
            'currency' => 'vnd',
            'mchNo' => 'M001',
            'mchOrderNo' => 'T20260101001',
            'reqTime' => '1704067200',
            'signType' => 'MD5',
            'version' => '3.0',
        ];

        $signString = CvPayService::buildSignString($params);
        $this->assertSame(
            'amount=100000&appId=app001&currency=vnd&mchNo=M001&mchOrderNo=T20260101001&reqTime=1704067200&signType=MD5&version=3.0',
            $signString
        );

        $sign = $service->generateSign($params);
        $this->assertSame(strtoupper(md5($signString . '&key=YOUR_PRIVATE_KEY')), $sign);
        $this->assertTrue($service->verifySign(array_merge($params, ['sign' => $sign])));
        $this->assertFalse($service->verifySign(array_merge($params, ['sign' => $sign, 'amount' => '1'])));
    }

    public function testStatusMaps(): void
    {
        $this->assertSame('success', CvPayService::mapPayInState(2));
        $this->assertSame('processing', CvPayService::mapPayInState(1));
        $this->assertSame('failed', CvPayService::mapPayInState(3));
        $this->assertSame('failed', CvPayService::mapPayInState(6));
        $this->assertSame('success', CvPayService::mapPayOutState(2));
        $this->assertSame('processing', CvPayService::mapPayOutState(1));
        $this->assertSame('failed', CvPayService::mapPayOutState(4));
    }

    public function testBuildMchOrderNoMaxLength(): void
    {
        $id = 'abc-def-ghi-jkl-mno-pqr-stu-vwx-yz0123456789';
        $orderNo = CvPayService::buildMchOrderNo($id);
        $this->assertSame(30, strlen($orderNo));
        $this->assertStringNotContainsString('-', $orderNo);
    }

    public function testToCvPayAmountIsIntegerVnd(): void
    {
        $this->assertSame(100000, CvPayService::toCvPayAmount(100000));
        $this->assertSame(100000, CvPayService::toCvPayAmount(100000.4));
        $this->assertSame(100001, CvPayService::toCvPayAmount(100000.6));
    }

    public function testParseCvPayAmountDoesNotScale(): void
    {
        $this->assertSame(100000, CvPayService::parseCvPayAmount('100000'));
        $this->assertSame(100000, CvPayService::parseCvPayAmount(100000));
    }
}
