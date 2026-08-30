<?php
/**
 * Trading Account External Account Model
 * 用于存储第三方交易平台返回的交易账号信息
 */

require_once __DIR__ . '/BaseModel.php';

class TradingAccountExternalAccount extends BaseModel {
    protected $table = 'tradingAccountExternalAccounts';
    protected $primaryKey = 'id';
    protected $fillable = [
        'tradingAccountId',
        'providerKey',
        'providerAccountId',
        'sysUserId',
        'platformBalance',
        'platformCredit',
        'groupId',
        'predefinedLogin',
        'status',
        'name',
        'color',
        'city',
        'state',
        'country',
        'zipCode',
        'phone',
        'address',
        'email',
        'idNumber',
        'leverage',
        'taxRate',
        'agentAccount',
        'comment',
        'allowChangePin',
        'isReadOnly',
        'sendReport',
        'masterPin',
        'investorPin',
        'phonePin',
        'isEnable',
        'enabledMark',
        'deleteMark',
        'creatorTime',
        'deleteTime',
        'deleteUserId',
        'rawResponse',
        'requestPayload'
    ];

    /**
     * 根据交易账户ID获取第三方账号信息
     */
    public function findByTradingAccount($tradingAccountId) {
        return $this->findOne(['tradingAccountId' => $tradingAccountId]);
    }

    /**
     * 根据用户ID获取活跃的第三方账号信息
     */
    public function findByUserId($userId) {
        $sql = "SELECT tea.*
                FROM `{$this->table}` tea
                INNER JOIN `tradingAccounts` ta ON ta.id = tea.tradingAccountId
                WHERE ta.userId = :userId
                AND ta.status = 'active'
                LIMIT 1";
        return $this->db->fetchOne($sql, ['userId' => $userId]);
    }

    /**
     * 根据用户ID获取该用户下所有第三方账号信息（含多平台）
     *
     * @param int $userId 用户ID
     * @return array
     */
    public function findAllByUserId($userId) {
        $sql = "SELECT tea.*
                FROM `{$this->table}` tea
                INNER JOIN `tradingAccounts` ta ON ta.id = tea.tradingAccountId
                WHERE ta.userId = :userId
                AND ta.status = 'active'
                ORDER BY tea.id ASC";
        return $this->db->fetchAll($sql, ['userId' => $userId]);
    }

    /**
     * 根据用户ID获取该用户下所有第三方账号信息（含平台信息）
     *
     * @param int $userId 用户ID
     * @return array
     */
    public function findAllByUserIdWithPlatform($userId) {
        $sql = "SELECT
                    tea.*,
                    ta.platformId,
                    ta.accountNumber,
                    ta.accountCurrency,
                    ta.accountType,
                    ta.assignedCommissionRuleId,
                    cr.ruleName AS assignedCommissionRuleName,
                    cr.ruleType AS assignedCommissionRuleType,
                    cr.fixed_amount AS assignedCommissionRuleFixedAmount,
                    cr.rebateAmount AS assignedCommissionRuleRebateAmount,
                    (
                        SELECT ib.id
                        FROM ib_partner_rules pr
                        INNER JOIN ibPartners ib ON ib.id = pr.ibPartnerId
                        WHERE pr.ruleId = ta.assignedCommissionRuleId
                          AND ib.userId = ta.userId
                        LIMIT 1
                    ) AS assignedCommissionIbPartnerId,
                    (
                        SELECT ib.ibCode
                        FROM ib_partner_rules pr
                        INNER JOIN ibPartners ib ON ib.id = pr.ibPartnerId
                        WHERE pr.ruleId = ta.assignedCommissionRuleId
                          AND ib.userId = ta.userId
                        LIMIT 1
                    ) AS assignedCommissionRuleIbCode,
                    tp.platformKey,
                    tp.displayName AS platformName
                FROM `{$this->table}` tea
                INNER JOIN `tradingAccounts` ta ON ta.id = tea.tradingAccountId
                INNER JOIN `tradingPlatforms` tp ON tp.id = ta.platformId
                LEFT JOIN `ibCommissionRules` cr ON cr.id = ta.assignedCommissionRuleId
                WHERE ta.userId = :userId
                AND ta.status = 'active'
                ORDER BY tea.id ASC";
        return $this->db->fetchAll($sql, ['userId' => $userId]);
    }

