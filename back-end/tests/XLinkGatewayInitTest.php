<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../script/xlinkgatewayinit.php';

final class XLinkGatewayInitTest extends TestCase {
    public function testGatewayConfigUsesTheFixedKrwIdentity(): void {
        $config = getXLinkGatewayConfig();

        $this->assertSame('xlink-krw', $config['gatewayKey']);
        $this->assertSame('X-Link KRW', $config['gatewayName']);
        $this->assertSame('X-Link KRW', $config['methodName']);
        $this->assertSame('KRW', $config['currency']);
        $this->assertSame('KR', $config['country']);
        $this->assertSame(29, $config['displayOrder']);
        $this->assertSame(0, $config['amountDecimalPlaces']);
    }

    public function testPayloadMapsCredentialsAndEnablesBothFlows(): void {
        $payload = buildXLinkGatewayPayload(
            getXLinkGatewayConfig(),
            [
                'shopId' => '108',
                'apiKey' => 'test-api-key',
                'secretKey' => 'test-hmac-secret',
                'environment' => 'sandbox',
                'baseUrl' => ''
            ]
        );

        $this->assertSame('test-api-key', $payload['gatewayData']['apiKey']);
        $this->assertSame('test-hmac-secret', $payload['gatewayData']['secretKey']);
        $this->assertSame(108, $payload['configData']['shop_id']);
        $this->assertSame('xlink', $payload['configData']['providerKey']);
        $this->assertSame('KRW', $payload['configData']['currency']);
        $this->assertSame(1, $payload['gatewayData']['isEnabled']);
        $this->assertSame(1, $payload['gatewayData']['isDepositEnabled']);
        $this->assertSame(1, $payload['gatewayData']['isWithdrawalEnabled']);
    }

    public function testCredentialLessRerunRetainsExistingCredentialsAndShopId(): void {
        $payload = buildXLinkGatewayPayload(
            getXLinkGatewayConfig(),
            [
                'shopId' => '',
                'apiKey' => '',
                'secretKey' => '',
                'environment' => '',
                'baseUrl' => ''
            ],
            [
                'apiKey' => 'existing-api-key',
                'secretKey' => 'existing-hmac-secret'
            ],
            [
                'shop_id' => 108,
                'base_url' => 'https://api.stage.x-link.asia/api/v1/p2p'
            ]
        );

        $this->assertSame('existing-api-key', $payload['gatewayData']['apiKey']);
        $this->assertSame('existing-hmac-secret', $payload['gatewayData']['secretKey']);
        $this->assertSame(108, $payload['configData']['shop_id']);
        $this->assertSame(1, $payload['gatewayData']['isEnabled']);
    }

    public function testEnvironmentSelectsSandboxAndProductionBaseUrls(): void {
        $config = getXLinkGatewayConfig();

        $sandbox = buildXLinkGatewayPayload($config, [
            'shopId' => '1', 'apiKey' => 'key', 'secretKey' => 'secret',
            'environment' => 'sandbox', 'baseUrl' => ''
        ]);
        $production = buildXLinkGatewayPayload($config, [
            'shopId' => '1', 'apiKey' => 'key', 'secretKey' => 'secret',
            'environment' => 'production', 'baseUrl' => ''
        ]);

        $this->assertSame('https://api.stage.x-link.asia/api/v1/p2p', $sandbox['configData']['base_url']);
        $this->assertSame('https://api.x-link.asia/api/v1/p2p', $production['configData']['base_url']);
    }

    public function testPayinRequiresAccountNameOnly(): void {
        $questions = getXLinkDepositQuestions();
        $byName = $this->questionsByName($questions);

        $this->assertSame(['account_name'], array_keys($byName));
        $this->assertStringContainsString('required', $byName['account_name']['validationRules']);
        $this->assertSame(PaymentSupportQuestion::SCOPE_DEPOSIT, $byName['account_name']['scope']);
    }

    public function testPayoutFieldsHaveTheRequiredAndOptionalContract(): void {
        $questions = $this->questionsByName(getXLinkWithdrawSupportQuestions());

        foreach (['account_number', 'account_name', 'bank_province', 'bank_city', 'bank_code'] as $name) {
            $this->assertArrayHasKey($name, $questions);
            $this->assertStringContainsString('required', $questions[$name]['validationRules']);
        }

        foreach (['bank_branch', 'customer_name', 'customer_lastname'] as $name) {
            $this->assertArrayHasKey($name, $questions);
            $this->assertStringNotContainsString('required', $questions[$name]['validationRules']);
        }
    }

    public function testWithdrawalTemplateMatchesWithdrawalSupportQuestionRequirements(): void {
        $support = $this->questionsByName(getXLinkWithdrawSupportQuestions());
        $template = $this->questionsByName(getXLinkWithdrawalTemplateQuestions(), 'scope');

        $this->assertSame(array_keys($support), array_keys($template));
        foreach ($support as $name => $supportQuestion) {
            $this->assertSame($supportQuestion['validationRules'], $template[$name]['validationRules']);
            $this->assertSame(
                strpos((string)$supportQuestion['validationRules'], 'required') !== false ? 1 : 0,
                (int)$template[$name]['isRequired']
            );
        }
    }

    public function testWithdrawalTemplateScopesAreAcceptedByTheQuestionModel(): void {
        foreach (getXLinkWithdrawalTemplateQuestions() as $question) {
            $this->assertContains($question['scope'], WithdrawalVerificationQuestion::ALLOWED_SCOPES);
        }
    }

    private function questionsByName(array $questions, string $key = 'name'): array {
        $indexed = [];
        foreach ($questions as $question) {
            $indexed[$question[$key]] = $question;
        }
        return $indexed;
    }
}
