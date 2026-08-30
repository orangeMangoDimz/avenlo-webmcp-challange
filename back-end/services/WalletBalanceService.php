<?php
/**
 * Wallet Balance Service
 * 统一计算 CRM wallet 可用余额，避免各处公式不一致。
 * 公式：入金 − 出金 + 佣金 − 钱包转出交易账户。
 */

require_once __DIR__ . '/../utils/Database.php';

class WalletBalanceService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getBreakdown($userId): array {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw new InvalidArgumentException('User id is required');
        }

        $depositResult = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS totalDeposits
             FROM deposits
             WHERE userId = :userId
               AND status = 'completed'
               AND tradingAccountId IS NULL
               AND countsToBalance = 1",
            ['userId' => $userId]
        );
        $totalDeposits = (float)($depositResult['totalDeposits'] ?? 0);

        $withdrawalResult = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS totalWithdrawals
             FROM withdrawals
             WHERE userId = :userId
               AND status IN ('pending', 'processing', 'completed')
               AND tradingAccountId IS NULL
               AND countsToBalance = 1",
            ['userId' => $userId]
        );
        $totalWithdrawals = (float)($withdrawalResult['totalWithdrawals'] ?? 0);

        $totalCompletedCommission = 0.0;
        $ibRow = $this->db->fetchOne(
            'SELECT id FROM ibPartners WHERE userId = :userId LIMIT 1',
            ['userId' => $userId]
        );
        if ($ibRow && !empty($ibRow['id'])) {
            $commissionResult = $this->db->fetchOne(
                "SELECT COALESCE(SUM(commission), 0) AS total
                 FROM ib_commission_order
                 WHERE ibPartnerId = :ibPartnerId
                   AND status = 'completed'
                   AND countsToBalance = 1",
                ['ibPartnerId' => (int)$ibRow['id']]
            );
            $totalCompletedCommission = (float)($commissionResult['total'] ?? 0);
        }

        $walletToTradingResult = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM internalTransfers
             WHERE userId = :userId
               AND (fromType = 'wallet' OR fromType = 'available_balance')
               AND status IN ('pending', 'processing', 'completed')
               AND countsToBalance = 1",
            ['userId' => $userId]
        );
        $walletToTradingAmount = (float)($walletToTradingResult['total'] ?? 0);

        return [
            'totalDeposits' => $totalDeposits,
            'totalWithdrawals' => $totalWithdrawals,
            'totalCompletedCommission' => $totalCompletedCommission,
            'walletToTradingAmount' => $walletToTradingAmount,
            'availableBalance' => $totalDeposits - $totalWithdrawals + $totalCompletedCommission - $walletToTradingAmount,
        ];
    }

    public function getAvailableBalance($userId): float {
        $breakdown = $this->getBreakdown($userId);
        return (float)$breakdown['availableBalance'];
    }
}
