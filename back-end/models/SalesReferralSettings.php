<?php
/**
 * Sales 个人推荐链接设置与统计
 * 表：sales_referral_settings
 */

require_once __DIR__ . '/BaseModel.php';

class SalesReferralSettings extends BaseModel {
    protected $table = 'sales_referral_settings';
    protected $primaryKey = 'userId';
    private const DEFAULT_SUFFIX_LENGTH = 8;
    private const MAX_GENERATION_ATTEMPTS = 20;

    /**
     * 按 userId 获取一条（可能不存在）
     */
    public function getByUserId($userId) {
        $userId = (int)$userId;
        if ($userId <= 0) return null;
        $row = $this->db->fetchOne("SELECT * FROM {$this->table} WHERE userId = :userId LIMIT 1", ['userId' => $userId]);
        return $row ?: null;
    }

    /**
     * 按后缀解析出 salesId（userId）
     * 返回 salesId 或 0
     */
    public function getUserIdBySuffix($suffix) {
        $suffix = trim((string)$suffix);
        if ($suffix === '') return 0;
        $row = $this->db->fetchOne("SELECT userId FROM {$this->table} WHERE referralSuffix = :suffix LIMIT 1", ['suffix' => $suffix]);
        if ($row !== false && !empty($row)) {
            return (int)$row['userId'];
        }
        return 0;
    }

    /**
     * 确保该 Sales 在表中有记录（无则插入），便于后续累加 urlClicks/registrationsCount
     */
    public function ensureSettingsRow($userId, $suffix) {
        return $this->getOrCreateByUserId($userId, $suffix);
    }

    /**
     * 获取或原子创建一条配置记录。
     * - 已存在时直接返回
     * - 传入自定义后缀时优先尝试写入该后缀
     */
    public function getOrCreateByUserId($userId, $preferredSuffix = null) {
        $userId = (int)$userId;
        if ($userId <= 0) return null;

        $existing = $this->getByUserId($userId);
        if ($existing) {
            return $existing;
        }

        $preferredSuffix = trim((string)$preferredSuffix);
        if ($preferredSuffix !== '') {
            $row = $this->insertIfAbsent($userId, $preferredSuffix);
            if ($row) {
                return $row;
            }
        }

        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $row = $this->insertIfAbsent($userId, $this->generateRandomAlphaNumeric(self::DEFAULT_SUFFIX_LENGTH));
            if ($row) {
                return $row;
            }
        }

        throw new Exception('Failed to generate unique sales referral suffix');
    }

    /**
     * 批量获取多个 Sales 的配置（返回 userId => row）
     */
    public function getByUserIds(array $userIds) {
        if (empty($userIds)) return [];
        $userIds = array_map('intval', array_filter($userIds));
        if (empty($userIds)) return [];
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = $this->db->fetchAll("SELECT * FROM {$this->table} WHERE userId IN ($placeholders)", $userIds);
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['userId']] = $r;
        }
        return $map;
    }

    /**
     * 批量获取或创建多个 Sales 的推荐链接配置
     */
    public function getOrCreateByUserIds(array $userIds) {
        if (empty($userIds)) return [];
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) return [];

        $map = $this->getByUserIds($userIds);
        foreach ($userIds as $userId) {
            if (!isset($map[$userId])) {
                $row = $this->getOrCreateByUserId($userId);
                if ($row) {
                    $map[$userId] = $row;
                }
            }
        }

        return $map;
    }

    /**
     * 检查后缀是否被其他用户占用（排除指定 userId）
     * 注意：fetchOne 无记录时返回 false，不是 null
     */
    public function isSuffixTakenByOther($suffix, $excludeUserId) {
        $suffix = trim((string)$suffix);
        if ($suffix === '') return false;
        $excludeUserId = (int)$excludeUserId;
        $row = $this->db->fetchOne(
            "SELECT userId FROM {$this->table} WHERE referralSuffix = :suffix AND userId != :excludeUserId LIMIT 1",
            ['suffix' => $suffix, 'excludeUserId' => $excludeUserId]
        );
        return $row !== false && $row !== null;
    }

    /**
     * 更新或插入：设置该 Sales 的后缀
     */
    public function setSuffix($userId, $suffix) {
        $userId = (int)$userId;
        $suffix = trim((string)$suffix);
        if ($userId <= 0 || $suffix === '') return false;
        $existing = $this->getByUserId($userId);
        if ($existing) {
            $this->db->query(
                "UPDATE {$this->table} SET referralSuffix = :suffix, updatedAt = NOW() WHERE userId = :userId",
                ['suffix' => $suffix, 'userId' => $userId]
            );
        } else {
            $this->db->query(
                "INSERT INTO {$this->table} (userId, referralSuffix, urlClicks, registrationsCount, createdAt, updatedAt) VALUES (:userId, :suffix, 0, 0, NOW(), NOW())",
                ['userId' => $userId, 'suffix' => $suffix]
            );
        }
        return true;
    }

    /**
     * 访问量 +1（由 visit 接口内部做限频后调用）
     */
    public function incrementUrlClicks($userId) {
        $userId = (int)$userId;
        if ($userId <= 0) return false;
        $this->db->query(
            "UPDATE {$this->table} SET urlClicks = urlClicks + 1, updatedAt = NOW() WHERE userId = :userId",
            ['userId' => $userId]
        );
        return true;
    }

    private function insertIfAbsent($userId, $suffix) {
        $stmt = $this->db->query(
            "INSERT IGNORE INTO {$this->table} (userId, referralSuffix, urlClicks, registrationsCount, createdAt, updatedAt) VALUES (:userId, :suffix, 0, 0, NOW(), NOW())",
            ['userId' => $userId, 'suffix' => $suffix]
        );

        if ($stmt->rowCount() > 0) {
            return $this->getByUserId($userId);
        }

        $existing = $this->getByUserId($userId);
        return $existing ?: null;
    }

    /**
     * 注册数 +1
     */
    public function incrementRegistrationsCount($userId) {
        $userId = (int)$userId;
        if ($userId <= 0) return false;
        $this->db->query(
            "UPDATE {$this->table} SET registrationsCount = registrationsCount + 1, updatedAt = NOW() WHERE userId = :userId",
            ['userId' => $userId]
        );
        return true;
    }
}
