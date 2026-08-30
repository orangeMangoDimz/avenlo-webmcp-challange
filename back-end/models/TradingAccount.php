<?php
/**
 * Trading Account Model
 * 对应表: tradingAccounts
 */

require_once __DIR__ . '/BaseModel.php';

class TradingAccount extends BaseModel {
    protected $table = 'tradingAccounts';
    protected $primaryKey = 'id';
    protected $fillable = [
        'userId',
        'platformId',
        'accountNumber',
        'accountNickname',
        'accountCurrency',
        'accountType',
        'assignedCommissionRuleId',
        'leverageValue',
        'initialDeposit',
        'predefinedLogin',
        'investorPinEncrypted',
        'status',
        'emailSent',
        'emailSentAt'
    ];

    /**
     * 获取用户的所有交易账户（包含平台信息和余额）
     */
    public function getAccountsByUser($userId) {
        $sql = "SELECT ta.*,
                       tp.displayName AS platformName,
                       tp.shortCode AS platformCode,
                       tp.platformKey,
                       tea.providerAccountId,
                       tea.platformBalance,
                       tea.platformCredit,
                       tea.leverage AS externalLeverage,
                       tea.groupId AS externalGroupId,
                       tg.id AS groupId,
                       tg.name AS groupName,
                       tg.label AS groupLabel,
                       tg.trading_id AS groupTradingId,
                       tg.depositByDefault AS groupDepositByDefault,
                       tg.depositCurrency AS groupDepositCurrency,
                       tg.leverageByDefault AS groupLeverageByDefault,
                       tg.leverageDisplay AS groupLeverageDisplay,
                       tg.unit AS groupUnit,
                       tg.scale AS groupScale
                FROM {$this->table} AS ta
                INNER JOIN tradingPlatforms AS tp ON tp.id = ta.platformId
                LEFT JOIN tradingAccountExternalAccounts AS tea ON tea.tradingAccountId = ta.id
                LEFT JOIN trading_group AS tg
                    ON tg.trading_platforms_key = tp.platformKey
                   AND (
                        -- mt4/mt5 存本地 trading_group.id；只有 financepro 存 FP 的 trading_id
                        (tp.platformKey IN ('mt5', 'mt4') AND tg.id = tea.groupId)
                        OR
                        (tp.platformKey NOT IN ('mt5', 'mt4') AND tg.trading_id = tea.groupId)
                   )
                WHERE ta.userId = :userId
                ORDER BY ta.createdAt DESC";
        return $this->query($sql, ['userId' => $userId]);
    }

    /**
     * 根据账号查找记录
     */
    public function findByAccountNumber($accountNumber) {
        return $this->findOne(['accountNumber' => $accountNumber]);
    }

    /**
     * 更新邮件发送状态
     */
    public function markEmailSent($accountId) {
        return $this->update($accountId, [
            'emailSent' => 1,
            'emailSentAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 统计用户在指定平台的活跃账户数量
     *
     * @param int $userId 用户ID
     * @param int $platformId 平台ID
     * @return int
     */
    public function countActiveAccountsOnPlatform($userId, $platformId) {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE userId = :userId AND platformId = :platformId AND status = 'active'";
        $result = $this->db->fetchOne($sql, [
            'userId' => $userId,
            'platformId' => $platformId
        ]);
        return isset($result['count']) ? (int)$result['count'] : 0;
    }

    /**
     * 检查用户是否在指定平台已有账户
     *
     * @param int $userId 用户ID
     * @param int $platformId 平台ID
     * @return bool
     */
    public function hasAccountOnPlatform($userId, $platformId) {
        return $this->countActiveAccountsOnPlatform($userId, $platformId) > 0;
    }

    /**
     * 检查用户是否有financepro平台的账户
     *
     * @param int $userId 用户ID
     * @return bool
     */
    public function hasFinanceProAccount($userId) {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table} AS ta
                INNER JOIN tradingPlatforms AS tp ON tp.id = ta.platformId
                WHERE ta.userId = :userId
                AND tp.platformKey = 'financepro'
                AND ta.status = 'active'";
        $result = $this->db->fetchOne($sql, ['userId' => $userId]);
        return isset($result['count']) && $result['count'] > 0;
    }

    /**
     * 获取用户的financepro账户
     *
     * @param int $userId 用户ID
     * @return array|null
     */
    public function getFinanceProAccount($userId) {
        $sql = "SELECT ta.*
                FROM {$this->table} AS ta
                INNER JOIN tradingPlatforms AS tp ON tp.id = ta.platformId
                WHERE ta.userId = :userId
                AND tp.platformKey = 'financepro'
                AND ta.status = 'active'
                ORDER BY ta.createdAt DESC
                LIMIT 1";
        return $this->db->fetchOne($sql, ['userId' => $userId]);
    }
}
