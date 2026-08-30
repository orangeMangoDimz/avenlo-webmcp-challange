<?php
/**
 * App → WebView 单次 handoff code
 */

require_once __DIR__ . '/BaseModel.php';

class AppWebHandoff extends BaseModel {
    protected $table = 'appWebHandoffs';
    protected $primaryKey = 'id';

    protected $fillable = [
        'code', 'userId', 'appSessionId', 'redirect',
        'ipAddress', 'userAgent', 'expiresAt', 'usedAt'
    ];

    /**
     * 创建一个新 code，返回 ['id'=>..., 'code'=>..., 'expiresAt'=>...]
     *
     * 重要：expiresAt 用 MySQL 的 DATE_ADD(NOW(), INTERVAL ...) 计算，
     * 不用 PHP 的 date()，避免 PHP 与 MySQL 时区不一致导致刚发的 code 立刻被判过期。
     */
    public function issue($userId, $appSessionId, $redirect, $ttlSeconds = 60, $context = []) {
        $code = bin2hex(random_bytes(48));
        $ttl = (int)$ttlSeconds;

        $sql = "INSERT INTO {$this->table}
                (code, userId, appSessionId, redirect, ipAddress, userAgent, expiresAt, createdAt)
                VALUES (:code, :userId, :appSessionId, :redirect, :ipAddress, :userAgent,
                        DATE_ADD(NOW(), INTERVAL :ttl SECOND), NOW())";
        $this->db->query($sql, [
            'code' => $code,
            'userId' => (int)$userId,
            'appSessionId' => $appSessionId !== null ? (int)$appSessionId : null,
            'redirect' => (string)$redirect,
            'ipAddress' => $context['ipAddress'] ?? null,
            'userAgent' => $context['userAgent'] ?? null,
            'ttl' => $ttl,
        ]);
        $id = (int)$this->db->getConnection()->lastInsertId();

        // 回查实际写入的 expiresAt（MySQL 算出来的，跟客户端时区无关）
        $row = $this->db->fetchOne("SELECT expiresAt FROM {$this->table} WHERE id = :id", ['id' => $id]);

        return [
            'id' => $id,
            'code' => $code,
            'expiresAt' => $row['expiresAt'] ?? null,
            'expiresIn' => $ttl,
        ];
    }

    /**
     * 取出仍有效（未用且未过期）的 handoff
     */
    public function findUsable($code) {
        $sql = "SELECT * FROM {$this->table}
                WHERE code = :code
                  AND usedAt IS NULL
                  AND expiresAt > NOW()
                LIMIT 1";
        return $this->db->fetchOne($sql, ['code' => (string)$code]);
    }

    /**
     * 单次使用：原子地把 usedAt 置为当前时间。
     * 返回受影响行数 —— 1 = 抢占成功，0 = 已被使用 / 已过期 / 不存在。
     * 必须配合"先 SELECT 再 UPDATE 看 rowCount"使用，避免并发兑换。
     */
    public function consume($id) {
        $sql = "UPDATE {$this->table}
                SET usedAt = NOW()
                WHERE id = :id AND usedAt IS NULL AND expiresAt > NOW()";
        $stmt = $this->db->query($sql, ['id' => (int)$id]);
        return $stmt->rowCount();
    }
}
