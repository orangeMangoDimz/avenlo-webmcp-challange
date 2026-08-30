<?php
/**
 * Transaction Limit Model
 * 对应表: transactionLimits
 */

require_once __DIR__ . '/BaseModel.php';

class TransactionLimit extends BaseModel {
    protected $table = 'transactionLimits';
    protected $primaryKey = 'id';
    protected $fillable = [
        'transactionType',
        'paymentType',
        'minimumAmount',
        'maximumAmount',
        'dailyLimit',
        'monthlyLimit',
        'isActive',
        'updatedBy'
    ];

    /**
     * 获取活跃的限额配置
     */
    public function getActiveLimits($transactionType = null, $paymentType = null) {
        $conditions = ['isActive' => 1];

        if ($transactionType) {
            $conditions['transactionType'] = $transactionType;
        }

        if ($paymentType) {
            $conditions['paymentType'] = $paymentType;
        }

        return $this->findAll($conditions);
    }

    /**
     * 获取指定类型的限额
     */
    public function getLimitForTransaction($transactionType, $paymentType) {
        // 先查找特定支付类型的限额
        $limit = $this->findOne([
            'transactionType' => $transactionType,
            'paymentType' => $paymentType,
            'isActive' => 1
        ]);

        // 如果没有，查找'all'类型的限额
        if (!$limit) {
            $limit = $this->findOne([
                'transactionType' => $transactionType,
                'paymentType' => 'all',
                'isActive' => 1
            ]);
        }

        return $limit;
    }

    /**
     * 验证交易金额是否在限额内
     */
    public function validateAmount($transactionType, $paymentType, $amount) {
        $limit = $this->getLimitForTransaction($transactionType, $paymentType);

        if (!$limit) {
            return ['valid' => true];
        }

        $errors = [];

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'limits' => $limit
        ];
    }

    /**
     * 验证交易金额是否在限额内（包含每日和每月限额）
     * @param string $transactionType - 交易类型：'deposit' 或 'withdrawal'
     * @param string $paymentType - 支付类型：'crypto', 'fiat', 'all'
     * @param float $amount - 交易金额
     * @param int $userId - 用户ID
     * @return array - ['valid' => bool, 'errors' => array, 'limits' => array]
     */
    public function validateAmountWithPeriodLimits($transactionType, $paymentType, $amount, $userId) {
        $limit = $this->getLimitForTransaction($transactionType, $paymentType);

        if (!$limit) {
            return ['valid' => true];
        }

        $errors = [];

        // 验证每日限额
        if ($limit['dailyLimit'] > 0) {
            $table = $transactionType === 'deposit' ? 'deposits' : 'withdrawals';
            $amountField = $transactionType === 'deposit' ? 'amount' : 'amount';

            $dailySql = "SELECT COALESCE(SUM({$amountField}), 0) as dailyTotal
                         FROM {$table}
                         WHERE userId = :userId
                         AND DATE(requestedAt) = CURDATE()";
            $dailyResult = $this->db->fetchOne($dailySql, ['userId' => $userId]);
            $dailyTotal = (float)($dailyResult['dailyTotal'] ?? 0);

            if ($dailyTotal + $amount > $limit['dailyLimit']) {
                $remaining = $limit['dailyLimit'] - $dailyTotal;
                $errors[] = "Daily deposit limit exceeded. Remaining limit: $" . number_format($remaining, 2);
            }
        }

        // 验证每月限额
        if ($limit['monthlyLimit'] > 0) {
            $table = $transactionType === 'deposit' ? 'deposits' : 'withdrawals';
            $amountField = $transactionType === 'deposit' ? 'amount' : 'amount';

            $monthlySql = "SELECT COALESCE(SUM({$amountField}), 0) as monthlyTotal
                           FROM {$table}
                           WHERE userId = :userId
                           AND YEAR(requestedAt) = YEAR(CURDATE())
                           AND MONTH(requestedAt) = MONTH(CURDATE())";
            $monthlyResult = $this->db->fetchOne($monthlySql, ['userId' => $userId]);
            $monthlyTotal = (float)($monthlyResult['monthlyTotal'] ?? 0);

            if ($monthlyTotal + $amount > $limit['monthlyLimit']) {
                $remaining = $limit['monthlyLimit'] - $monthlyTotal;
                $errors[] = "Monthly deposit limit exceeded. Remaining limit: $" . number_format($remaining, 2);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'limits' => $limit
        ];
    }
}
