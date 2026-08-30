<?php
/**
 * UUID 工具
 * 项目里没引第三方 uuid 包，用 random_bytes 自己拼一个 RFC 4122 v4。
 */

class Uuid
{
    /**
     * 生成 UUID v4：xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    public static function v4()
    {
        $data = random_bytes(16);
        // 版本位（第 7 字节高 4 位 → 0100）
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // variant 位（第 9 字节高 2 位 → 10）
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
