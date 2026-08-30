<?php
/**
 * Encryptor
 * 提供可逆对称加密和解密方法，用于存储敏感数据（如 Investor PIN）
 */

class Encryptor
{
    private const CIPHER = 'AES-256-CBC';

    /**
     * 加密明文
     *
     * @param string|null $plainText
     * @param string|null $customKey
     * @return string|null
     * @throws Exception
     */
    public static function encrypt($plainText, $customKey = null)
    {
        if ($plainText === null || $plainText === '') {
            return null;
        }

        [$key, $iv] = self::getKeyAndIv($customKey);

        $encrypted = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new Exception('Failed to encrypt data');
        }

        return base64_encode($encrypted);
    }

    /**
     * 解密密文
     *
     * @param string|null $cipherText
     * @param string|null $customKey
     * @return string|null
     * @throws Exception
     */
    public static function decrypt($cipherText, $customKey = null)
    {
        if ($cipherText === null || $cipherText === '') {
            return null;
        }

        [$key, $iv] = self::getKeyAndIv($customKey);

        $decoded = base64_decode($cipherText, true);
        if ($decoded === false) {
            throw new Exception('Failed to decode encrypted data');
        }

        $decrypted = openssl_decrypt($decoded, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new Exception('Failed to decrypt data');
        }

        return $decrypted;
    }

    /**
     * 生成密钥和 IV
     *
     * @param string|null $customKey
     * @return array
     */
    private static function getKeyAndIv($customKey = null)
    {
        static $cachedKey = null;
        static $cachedIv = null;

        if ($customKey === null && $cachedKey !== null && $cachedIv !== null) {
            return [$cachedKey, $cachedIv];
        }

        $keyMaterial = $customKey ?? self::getDefaultKey();

        // 生成 32 字节的密钥
        $key = hash('sha256', $keyMaterial, true);

        // 生成 16 字节的 IV（与密钥不同）
        $ivSource = hash('sha256', $keyMaterial . '_iv', true);
        $iv = substr($ivSource, 0, 16);

        if ($customKey === null) {
            $cachedKey = $key;
            $cachedIv = $iv;
        }

        return [$key, $iv];
    }

    /**
     * 从配置加载默认密钥
     *
     * @return string
     */
    private static function getDefaultKey()
    {
        static $defaultKey = null;

        if ($defaultKey !== null) {
            return $defaultKey;
        }

        $config = require __DIR__ . '/../config/app.php';

        $defaultKey = $config['encryption']['key'] ?? 'finance_pro_encryption_key_2025';

        return $defaultKey;
    }
}
