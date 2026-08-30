<?php

/**
 * Resolves a domain for warnings emitted while routing an unmatched request.
 */
class ApplicationLogDomainResolver
{
    private const PAYMENT_KEYS = [
        'payOrderId',
        'mchOrderNo',
        'wayCode',
        'ifCode',
        'mchNo',
    ];

    private const PAYMENT_PATH_SEGMENTS = [
        'pay',
        'payment',
        'payments',
        'deposit',
        'deposits',
        'withdraw',
        'withdrawal',
        'withdrawals',
        'flashpay',
        '5pay',
        'spay',
        'cvpay',
        'vexora',
        'ibeepay',
        'coinsbuy',
        'paymentasia',
    ];

    /**
     * @param string $requestPath
     * @param array<int|string,mixed> $requestKeys
     */
    public static function resolveUnmatchedRequest($requestPath, array $requestKeys): string
    {
        if (self::hasPaymentKey($requestKeys) || self::hasPaymentPath($requestPath)) {
            return 'payment';
        }

        return 'routing';
    }

    /**
     * @param array<int|string,mixed> $requestKeys
     */
    private static function hasPaymentKey(array $requestKeys): bool
    {
        $normalizedKeys = array_map(static function ($key): string {
            return strtolower(trim((string) $key));
        }, $requestKeys);

        $paymentKeys = array_map(static function ($key): string {
            return strtolower($key);
        }, self::PAYMENT_KEYS);

        return !empty(array_intersect($paymentKeys, $normalizedKeys));
    }

    private static function hasPaymentPath($requestPath): bool
    {
        $segments = preg_split(
            '/[\/_-]+/',
            strtolower(trim((string) $requestPath, '/')),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($segments)) {
            return false;
        }

        return !empty(array_intersect(self::PAYMENT_PATH_SEGMENTS, $segments));
    }
}
