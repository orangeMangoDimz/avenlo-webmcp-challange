<?php
/**
 * 读取 HTTP 请求体
 */

class RequestInput {
    public static function readRawBody() {
        $raw = file_get_contents('php://input');
        return $raw === false ? '' : $raw;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function readJsonBody() {
        $raw = self::readRawBody();
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
