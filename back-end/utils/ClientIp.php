<?php
/**
 * 客户端 IP 获取（公共方法）
 * 考虑代理、X-Forwarded-For 等，供各接口统一使用
 */
class ClientIp {
    /**
     * 获取当前请求的客户端 IP
     * 优先级：HTTP_CLIENT_IP（公网）-> HTTP_X_FORWARDED_FOR 最后一段（公网）-> REMOTE_ADDR（公网或私网兜底）
     * @return string 有效 IP 或 '0.0.0.0'
     */
    public static function getClientIp() {
        $ip = '';
        $ip_client = $_SERVER['HTTP_CLIENT_IP'] ?? '';
        $ip_x = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($ip_x !== '') {
            $address = explode(',', $ip_x);
            $ip_x = trim($address[count($address) - 1]);
        }
        $ip_remote = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($ip_client !== '' && filter_var($ip_client, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) !== false) {
            $ip = $ip_client;
        }
        if ($ip === '' && $ip_x !== '' && filter_var($ip_x, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) !== false) {
            $ip = $ip_x;
        }
        if ($ip === '' && $ip_remote !== '' && filter_var($ip_remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) !== false) {
            $ip = $ip_remote;
        }
        // 无公网 IP 时兜底，避免返回空导致后续逻辑异常
        if ($ip === '' && $ip_remote !== '' && filter_var($ip_remote, FILTER_VALIDATE_IP)) {
            $ip = $ip_remote;
        }
        return $ip !== '' ? $ip : '0.0.0.0';
    }
}
