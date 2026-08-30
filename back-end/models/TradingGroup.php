<?php
/**
 * 交易平台组别模型
 * 管理从交易平台同步的组别信息
 */

require_once __DIR__ . '/BaseModel.php';

class TradingGroup extends BaseModel {
    protected $table = 'trading_group';
    protected $primaryKey = 'id';

    protected $fillable = [
        'trading_id', 'trading_platforms_key', 'name', 'label', 'enable', 'enabledMark',
        'depositByDefault', 'depositCurrency', 'leverageByDefault', 'leverageDisplay',
        'unit', 'scale',
        'annualInterestRate', 'accountTag', 'owner', 'supportPage', 'creatorTime',
        'marginCallLevel', 'stopOutLevel', 'stopOutLevelUnit', 'stopOutLevelUnitDisplay',
        'skipFullyHedged', 'isDefault'
    ];

    protected $hidden = [];

    /**
     * 空字符串转 null，避免写入 DECIMAL/INT/DATETIME 时报 1366
     */
    private function nullIfEmpty($value) {
        return ($value === null || $value === '') ? null : $value;
    }

    /**
     * 将倍率值规范为正数；空值时返回默认值 1。
     */
    private function normalizePositiveDecimal($value, $default = 1.0) {
        if ($value === null || $value === '') {
            return (float)$default;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Trading group unit/scale must be numeric.');
        }

        $normalized = (float)$value;
        if ($normalized <= 0) {
            throw new InvalidArgumentException('Trading group unit/scale must be greater than 0.');
        }

        return $normalized;
    }

