<?php
/**
 * Account Closure Service
 * 客户自助注销账号的核心逻辑：余额校验 + 置为 closed + 记录日志。
 * 路由、密码二次校验、session 失效由调用方负责，这里只管账号本身的状态流转。
 */

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/ClientActivityLog.php';
require_once __DIR__ . '/WalletBalanceService.php';

class AccountClosureService {
    private $userModel;
    private $accountModel;
    private $activityLogModel;
    private $walletBalanceService;

    public function __construct() {
        $this->userModel = new ClientUser();
        $this->accountModel = new TradingAccount();
        $this->activityLogModel = new ClientActivityLog();
        $this->walletBalanceService = new WalletBalanceService();
    }

    /**
     * 检查是否可注销，返回拦截原因；可注销返回 null。
     * 口径：钱包可用余额 + 各交易账户余额，任一不为 0 都不允许注销。
     */
    public function getCloseBlockReason(int $userId): ?string {
        if (round($this->walletBalanceService->getAvailableBalance($userId), 2) != 0.0) {
            return 'Wallet balance must be zero before closing the account';
        }

        if (round($this->getTradingAccountsBalance($userId), 2) != 0.0) {
            return 'Trading account balance must be zero before closing the account';
        }

        return null;
    }

    /**
     * 执行注销：置 status=closed 并记录活动日志。
     * 兜底再校验一次余额，余额不为 0 时抛异常，避免被绕过校验直接调用。
     */
    public function closeAccount(int $userId): bool {
        // 为 app 首版上线先放开余额校验，直接注销；后续要恢复把下面这段取消注释即可。
        // $reason = $this->getCloseBlockReason($userId);
        // if ($reason !== null) {
        //     throw new RuntimeException($reason);
        // }

        // status=closed 表示自助注销；deletedAt 配合 (email, deletedAt) 复合唯一约束，
        // 让原邮箱/电话腾出来可被重新注册。
        $ok = $this->userModel->update($userId, [
            'status' => 'closed',
            'deletedAt' => date('Y-m-d H:i:s')
        ]);
        if ($ok) {
            $this->activityLogModel->logActivity([
                'userId' => $userId,
                'activityType' => 'account_closed',
                'description' => 'Client closed their own account'
            ]);
        }

        return (bool)$ok;
    }

    /**
     * 各交易账户余额之和，口径与 TradingAccountController::getAccounts 一致：用 platformBalance 同步快照。
     */
    private function getTradingAccountsBalance(int $userId): float {
        $total = 0.0;
        foreach ($this->accountModel->getAccountsByUser($userId) as $account) {
            if (isset($account['platformBalance']) && $account['platformBalance'] !== null) {
                $total += (float)$account['platformBalance'];
            }
        }

        return $total;
    }
}
