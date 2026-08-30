<?php
/**
 * Payment Gateway Fee Rule Model
 * 对应表: paymentGatewayFeeRules
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentGatewayFeeRule extends BaseModel {
    protected $table = 'paymentGatewayFeeRules';
    protected $primaryKey = 'id';
    protected $fillable = [
        'gatewayFundingSettingId',
        'transactionType',
        'thresholdAmount',
        'feeMode',
        'percentage',
        'fixed',
        'minFee',
        'maxFee',
        'chargeToClient',
        'sortOrder',
        'isActive'
    ];

    protected function getBooleanFields() {
        return ['chargeToClient', 'isActive'];
    }

    protected function getNumericFields() {
        return [
            'id',
            'gatewayFundingSettingId',
            'thresholdAmount',
            'percentage',
            'fixed',
            'minFee',
            'maxFee',
            'sortOrder'
        ];
    }

    public function getByGatewayFundingSettingId($gatewayFundingSettingId, $onlyActive = false, $transactionType = null) {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE gatewayFundingSettingId = :gatewayFundingSettingId";

        $params = ['gatewayFundingSettingId' => (int)$gatewayFundingSettingId];

        if ($onlyActive) {
            $sql .= " AND isActive = 1";
        }

        if ($transactionType !== null) {
            $sql .= " AND transactionType = :transactionType";
            $params['transactionType'] = $transactionType;
        }

        $sql .= " ORDER BY transactionType ASC, thresholdAmount ASC, sortOrder ASC, id ASC";

        return $this->hideFields($this->db->fetchAll($sql, $params));
    }

    public function replaceByGatewayFundingSettingId($gatewayFundingSettingId, $rules) {
        $this->db->delete(
            $this->table,
            'gatewayFundingSettingId = :gatewayFundingSettingId',
            ['gatewayFundingSettingId' => (int)$gatewayFundingSettingId]
        );

        foreach ($rules as $rule) {
            $rule['gatewayFundingSettingId'] = (int)$gatewayFundingSettingId;
            $this->create($rule);
        }
    }
}