    /**
     * 根据用户ID获取financepro平台的第三方账号信息
     *
     * @param int $userId 用户ID
     * @return array|null
     */
    public function findFinanceProByUserId($userId) {
        $sql = "SELECT tea.*
                FROM `{$this->table}` tea
                INNER JOIN `tradingAccounts` ta ON ta.id = tea.tradingAccountId
                INNER JOIN `tradingPlatforms` tp ON tp.id = ta.platformId
                WHERE ta.userId = :userId
                AND ta.status = 'active'
                AND tp.platformKey = 'financepro'
                LIMIT 1";
        return $this->db->fetchOne($sql, ['userId' => $userId]);
    }

    /**
     * 取该用户在 FinancePro 上已存在的 SysUserId（用户级标识，再次开户需回传）。
     * 只挑已写入 sysUserId 的记录；交易账号状态不过滤——账号关掉了 FP 那边的 user 仍然存在。
     *
     * @param int $userId
     * @return string|null
     */
    public function findFinanceProSysUserIdByUserId($userId) {
        $sql = "SELECT tea.sysUserId
                FROM `{$this->table}` tea
                INNER JOIN `tradingAccounts` ta ON ta.id = tea.tradingAccountId
                INNER JOIN `tradingPlatforms` tp ON tp.id = ta.platformId
                WHERE ta.userId = :userId
                AND tp.platformKey = 'financepro'
                AND tea.sysUserId IS NOT NULL
                AND tea.sysUserId <> ''
                ORDER BY tea.id ASC
                LIMIT 1";
        $row = $this->db->fetchOne($sql, ['userId' => $userId]);
        return isset($row['sysUserId']) && $row['sysUserId'] !== '' ? (string)$row['sysUserId'] : null;
    }

