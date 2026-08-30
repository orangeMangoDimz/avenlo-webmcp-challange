<?php
/**
 * Payment Gateway Funding Setting Model
 * 对应表: paymentGatewayFundingSettings
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/PaymentGatewayFeeRule.php';
require_once __DIR__ . '/PaymentGatewayQuickAmount.php';

class PaymentGatewayFundingSetting extends BaseModel {
    protected $table = 'paymentGatewayFundingSettings';
    protected $primaryKey = 'id';
    protected $fillable = [
        'gatewaySettingId',
        'calculationMode',
        'minDeposit',
        'maxDeposit',
        'minWithdrawal',
        'maxWithdrawal',
        'isActive',
        'notes',
        'updatedBy'
    ];
    private $feeRuleModel;
    private $quickAmountModel;

    public function __construct() {
        parent::__construct();
        $this->feeRuleModel = new PaymentGatewayFeeRule();
        $this->quickAmountModel = new PaymentGatewayQuickAmount();
    }

    protected function hideFields($data) {
        $data = parent::hideFields($data);

        if (empty($data)) {
            return $data;
        }

        if (isset($data[$this->primaryKey])) {
            return $this->normalizeFundingConfigRecord($data);
        }

        if (is_array($data)) {
            foreach ($data as &$record) {
                if (is_array($record) && isset($record[$this->primaryKey])) {
                    $record = $this->normalizeFundingConfigRecord($record);
                }
            }
            unset($record);
        }

        return $data;
    }

    public function getAllWithGateways($isActive = null) {
        $sql = "SELECT pgfs.*, pgs.gatewayKey, pgs.gatewayName
                FROM {$this->table} pgfs
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = pgfs.gatewaySettingId
                WHERE pgs.deletedAt IS NULL";

        $params = [];
        if ($isActive !== null) {
            $sql .= " AND pgfs.isActive = :isActive";
            $params['isActive'] = $isActive ? 1 : 0;
        }

        $sql .= " ORDER BY pgs.gatewayName ASC, pgfs.id ASC";

        $records = $this->hideFields($this->db->fetchAll($sql, $params));
        foreach ($records as &$record) {
            $record = $this->attachFeeRules($record, $isActive === true);
        }
        unset($record);

        return $records;
    }

    public function getByGatewaySettingId($gatewaySettingId, $onlyActive = false) {
        $sql = "SELECT pgfs.*, pgs.gatewayKey, pgs.gatewayName
                FROM {$this->table} pgfs
                INNER JOIN paymentGatewaySettings pgs ON pgs.id = pgfs.gatewaySettingId
                WHERE pgfs.gatewaySettingId = :gatewaySettingId
                  AND pgs.deletedAt IS NULL";

        $params = ['gatewaySettingId' => (int)$gatewaySettingId];
        if ($onlyActive) {
            $sql .= " AND pgfs.isActive = 1";
        }

        $sql .= " LIMIT 1";

        $result = $this->db->fetchOne($sql, $params);
        if (!$result) {
            return null;
        }

        $result = $this->hideFields($result);
        return $this->attachFeeRules($result, $onlyActive);
    }

    public function upsertByGatewaySettingId($gatewaySettingId, $data) {
        $existing = $this->findOne(['gatewaySettingId' => (int)$gatewaySettingId]);

        if ($existing) {
            $this->update((int)$existing['id'], $data);
            return (int)$existing['id'];
        }

        $data['gatewaySettingId'] = (int)$gatewaySettingId;
        $id = (int)$this->create($data);
        $this->quickAmountModel->seedDefaults((int)$gatewaySettingId);
        return $id;
    }

    public function calculateForTransaction($gatewaySettingId, $transactionType, $amount) {
        $fundingSettings = $this->getByGatewaySettingId((int)$gatewaySettingId, true);
        $amount = (float)$amount;

        if (!$fundingSettings) {
            return [
                'platformFee' => 0.0,
                'netAmount' => round($amount, 2),
                'feeSettings' => null
            ];
        }

        $rule = $this->resolveFeeRule($fundingSettings, $transactionType, $amount);
        $mode = $rule['mode'] ?? 'none';
        $percentage = (float)($rule['percentage'] ?? 0);
        $fixed = (float)($rule['fixed'] ?? 0);
        $minFee = array_key_exists('minFee', $rule) && $rule['minFee'] !== null ? (float)$rule['minFee'] : null;
        $maxFee = array_key_exists('maxFee', $rule) && $rule['maxFee'] !== null ? (float)$rule['maxFee'] : null;
        $chargeToClient = (bool)($rule['chargeToClient'] ?? true);

        $platformFee = $this->calculateFeeAmount($amount, $mode, $percentage, $fixed, $minFee, $maxFee);
        $netAmount = $chargeToClient ? round($amount - $platformFee, 2) : round($amount, 2);

        return [
            'platformFee' => $platformFee,
            'netAmount' => $netAmount,
            'feeSettings' => $fundingSettings,
            'appliedRule' => $rule
        ];
    }

    private function normalizeFundingConfigRecord($record) {
        if (!array_key_exists('calculationMode', $record) || empty($record['calculationMode'])) {
            $record['calculationMode'] = 'single';
        }

        return $record;
    }

    private function resolveFeeRule($fundingSettings, $transactionType, $amount) {
        $selectedRule = null;
        foreach ($fundingSettings['feeRules'] ?? [] as $rule) {
            if (($rule['transactionType'] ?? null) !== $transactionType || empty($rule['isActive'])) {
                continue;
            }

            $thresholdAmount = isset($rule['thresholdAmount']) ? (float)$rule['thresholdAmount'] : 0.0;
            if ($amount < $thresholdAmount) {
                continue;
            }

            if ($selectedRule === null || $thresholdAmount >= (float)($selectedRule['thresholdAmount'] ?? 0)) {
                $selectedRule = $rule;
            }
        }

        if ($selectedRule !== null) {
            return $this->normalizeRule($selectedRule);
        }

        return [
            'mode' => 'none',
            'percentage' => 0.0,
            'fixed' => 0.0,
            'minFee' => null,
            'maxFee' => null,
            'chargeToClient' => true,
            'source' => 'none'
        ];
    }

    private function normalizeRule($rule) {
        return [
            'mode' => $rule['feeMode'] ?? ($rule['mode'] ?? 'none'),
            'percentage' => isset($rule['percentage']) ? (float)$rule['percentage'] : 0.0,
            'fixed' => isset($rule['fixed']) ? (float)$rule['fixed'] : 0.0,
            'minFee' => array_key_exists('minFee', $rule) && $rule['minFee'] !== null ? (float)$rule['minFee'] : null,
            'maxFee' => array_key_exists('maxFee', $rule) && $rule['maxFee'] !== null ? (float)$rule['maxFee'] : null,
            'chargeToClient' => array_key_exists('chargeToClient', $rule) ? (bool)$rule['chargeToClient'] : true,
            'source' => isset($rule['transactionType']) ? 'rule' : 'single'
        ];
    }

    private function attachFeeRules($record, $onlyActive) {
        $record['feeRules'] = $this->feeRuleModel->getByGatewayFundingSettingId(
            (int)$record['id'],
            $onlyActive
        );
        $record['calculationMode'] = $this->inferCalculationMode($record['feeRules']);

        $grouped = $this->quickAmountModel->getGroupedByGatewaySettingId((int)$record['gatewaySettingId']);
        $record['depositQuickAmounts'] = $grouped['depositQuickAmounts'];
        $record['withdrawalQuickAmounts'] = $grouped['withdrawalQuickAmounts'];

        return $record;
    }

    private function inferCalculationMode($feeRules) {
        if (!is_array($feeRules) || empty($feeRules)) {
            return 'single';
        }

        $counts = [];
        foreach ($feeRules as $rule) {
            $transactionType = $rule['transactionType'] ?? '';
            if (!in_array($transactionType, ['deposit', 'withdrawal'], true)) {
                continue;
            }

            $counts[$transactionType] = ($counts[$transactionType] ?? 0) + 1;
            if ($counts[$transactionType] > 1) {
                return 'threshold';
            }
        }

        return 'single';
    }

    private function calculateFeeAmount($amount, $mode, $percentage, $fixed, $minFee, $maxFee) {
        $platformFee = 0.0;
        if ($mode === 'dynamic') {
            $platformFee += ($amount * $percentage);
        }
        if ($mode === 'fixed') {
            $platformFee += $fixed;
        }

        if ($minFee !== null && $platformFee < $minFee) {
            $platformFee = $minFee;
        }
        if ($maxFee !== null && $platformFee > $maxFee) {
            $platformFee = $maxFee;
        }

        return round($platformFee, 2);
    }
}
