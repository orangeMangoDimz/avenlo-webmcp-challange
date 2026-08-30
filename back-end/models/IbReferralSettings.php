<?php
/**
 * IB 推荐链接设置（IB Referral URL）
 * 表：ib_referral_settings
 * 与 Sales Personal Referral URL 类似，用于绑定 IB 与通过该链接注册的 Client。
 */

require_once __DIR__ . '/BaseModel.php';

class IbReferralSettings extends BaseModel {
    protected $table = 'ib_referral_settings';
    protected $primaryKey = 'id';
    private const DEFAULT_SUFFIX_LENGTH = 8;
    private const MAX_GENERATION_ATTEMPTS = 20;

    /**
     * 按 ibPartnerId 获取一条（可能不存在）
     */
    public function getByIbPartnerId($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) return null;
        $row = $this->db->fetchOne("SELECT * FROM {$this->table} WHERE ibPartnerId = :ibPartnerId LIMIT 1", ['ibPartnerId' => $ibPartnerId]);
        return $row ?: null;
    }

    /**
     * 按后缀解析出 ibPartnerId
     * 返回 ibPartnerId 或 0
     */
    public function getIbPartnerIdBySuffix($suffix) {
        $suffix = trim((string)$suffix);
        if ($suffix === '') return 0;
        $row = $this->db->fetchOne("SELECT ibPartnerId FROM {$this->table} WHERE referralSuffix = :suffix LIMIT 1", ['suffix' => $suffix]);
        if ($row !== false && !empty($row)) {
            return (int)$row['ibPartnerId'];
        }
        return 0;
    }

    /**
     * 确保该 IB 在表中有记录（无则插入），便于后续累加 urlClicks/registrationsCount
     */
    public function ensureSettingsRow($ibPartnerId, $suffix) {
        return $this->getOrCreateByIbPartnerId($ibPartnerId, $suffix);
    }

    /**
     * 获取或原子创建一条配置记录。
     * - 已存在时直接返回
     * - 传入自定义后缀时优先尝试写入该后缀
     */
    public function getOrCreateByIbPartnerId($ibPartnerId, $preferredSuffix = null) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) return null;

        $existing = $this->getByIbPartnerId($ibPartnerId);
        if ($existing) {
            return $existing;
        }

        $preferredSuffix = trim((string)$preferredSuffix);
        if ($preferredSuffix !== '') {
            $row = $this->insertIfAbsent($ibPartnerId, $preferredSuffix);
            if ($row) {
                return $row;
            }
        }

        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $row = $this->insertIfAbsent($ibPartnerId, $this->generateRandomAlphaNumeric(self::DEFAULT_SUFFIX_LENGTH));
            if ($row) {
                return $row;
            }
        }

        throw new Exception('Failed to generate unique IB referral suffix');
    }

    /**
     * 批量获取多个 IB 的配置（返回 ibPartnerId => row）
     */
    public function getByIbPartnerIds(array $ibPartnerIds) {
        if (empty($ibPartnerIds)) return [];
        $ibPartnerIds = array_map('intval', array_filter($ibPartnerIds));
        if (empty($ibPartnerIds)) return [];
        $placeholders = implode(',', array_fill(0, count($ibPartnerIds), '?'));
        $rows = $this->db->fetchAll("SELECT * FROM {$this->table} WHERE ibPartnerId IN ($placeholders)", $ibPartnerIds);
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['ibPartnerId']] = $r;
        }
        return $map;
    }

    /**
     * 批量获取或创建多个 IB 的推荐链接配置
     */
    public function getOrCreateByIbPartnerIds(array $ibPartnerIds) {
        if (empty($ibPartnerIds)) return [];
        $ibPartnerIds = array_values(array_unique(array_filter(array_map('intval', $ibPartnerIds))));
        if (empty($ibPartnerIds)) return [];

        $map = $this->getByIbPartnerIds($ibPartnerIds);
        foreach ($ibPartnerIds as $ibPartnerId) {
            if (!isset($map[$ibPartnerId])) {
                $row = $this->getOrCreateByIbPartnerId($ibPartnerId);
                if ($row) {
                    $map[$ibPartnerId] = $row;
                }
            }
        }

        return $map;
    }

    /**
     * 检查后缀是否被其他 IB 占用（排除指定 ibPartnerId）
     */
    public function isSuffixTakenByOther($suffix, $excludeIbPartnerId) {
        $suffix = trim((string)$suffix);
        if ($suffix === '') return false;
        $excludeIbPartnerId = (int)$excludeIbPartnerId;
        $row = $this->db->fetchOne(
            "SELECT ibPartnerId FROM {$this->table} WHERE referralSuffix = :suffix AND ibPartnerId != :excludeIbPartnerId LIMIT 1",
            ['suffix' => $suffix, 'excludeIbPartnerId' => $excludeIbPartnerId]
        );
        return $row !== false && $row !== null;
    }

    /**
     * 更新或插入：设置该 IB 的后缀
     */
    public function setSuffix($ibPartnerId, $suffix) {
        $ibPartnerId = (int)$ibPartnerId;
        $suffix = trim((string)$suffix);
        if ($ibPartnerId <= 0 || $suffix === '') return false;
        $existing = $this->getByIbPartnerId($ibPartnerId);
        if ($existing) {
            $this->db->query(
                "UPDATE {$this->table} SET referralSuffix = :suffix, updatedAt = NOW() WHERE ibPartnerId = :ibPartnerId",
                ['suffix' => $suffix, 'ibPartnerId' => $ibPartnerId]
            );
        } else {
            $this->db->query(
                "INSERT INTO {$this->table} (ibPartnerId, referralSuffix, urlClicks, registrationsCount, createdAt, updatedAt) VALUES (:ibPartnerId, :suffix, 0, 0, NOW(), NOW())",
                ['ibPartnerId' => $ibPartnerId, 'suffix' => $suffix]
            );
        }
        return true;
    }

    /**
     * 访问量 +1
     */
    public function incrementUrlClicks($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) return false;
        $this->db->query(
            "UPDATE {$this->table} SET urlClicks = urlClicks + 1, updatedAt = NOW() WHERE ibPartnerId = :ibPartnerId",
            ['ibPartnerId' => $ibPartnerId]
        );
        return true;
    }

    private function insertIfAbsent($ibPartnerId, $suffix) {
        $stmt = $this->db->query(
            "INSERT IGNORE INTO {$this->table} (ibPartnerId, referralSuffix, urlClicks, registrationsCount, createdAt, updatedAt) VALUES (:ibPartnerId, :suffix, 0, 0, NOW(), NOW())",
            ['ibPartnerId' => $ibPartnerId, 'suffix' => $suffix]
        );

        if ($stmt->rowCount() > 0) {
            return $this->getByIbPartnerId($ibPartnerId);
        }

        $existing = $this->getByIbPartnerId($ibPartnerId);
        return $existing ?: null;
    }

    /**
     * 注册数 +1
     */
    public function incrementRegistrationsCount($ibPartnerId) {
        $ibPartnerId = (int)$ibPartnerId;
        if ($ibPartnerId <= 0) return false;
        $this->db->query(
            "UPDATE {$this->table} SET registrationsCount = registrationsCount + 1, updatedAt = NOW() WHERE ibPartnerId = :ibPartnerId",
            ['ibPartnerId' => $ibPartnerId]
        );
        return true;
    }
}
