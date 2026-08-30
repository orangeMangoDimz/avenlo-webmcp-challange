<?php
/**
 * 给 trading platform 签的独立 HS256 JWT。
 * 与 CRM 自身的 utils/JWT.php 不复用 secret/issuer/audience，配置取自 app.php['trading_platform_jwt']。
 *
 * Claim 形态对齐 trading platform 期望：
 *
 *   [issue() — user-bound，对应 password grant]
 *     aud  = config.audience（如 apple_app）
 *     iss  = config.issuer（如 YuebonTeach）
 *     name = clientUsers.email
 *     id   = FinancePro SysUserId（用户级标识）
 *     role = config.role（固定，如 administrators）
 *     sub  = config.subject（固定，如 password）
 *     iat / nbf / exp 由 ttl 决定
 *
 *   [issueClientCredentials() — 未开户用户的 fallback，对应 client_credentials grant]
 *     aud  = config.audience
 *     iss  = config.issuer
 *     sub  = "client_credential"   // 固定，区别于 password grant
 *     iat / nbf / exp 由 ttl 决定
 *     —— 不带 name / id / role，因为没有用户级标识
 */

class TradingPlatformJwt {
    /**
     * 签发 trading platform 的 access token。
     *
     * @param string $name      用户登录名（email）
     * @param string $sysUserId FinancePro 返回的 SysUserId
     * @param int    $ttlSeconds 过期秒数；调用方传 CRM access TTL 保持一致
     * @return string JWT 字符串
     * @throws Exception 配置缺失（secret 未填）时抛出
     */
    public static function issue($name, $sysUserId, $ttlSeconds) {
        $appConfig = require __DIR__ . '/../config/app.php';
        $cfg = $appConfig['trading_platform_jwt'] ?? [];

        $secret = (string)($cfg['secret'] ?? '');
        if ($secret === '') {
            throw new Exception('trading_platform_jwt.secret is not configured');
        }

        $now = time();
        $payload = [
            'aud'  => (string)($cfg['audience'] ?? ''),
            'iss'  => (string)($cfg['issuer'] ?? ''),
            'name' => (string)$name,
            'id'   => (string)$sysUserId,
            'role' => (string)($cfg['role'] ?? ''),
            'sub'  => (string)($cfg['subject'] ?? ''),
            'iat'  => $now,
            'exp'  => $now + (int)$ttlSeconds,
            'nbf'  => $now,
        ];

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $headerEncoded  = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * 签发 client_credentials grant 的 token：用户尚未在 FP 开过户（没有 SysUserId）时使用，
     * 让前端依然能拿到一个可访问 trading platform 公共接口的 token。
     *
     * Claim 只保留 aud/iss/sub + iat/nbf/exp；secret / audience / issuer 完全复用与 issue() 相同的配置。
     *
     * @param int $ttlSeconds 过期秒数；调用方传 CRM access TTL 保持一致
     * @return string JWT 字符串
     * @throws Exception 配置缺失（secret 未填）时抛出
     */
    public static function issueClientCredentials($ttlSeconds) {
        $appConfig = require __DIR__ . '/../config/app.php';
        $cfg = $appConfig['trading_platform_jwt'] ?? [];

        $secret = (string)($cfg['secret'] ?? '');
        if ($secret === '') {
            throw new Exception('trading_platform_jwt.secret is not configured');
        }

        $now = time();
        $payload = [
            'aud' => (string)($cfg['audience'] ?? ''),
            'iss' => (string)($cfg['issuer'] ?? ''),
            'sub' => 'client_credential',
            'iat' => $now,
            'exp' => $now + (int)$ttlSeconds,
            'nbf' => $now,
        ];

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $headerEncoded  = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