    /**
     * 展示单位允许为空；空字符串按 null 存储。
     */
    private function normalizeDisplayUnit($value) {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);
        return $normalized === '' ? null : $normalized;
    }

    /**
     * 根据交易平台和交易ID查找
     */
    public function findByTradingId($tradingId, $platformKey) {
        return $this->findOne([
            'trading_id' => $tradingId,
            'trading_platforms_key' => $platformKey
        ]);
    }

    /**
     * 根据平台和本地主键查找。
     */
    public function findByPlatformAndId($platformKey, $id) {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $group = $this->findById($id);
        if (!$group || ($group['trading_platforms_key'] ?? null) !== $platformKey) {
            return null;
        }

        return $group;
    }

    /**
     * 根据平台和组名查找（MT5 等字符串组名场景）
     */
    public function findByPlatformAndName($platformKey, $name) {
        return $this->findOne([
            'trading_platforms_key' => $platformKey,
            'name' => $name
        ]);
    }

    /**
     * 获取指定平台的所有组别
     */
    public function getByPlatform($platformKey) {
        return $this->findAll(['trading_platforms_key' => $platformKey], 'name ASC');
    }

    public function updateLabel($groupId, $label) {
        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            throw new InvalidArgumentException('Invalid trading group id.');
        }

        $group = $this->findById($groupId);
        if (!$group) {
            throw new InvalidArgumentException('Trading group not found.');
        }

        $normalizedLabel = trim((string)$label);
        if ($normalizedLabel === '') {
            $normalizedLabel = (string)($group['name'] ?? '');
        }

        $this->update($groupId, ['label' => $normalizedLabel]);
        return $this->findById($groupId);
    }

    /**
     * 更新组别的金额单位配置。
     */
    public function updateAmountConfig($groupId, array $config) {
        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            throw new InvalidArgumentException('Invalid trading group id.');
        }

        $group = $this->findById($groupId);
        if (!$group) {
            throw new InvalidArgumentException('Trading group not found.');
        }

        $payload = [];
        if (array_key_exists('unit', $config)) {
            $payload['unit'] = $this->normalizeDisplayUnit($config['unit']);
        }
        if (array_key_exists('scale', $config)) {
            $payload['scale'] = $this->normalizePositiveDecimal($config['scale']);
        }

        if (empty($payload)) {
            throw new InvalidArgumentException('At least one of unit or scale is required.');
        }

        $this->update($groupId, $payload);
        return $this->findById($groupId);
    }

    /**
     * 获取默认组别列表
     */
    public function getDefaultGroups($platformKey = null) {
        $conditions = ['isDefault' => 1];
        if ($platformKey) {
            $conditions['trading_platforms_key'] = $platformKey;
        }
        return $this->findAll($conditions, 'trading_platforms_key ASC, name ASC');
    }

    /**
     * 获取默认组别
     * 为兼容旧调用，返回默认组列表中的第一条。
     */
    public function getDefaultGroup($platformKey = null) {
        $groups = $this->getDefaultGroups($platformKey);
        return !empty($groups) ? $groups[0] : null;
    }

    /**
     * 按 id 列表获取交易组（可选过滤平台）。
     * 用于按 IB 配置的组别（ib_partner_trading_groups）查询。
     */
    public function getByIds(array $ids, $platformKey = null) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
            return $v > 0;
        })));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE id IN ({$placeholders})";
        $params = $ids;

        if ($platformKey !== null && $platformKey !== '') {
            $sql .= " AND trading_platforms_key = ?";
            $params[] = $platformKey;
        }

        $sql .= " ORDER BY trading_platforms_key ASC, name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    private function validateGroupForPlatform($groupId, $platformKey) {
        if (empty($platformKey)) {
            throw new InvalidArgumentException('platformKey is required.');
        }

        $groupId = (int)$groupId;
        if ($groupId <= 0) {
            throw new InvalidArgumentException('Invalid trading group id.');
        }

        $group = $this->findById($groupId);
        if (!$group) {
            throw new InvalidArgumentException('Trading group not found.');
        }
        if ($group['trading_platforms_key'] !== $platformKey) {
            throw new InvalidArgumentException('Group does not belong to the specified platform.');
        }

        return $group;
    }

    /**
     * 设置默认组别（允许同平台多个默认组别）
     * 仅将当前组别标记为默认，不影响同平台其他组别。
     */
    public function setDefault($groupId, $platformKey) {
        $this->validateGroupForPlatform($groupId, $platformKey);
        return $this->update((int)$groupId, ['isDefault' => 1]);
    }

    /**
     * 取消默认组别
     */
    public function removeDefault($groupId, $platformKey) {
        $this->validateGroupForPlatform($groupId, $platformKey);
        return $this->update((int)$groupId, ['isDefault' => 0]);
    }

    /**
     * 批量同步组别数据
     * 所有数值/DECIMAL/DATETIME 可空字段：空字符串转为 null，避免 MySQL 1366
     */
    public function syncGroups($platformKey, $groups) {
        $synced = 0;
        $updated = 0;

        foreach ($groups as $groupData) {
            // 查找是否已存在
            $existing = $this->findByTradingId($groupData['Id'], $platformKey);

            // 可空数值/小数：空字符串必须转 null，否则 MySQL 报 Incorrect decimal/value
            $depositByDefault = $groupData['DepositByDefault'] ?? null;
            $leverageByDefault = $groupData['LeverageByDefault'] ?? null;
            $annualInterestRate = $groupData['AnnualInterestRate'] ?? null;

            $data = [
                'trading_id' => (int)$groupData['Id'],
                'trading_platforms_key' => $platformKey,
                'name' => (string)($groupData['Name'] ?? ''),
                'label' => !empty($existing['label']) ? (string)$existing['label'] : (string)($groupData['Name'] ?? ''),
                'enable' => (int)(isset($groupData['Enable']) ? $groupData['Enable'] : 1),
                'enabledMark' => !empty($groupData['EnabledMark']) ? 1 : 0,
                'depositByDefault' => ($depositByDefault !== null && $depositByDefault !== '') ? (float)$depositByDefault : null,
                'depositCurrency' => $this->nullIfEmpty($groupData['DepositCurrency'] ?? null),
                'leverageByDefault' => ($leverageByDefault !== null && $leverageByDefault !== '') ? (int)$leverageByDefault : null,
                'leverageDisplay' => $this->nullIfEmpty($groupData['LeverageDisplay'] ?? null),
                'unit' => $existing ? $this->normalizeDisplayUnit($existing['unit'] ?? null) : null,
                'scale' => $existing ? $this->normalizePositiveDecimal($existing['scale'] ?? 1) : 1.0,
                'annualInterestRate' => ($annualInterestRate !== null && $annualInterestRate !== '') ? (float)$annualInterestRate : null,
                'accountTag' => $this->nullIfEmpty($groupData['AccountTag'] ?? null),
                'owner' => $this->nullIfEmpty($groupData['Owner'] ?? null),
                'supportPage' => $this->nullIfEmpty($groupData['SupportPage'] ?? null),
                'creatorTime' => $this->nullIfEmpty($groupData['CreatorTime'] ?? null),
            ];

            // 处理 MarginInfo（数值/可空字段空串转 null，TINYINT 用 0/1）
            if (isset($groupData['MarginInfo']) && is_array($groupData['MarginInfo'])) {
                $marginInfo = $groupData['MarginInfo'];
                $data['marginCallLevel'] = $this->nullIfEmpty($marginInfo['MarginCallLevel'] ?? null);
                $data['stopOutLevel'] = $this->nullIfEmpty($marginInfo['StopOutLevel'] ?? null);
                $su = $marginInfo['StopOutLevelUnit'] ?? null;
                $data['stopOutLevelUnit'] = ($su !== null && $su !== '') ? (int)$su : null;
                $data['stopOutLevelUnitDisplay'] = $this->nullIfEmpty($marginInfo['StopOutLevelUnitDisplay'] ?? null);
                $data['skipFullyHedged'] = isset($marginInfo['SkipFullyHedged']) ? (!empty($marginInfo['SkipFullyHedged']) ? 1 : 0) : null;
            }

            if ($existing) {
                // 更新现有记录（保留 isDefault 状态）；必须用 0/1 整数，避免 PDO 将 boolean false 绑定为 '' 导致 MySQL 报错
                $data['isDefault'] = !empty($existing['isDefault']) ? 1 : 0;
                $this->update($existing['id'], $data);
                $updated++;
            } else {
                // 创建新记录
                $data['isDefault'] = 0;
                $this->create($data);
                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'total' => count($groups)
        ];
    }

    /**
     * 同步 MT5 组别数据（MT5 组标识为字符串 Group）
     *
     * 说明：
     * - 现有表 trading_group.trading_id 为 int（原本按 FinancePro 设计）
     * - MT5 的组主标识是字符串 Group，因此这里按 name 作为逻辑唯一键同步
     * - trading_id 使用稳定 hash（crc32）生成，避免改表阻塞接入
     */
    public function syncMt5Groups($groups) {
        $platformKey = 'mt5';
        $synced = 0;
        $updated = 0;

        foreach ($groups as $groupData) {
            $groupName = trim((string)($groupData['Group'] ?? ''));
            if ($groupName === '') {
                continue;
            }

            $existing = $this->findByPlatformAndName($platformKey, $groupName);
            $tradingId = $existing ? (int)$existing['trading_id'] : $this->allocateMt5TradingId($groupName, $platformKey);

            $authPasswordMin = isset($groupData['AuthPasswordMin']) ? (int)$groupData['AuthPasswordMin'] : null;
            $demoLeverage = isset($groupData['DemoLeverage']) ? (int)$groupData['DemoLeverage'] : null;
            $demoDeposit = isset($groupData['DemoDeposit']) ? $groupData['DemoDeposit'] : null;
            $marginCall = isset($groupData['MarginCall']) ? $groupData['MarginCall'] : null;
            $marginStopOut = isset($groupData['MarginStopOut']) ? $groupData['MarginStopOut'] : null;

            $data = [
                'trading_id' => $tradingId,
                'trading_platforms_key' => $platformKey,
                'name' => $groupName,
                'label' => !empty($existing['label']) ? (string)$existing['label'] : $groupName,
                'enable' => 1,
                'enabledMark' => 1,
                'depositByDefault' => ($demoDeposit !== null && $demoDeposit !== '') ? (float)$demoDeposit : null,
                'depositCurrency' => $this->nullIfEmpty($groupData['Currency'] ?? null),
                'leverageByDefault' => ($demoLeverage !== null && $demoLeverage !== '') ? $demoLeverage : null,
                'leverageDisplay' => ($demoLeverage !== null && $demoLeverage > 0) ? ('1:' . $demoLeverage) : null,
                'unit' => $existing ? $this->normalizeDisplayUnit($existing['unit'] ?? null) : null,
                'scale' => $existing ? $this->normalizePositiveDecimal($existing['scale'] ?? 1) : 1.0,
                'annualInterestRate' => isset($groupData['TradeInterestrate']) && $groupData['TradeInterestrate'] !== '' ? (float)$groupData['TradeInterestrate'] : null,
                'accountTag' => $this->nullIfEmpty($groupData['CompanyCatalog'] ?? null),
                'owner' => $this->nullIfEmpty($groupData['Company'] ?? null),
                'supportPage' => $this->nullIfEmpty($groupData['CompanySupportPage'] ?? ($groupData['CompanyPage'] ?? null)),
                'creatorTime' => null,
                'marginCallLevel' => ($marginCall !== null && $marginCall !== '') ? (string)$marginCall : null,
                'stopOutLevel' => ($marginStopOut !== null && $marginStopOut !== '') ? (string)$marginStopOut : null,
                'stopOutLevelUnit' => isset($groupData['MarginSOMode']) ? (int)$groupData['MarginSOMode'] : null,
                'stopOutLevelUnitDisplay' => isset($groupData['MarginSOMode']) ? 'MT5' : null,
                'skipFullyHedged' => null,
                'isDefault' => $existing ? (!empty($existing['isDefault']) ? 1 : 0) : 0,
            ];

            // 把 MT5 组密码最小长度放到 comment-like 可用字段里会污染语义，这里借 accountTag 不合适，故仅保留在 raw source（当前表无 raw 字段）
            // 如后续开户需要 AuthPasswordMin，建议直接从 MT5 实时读取或给 trading_group 增加专用字段。
            // 目前同步不存 AuthPasswordMin，但保留本地可继续扩展。

            if ($authPasswordMin !== null && $authPasswordMin > 0 && empty($data['accountTag'])) {
                $data['accountTag'] = 'AuthPasswordMin:' . $authPasswordMin;
            }

            if ($existing) {
                $this->update($existing['id'], $data);
                $updated++;
            } else {
                $this->create($data);
                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'total' => count($groups)
        ];
    }

    /**
     * 同步 MT4 组别数据。
     *
     * MT4 网关组主标识也是字符串 name（含反斜杠，如 real\USD），与 MT5 同样按 name 作为逻辑唯一键，
     * trading_id 复用 allocateMt5TradingId 的 crc32 稳定分配。字段为网关 snake_case（文档 5.7 / 6.4）。
     */
    public function syncMt4Groups($groups) {
        $platformKey = 'mt4';
        $synced = 0;
        $updated = 0;

        foreach ($groups as $groupData) {
            $groupName = trim((string)($groupData['name'] ?? ''));
            if ($groupName === '') {
                continue;
            }

            $existing = $this->findByPlatformAndName($platformKey, $groupName);
            $tradingId = $existing ? (int)$existing['trading_id'] : $this->allocateMt5TradingId($groupName, $platformKey);

            $defaultLeverage = isset($groupData['default_leverage']) ? (int)$groupData['default_leverage'] : null;
            $defaultDeposit = $groupData['demo_deposit'] ?? null;
            $marginCall = $groupData['margin_call'] ?? null;
            $marginStopout = $groupData['margin_stopout'] ?? null;
            $interestRate = $groupData['interest_rate'] ?? null;
            $supportPage = $groupData['support_page'] ?? ($groupData['support_email'] ?? null);

            $data = [
                'trading_id' => $tradingId,
                'trading_platforms_key' => $platformKey,
                'name' => $groupName,
                'label' => !empty($existing['label']) ? (string)$existing['label'] : $groupName,
                'enable' => (int)(isset($groupData['enable']) ? $groupData['enable'] : 1),
                'enabledMark' => 1,
                'depositByDefault' => ($defaultDeposit !== null && $defaultDeposit !== '') ? (float)$defaultDeposit : null,
                'depositCurrency' => $this->nullIfEmpty($groupData['currency'] ?? null),
                'leverageByDefault' => ($defaultLeverage !== null && $defaultLeverage > 0) ? $defaultLeverage : null,
                'leverageDisplay' => ($defaultLeverage !== null && $defaultLeverage > 0) ? ('1:' . $defaultLeverage) : null,
                'unit' => $existing ? $this->normalizeDisplayUnit($existing['unit'] ?? null) : null,
                'scale' => $existing ? $this->normalizePositiveDecimal($existing['scale'] ?? 1) : 1.0,
                'annualInterestRate' => ($interestRate !== null && $interestRate !== '') ? (float)$interestRate : null,
                'accountTag' => $this->nullIfEmpty($groupData['company'] ?? null),
                'owner' => $this->nullIfEmpty($groupData['company'] ?? null),
                'supportPage' => $this->nullIfEmpty($supportPage),
                'creatorTime' => null,
                'marginCallLevel' => ($marginCall !== null && $marginCall !== '') ? (string)$marginCall : null,
                'stopOutLevel' => ($marginStopout !== null && $marginStopout !== '') ? (string)$marginStopout : null,
                'stopOutLevelUnit' => null,
                'stopOutLevelUnitDisplay' => $this->nullIfEmpty($groupData['margin_type'] ?? null),
                'skipFullyHedged' => null,
                'isDefault' => $existing ? (!empty($existing['isDefault']) ? 1 : 0) : 0,
            ];

            if ($existing) {
                $this->update($existing['id'], $data);
                $updated++;
            } else {
                $this->create($data);
                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'total' => count($groups)
        ];
    }

    private function allocateMt5TradingId($groupName, $platformKey) {
        // trading_group.trading_id 是 MySQL INT，避免超过 2147483647 被截断成同一个值导致唯一键冲突
        $min = 1;
        $max = 2147483646;
        $range = $max - $min + 1;
        $unsignedHash = (int)sprintf('%u', crc32($groupName));
        $base = ($unsignedHash % $range) + $min;

        $candidate = $base;
        $attempts = 0;
        $maxAttempts = 10000;
        while ($attempts < $maxAttempts) {
            $existing = $this->findByTradingId($candidate, $platformKey);
            if (!$existing) {
                return $candidate;
            }
            if (($existing['name'] ?? '') === $groupName) {
                return (int)$existing['trading_id'];
            }
            $candidate++;
            if ($candidate > $max) {
                $candidate = $min;
            }
            $attempts++;
        }

        throw new RuntimeException('Failed to allocate synthetic trading_id for MT5 group: ' . $groupName);
    }

    /**
     * 获取所有启用的组别
     */
    public function getEnabledGroups($platformKey = null) {
        $conditions = ['enable' => 1];
        if ($platformKey) {
            $conditions['trading_platforms_key'] = $platformKey;
        }
        return $this->findAll($conditions, 'name ASC');
    }
}
