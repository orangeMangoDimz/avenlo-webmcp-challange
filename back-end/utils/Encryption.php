<?php
/**
 * 加密工具类
 * 用于加密/解密邀请链接等敏感信息
 */

class Encryption {
    private static $method = 'AES-256-CBC';
    private static $key;
    private static $iv;

    /**
     * 获取加密密钥
     */
    private static function getKey() {
        if (!self::$key) {
            $config = require __DIR__ . '/../config/app.php';
            // 使用JWT密钥作为加密密钥（确保长度足够）
            $jwtSecret = $config['jwt']['secret'];
            // 确保密钥长度为32字节（AES-256需要）
            self::$key = substr(hash('sha256', $jwtSecret), 0, 32);
        }
        return self::$key;
    }

    /**
     * 获取初始化向量
     */
    private static function getIv() {
        if (!self::$iv) {
            $config = require __DIR__ . '/../config/app.php';
            // Keep this technical seed stable so a visual rebrand cannot invalidate existing ciphertext.
            $appName = $config['security']['encryption_iv_seed'] ?? 'CRM';
            self::$iv = substr(hash('md5', $appName), 0, 16);
        }
        return self::$iv;
    }

    /**
     * 加密字符串
     * @param string $data 要加密的数据
     * @return string Base64编码的加密字符串
     */
    public static function encrypt($data) {
        $encrypted = openssl_encrypt($data, self::$method, self::getKey(), 0, self::getIv());
        return base64_encode($encrypted);
    }

    /**
     * 解密字符串
     * @param string $encryptedData Base64编码的加密字符串
     * @return string|false 解密后的数据，失败返回false
     */
    public static function decrypt($encryptedData) {
        $data = base64_decode($encryptedData);
        if ($data === false) {
            return false;
        }
        return openssl_decrypt($data, self::$method, self::getKey(), 0, self::getIv());
    }

    /**
     * 加密URL参数（用于邀请链接）
     * @param string $invitationCode 邀请码
     * @return string 加密后的字符串（URL安全）
     */
    public static function encryptInvitationCode($invitationCode) {
        $encrypted = self::encrypt($invitationCode);
        // 替换URL不安全的字符
        return str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);
    }

    /**
     * 解密URL参数
     * @param string $encryptedCode 加密的邀请码
     * @return string|false 解密后的邀请码，失败返回false
     */
    public static function decryptInvitationCode($encryptedCode) {
        // 恢复URL安全的字符
        $encrypted = str_replace(['-', '_'], ['+', '/'], $encryptedCode);
        // 补充可能缺失的填充
        $padding = 4 - (strlen($encrypted) % 4);
        if ($padding !== 4) {
            $encrypted .= str_repeat('=', $padding);
        }
        return self::decrypt($encrypted);
    }
}
