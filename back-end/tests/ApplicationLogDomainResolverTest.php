<?php

use PHPUnit\Framework\TestCase;

$resolverFile = __DIR__ . '/../utils/ApplicationLogDomainResolver.php';
if (is_file($resolverFile)) {
    require_once $resolverFile;
}

class ApplicationLogDomainResolverTest extends TestCase
{
    public function testOrdinaryUnmatchedRequestIsClassifiedAsRouting(): void
    {
        $this->assertSame(
            'routing',
            $this->resolve('robots.txt', [])
        );
    }

    public function testPaymentIdentifiersClassifyAnUnmatchedRequestAsPayment(): void
    {
        $this->assertSame(
            'payment',
            $this->resolve('unknown-callback', ['mchOrderNo', 'sign'])
        );
    }

    public function testPaymentPathClassifiesAnUnmatchedRequestAsPayment(): void
    {
        $this->assertSame(
            'payment',
            $this->resolve('api/payment-methods/unknown', [])
        );
    }

    private function resolve(string $requestPath, array $requestKeys): ?string
    {
        if (!class_exists('ApplicationLogDomainResolver')) {
            return null;
        }

        return ApplicationLogDomainResolver::resolveUnmatchedRequest($requestPath, $requestKeys);
    }
}
