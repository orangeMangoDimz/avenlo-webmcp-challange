<?php
/**
 * JWT令牌处理类
 * 简化的JWT实现（生产环境建议使用 firebase/php-jwt 库）
 */

class JWT {
    private static $config = null;

    /**
     * 获取配置
     */
    private static function getConfig() {
        if (self::$config === null) {
            $appConfig = require __DIR__ . '/../config/app.php';
            self::$config = $appConfig['jwt'];
        }
        return self::$config;
    }

    /**
     * 生成JWT令牌
     */
    public static function encode($payload) {
        $config = self::getConfig();

        $header = [
            'typ' => 'JWT',
            'alg' => $config['algorithm']
        ];

        $payload['iss'] = $config['issuer'];
        $payload['aud'] = $config['audience'];
        $payload['iat'] = time();
        $payload['exp'] = time() + $config['expire'];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::sign(
            $headerEncoded . '.' . $payloadEncoded,
            $config['secret'],
            $config['algorithm']
        );

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * 解码JWT令牌
     */
    public static function decode($token) {
        $config = self::getConfig();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        list($headerEncoded, $payloadEncoded, $signatureProvided) = $parts;

        // 验证签名
        $signatureExpected = self::sign(
            $headerEncoded . '.' . $payloadEncoded,
            $config['secret'],
            $config['algorithm']
        );

        if (!hash_equals($signatureExpected, $signatureProvided)) {
            throw new Exception('Invalid token signature');
        }

        // 解码payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        // 验证过期时间
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }

        // 验证发行者和受众
        if (isset($payload['iss']) && $payload['iss'] !== $config['issuer']) {
            throw new Exception('Invalid token issuer');
        }

        if (isset($payload['aud']) && $payload['aud'] !== $config['audience']) {
            throw new Exception('Invalid token audience');
        }

        return $payload;
    }

    /**
     * 验证令牌
     */
    public static function verify($token) {
        try {
            self::decode($token);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 从请求头获取令牌
     */
    public static function getTokenFromHeader() {
        // 方法1: 尝试从 getallheaders() 获取（对大小写不敏感）
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            // 检查 Authorization（大小写不敏感）
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $auth = $value;
                    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }

        // 方法2: 从 $_SERVER 获取（支持 Apache 和 Nginx）
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        // 方法3: Apache 特殊处理（通过 .htaccess 重写）
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        // 方法4: 尝试从 $_SERVER 中查找所有可能的 Authorization header（大小写不敏感）
        foreach ($_SERVER as $key => $value) {
            if (strpos(strtoupper($key), 'HTTP_AUTHORIZATION') !== false ||
                strpos(strtoupper($key), 'REDIRECT_HTTP_AUTHORIZATION') !== false) {
                if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * 生成签名
     */
    private static function sign($data, $secret, $algorithm) {
        $algoMap = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512'
        ];

        if (!isset($algoMap[$algorithm])) {
            throw new Exception('Unsupported algorithm');
        }

        $signature = hash_hmac($algoMap[$algorithm], $data, $secret, true);
        return self::base64UrlEncode($signature);
    }

    /**
     * Base64 URL编码
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL解码
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * 生成刷新令牌
     */
    public static function generateRefreshToken($userId) {
        return bin2hex(random_bytes(32)) . '_' . $userId . '_' . time();
    }

    /**
     * 获取当前请求的 JWT payload
     * 这个方法会自动从请求头获取 token 并解码
     */
    public static function getPayload() {
        // 从请求头获取 token
        $token = self::getTokenFromHeader();
        if (!$token) {
            return null;
        }

        try {
            $payload = self::decode($token);
            return $payload;
        } catch (Exception $e) {
            return null;
        }

    }

}