    /**
     * 更新指定账户的余额
     *
     * @param string $providerAccountId 第三方账户ID
     * @param float $balance 余额
     * @return bool
     */
    public function updateBalance($providerAccountId, $balance) {
        $sql = "UPDATE `{$this->table}`
                SET platformBalance = :balance
                WHERE providerAccountId = :providerAccountId";
        try {
            $this->db->query($sql, [
                'providerAccountId' => $providerAccountId,
                'balance' => $balance
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Update balance failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 同步更新指定账户的 balance 与 credit。
     *
     * - 任一参数传 null 时跳过该字段，避免误把已知值覆盖成 NULL；
     * - 两个字段都为 null 时直接返回 true，不发起 UPDATE。
     *
     * @param string $providerAccountId 第三方账户ID
     * @param float|null $balance
     * @param float|null $credit
     * @return bool
     */
    public function updateBalanceAndCredit($providerAccountId, $balance, $credit) {
        $sets = [];
        $params = ['providerAccountId' => $providerAccountId];

        if ($balance !== null) {
            $sets[] = 'platformBalance = :balance';
            $params['balance'] = $balance;
        }
        if ($credit !== null) {
            $sets[] = 'platformCredit = :credit';
            $params['credit'] = $credit;
        }
        if (empty($sets)) {
            return true;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets)
            . " WHERE providerAccountId = :providerAccountId";
        try {
            $this->db->query($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log('Update balance/credit failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新指定账户的杠杆（本地快照，平台为准；同步也会覆盖）。
     *
     * @param string $providerAccountId 第三方账户ID
     * @param int $leverage 杠杆数值
     * @return bool
     */
    public function updateLeverage($providerAccountId, $leverage) {
        $sql = "UPDATE `{$this->table}`
                SET leverage = :leverage
                WHERE providerAccountId = :providerAccountId";
        try {
            $this->db->query($sql, [
                'providerAccountId' => $providerAccountId,
                'leverage' => $leverage
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Update leverage failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新指定账户的所属 group（本地快照，平台为准）。
     * groupId 存法随平台：MT5/MT4 存 tradingGroups.id，其它(FP)存 trading_id——由调用方决定传入值。
     *
     * @param string $providerAccountId 第三方账户ID
     * @param int $groupId
     * @return bool
     */
    public function updateGroupId($providerAccountId, $groupId) {
        $sql = "UPDATE `{$this->table}`
                SET groupId = :groupId
                WHERE providerAccountId = :providerAccountId";
        try {
            $this->db->query($sql, [
                'providerAccountId' => $providerAccountId,
                'groupId' => $groupId
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Update groupId failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取指定 providerAccountId 当前存储的 platformCredit 值。
     * 用于判断 MT5 credit 是否变化，决定是否触发流水同步。
     *
     * @param string $providerAccountId
     * @return float|null 没记录或 NULL 时返回 null
     */
    public function getStoredCredit($providerAccountId) {
        $sql = "SELECT `platformCredit` FROM `{$this->table}`
                WHERE providerAccountId = :providerAccountId LIMIT 1";
        try {
            $row = $this->db->fetchOne($sql, [
                'providerAccountId' => $providerAccountId,
            ]);
            if (!$row || !array_key_exists('platformCredit', $row) || $row['platformCredit'] === null) {
                return null;
            }
            return (float)$row['platformCredit'];
        } catch (Exception $e) {
            error_log('Get stored credit failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 根据 providerAccountId 取得本地 tradingAccountId。
     * 同步赠金流水时需要写 trading_account_id 字段。
     *
     * @param string $providerAccountId
     * @return int|null
     */
    public function getTradingAccountIdByProviderAccountId($providerAccountId) {
        $sql = "SELECT `tradingAccountId` FROM `{$this->table}`
                WHERE providerAccountId = :providerAccountId LIMIT 1";
        try {
            $row = $this->db->fetchOne($sql, [
                'providerAccountId' => $providerAccountId,
            ]);
            if (!$row || empty($row['tradingAccountId'])) {
                return null;
            }
            return (int)$row['tradingAccountId'];
        } catch (Exception $e) {
            error_log('Lookup tradingAccountId failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 根据 providerAccountId 更新 MT5 账户资料字段。
     *
     * @param string $providerAccountId
     * @param array $fields
     * @return bool
     */
    public function updateProfileByProviderAccountId($providerAccountId, array $fields) {
        $record = $this->findByProviderAccountId($providerAccountId);
        if (!$record || empty($record['id'])) {
            return false;
        }

        $payload = [];
        foreach (['leverage', 'groupId', 'name', 'email', 'country', 'phone', 'comment', 'rawResponse'] as $field) {
            if (array_key_exists($field, $fields)) {
                $payload[$field] = $fields[$field];
            }
        }

        if (empty($payload)) {
            return true;
        }

        try {
            $this->update((int)$record['id'], $payload);
            return true;
        } catch (Exception $e) {
            error_log('Update external account profile failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量更新余额
     *
     * @param array $balances 余额数组，格式：[['providerAccountId' => 'xxx', 'balance' => 100.00], ...]
     * @return int 更新的记录数
     */
    public function batchUpdateBalances($balances) {
        if (empty($balances)) {
            return 0;
        }

        $updated = 0;
        foreach ($balances as $item) {
            if (isset($item['providerAccountId']) && isset($item['balance'])) {
                if ($this->updateBalance($item['providerAccountId'], $item['balance'])) {
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /**
     * 根据 providerAccountId 查找记录
     *
     * @param string $providerAccountId 第三方账户ID
     * @return array|null
     */
    public function findByProviderAccountId($providerAccountId) {
        return $this->findOne(['providerAccountId' => $providerAccountId]);
    }

    /**
     * 根据 providerAccountId 查找对应的 userId（client id）
     *
     * @param string $providerAccountId 第三方账户ID
     * @return array|null 返回包含 userId 和 tradingAccountId 的数组，如果未找到返回 null
     */
    public function findUserIdByProviderAccountId($providerAccountId) {
        $sql = "SELECT ta.userId, ta.id as tradingAccountId, ta.status as tradingAccountStatus
                FROM `{$this->table}` tea
                INNER JOIN `tradingAccounts` ta ON ta.id = tea.tradingAccountId
                WHERE tea.providerAccountId = :providerAccountId
                LIMIT 1";
        return $this->db->fetchOne($sql, ['providerAccountId' => $providerAccountId]);
    }
}
