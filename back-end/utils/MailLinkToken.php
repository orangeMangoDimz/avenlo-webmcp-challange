<?php
/**
 * 邮件链接令牌
 * 仅后端可解码，用于生成/解析邮件内加密链接，不暴露接口路径与参数含义。
 */

require_once __DIR__ . '/Encryption.php';

class MailLinkToken {
    /** 类型：IB 邀请文档查看 */
    const TYPE_IB_DOCUMENT = 'ib_document';

    /**
     * 生成加密令牌（URL 安全）
     * @param array $payload 例如 ['type' => 'ib_document', 'documentId' => 1, 'code' => '...']
     * @return string URL 安全的密文，可直接作为 index.php?t= 的值
     */
    public static function encode(array $payload) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $encrypted = Encryption::encrypt($json);
        return str_replace(['+', '/', '='], ['-', '_', ''], $encrypted);
    }

    /**
     * 解析加密令牌
     * @param string $token 从 index.php?t= 获取的字符串
     * @return array|null 解密后的 payload，失败返回 null
     */
    public static function decode($token) {
        if (!is_string($token) || $token === '') {
            return null;
        }
        $encrypted = str_replace(['-', '_'], ['+', '/'], $token);
        $padding = 4 - (strlen($encrypted) % 4);
        if ($padding !== 4) {
            $encrypted .= str_repeat('=', $padding);
        }
        $json = Encryption::decrypt($encrypted);
        if ($json === false) {
            return null;
        }
        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }
}
