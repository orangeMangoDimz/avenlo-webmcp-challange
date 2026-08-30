<?php
/**
 * Payment Gateway Quick Amount Model
 * 对应表: paymentGatewayQuickAmounts
 */

require_once __DIR__ . '/BaseModel.php';

class PaymentGatewayQuickAmount extends BaseModel {
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const DEFAULT_AMOUNTS = [100.0, 500.0, 1000.0, 5000.0];

    protected $table = 'paymentGatewayQuickAmounts';
    protected $primaryKey = 'id';
    protected $fillable = [
        'gatewaySettingId',
        'transactionType',
        'amount',
        'sortOrder',
    ];

    protected function getNumericFields() {
        return ['id', 'gatewaySettingId', 'amount', 'sortOrder'];
    }

    public function listByGatewaySettingId($gatewaySettingId, $transactionType = null) {
        $sql = "SELECT id, gatewaySettingId, transactionType, amount, sortOrder
                FROM {$this->table}
                WHERE gatewaySettingId = :gatewaySettingId";
        $params = ['gatewaySettingId' => (int)$gatewaySettingId];

        if ($transactionType !== null) {
            $sql .= " AND transactionType = :transactionType";
            $params['transactionType'] = $transactionType;
        }

        $sql .= " ORDER BY transactionType ASC, amount ASC, sortOrder ASC, id ASC";

        $rows = $this->db->fetchAll($sql, $params) ?: [];
        return array_map([$this, 'formatRow'], $rows);
    }

    public function getGroupedByGatewaySettingId($gatewaySettingId) {
        $rows = $this->listByGatewaySettingId($gatewaySettingId);
        $deposit = [];
        $withdrawal = [];
        foreach ($rows as $row) {
            if ($row['transactionType'] === self::TYPE_WITHDRAWAL) {
                $withdrawal[] = $row;
            } else {
                $deposit[] = $row;
            }
        }
        return [
            'depositQuickAmounts' => $deposit,
            'withdrawalQuickAmounts' => $withdrawal,
        ];
    }

    public function countByGatewayAndType($gatewaySettingId, $transactionType) {
        $sql = "SELECT COUNT(*) AS cnt
                FROM {$this->table}
                WHERE gatewaySettingId = :gatewaySettingId
                  AND transactionType = :transactionType";
        $row = $this->db->fetchOne($sql, [
            'gatewaySettingId' => (int)$gatewaySettingId,
            'transactionType' => $transactionType,
        ]);
        return (int)($row['cnt'] ?? 0);
    }

    public function addAmount($gatewaySettingId, $transactionType, $amount) {
        $gatewaySettingId = (int)$gatewaySettingId;
        $transactionType = $this->normalizeType($transactionType);
        $amount = round((float)$amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than 0');
        }

        $existing = $this->db->fetchOne(
            "SELECT id FROM {$this->table}
             WHERE gatewaySettingId = :gatewaySettingId
               AND transactionType = :transactionType
               AND amount = :amount
             LIMIT 1",
            [
                'gatewaySettingId' => $gatewaySettingId,
                'transactionType' => $transactionType,
                'amount' => $amount,
            ]
        );
        if ($existing) {
            throw new InvalidArgumentException('This amount already exists for this gateway');
        }

        $sortOrder = $this->nextSortOrder($gatewaySettingId, $transactionType);
        $id = (int)$this->create([
            'gatewaySettingId' => $gatewaySettingId,
            'transactionType' => $transactionType,
            'amount' => $amount,
            'sortOrder' => $sortOrder,
        ]);

        $row = $this->findById($id);
        return $this->formatRow($row);
    }

    public function deleteAmount($id) {
        $id = (int)$id;
        $row = $this->findById($id);
        if (!$row) {
            return null;
        }

        $count = $this->countByGatewayAndType($row['gatewaySettingId'], $row['transactionType']);
        if ($count <= 1) {
            throw new RuntimeException('At least one quick amount is required');
        }

        $this->delete($id);
        return $this->formatRow($row);
    }

    public function seedDefaults($gatewaySettingId) {
        $gatewaySettingId = (int)$gatewaySettingId;
        foreach ([self::TYPE_DEPOSIT, self::TYPE_WITHDRAWAL] as $type) {
            if ($this->countByGatewayAndType($gatewaySettingId, $type) > 0) {
                continue;
            }
            $sortOrder = 1;
            foreach (self::DEFAULT_AMOUNTS as $amount) {
                $this->create([
                    'gatewaySettingId' => $gatewaySettingId,
                    'transactionType' => $type,
                    'amount' => $amount,
                    'sortOrder' => $sortOrder,
                ]);
                $sortOrder++;
            }
        }
    }

    public function seedDefaultsForAllGateways() {
        $sql = "SELECT id FROM paymentGatewaySettings WHERE deletedAt IS NULL";
        $gateways = $this->db->fetchAll($sql) ?: [];
        foreach ($gateways as $gateway) {
            $this->seedDefaults((int)$gateway['id']);
        }
    }

    private function nextSortOrder($gatewaySettingId, $transactionType) {
        $sql = "SELECT COALESCE(MAX(sortOrder), 0) AS maxSort
                FROM {$this->table}
                WHERE gatewaySettingId = :gatewaySettingId
                  AND transactionType = :transactionType";
        $row = $this->db->fetchOne($sql, [
            'gatewaySettingId' => (int)$gatewaySettingId,
            'transactionType' => $transactionType,
        ]);
        return ((int)($row['maxSort'] ?? 0)) + 1;
    }

    private function normalizeType($transactionType) {
        $type = strtolower(trim((string)$transactionType));
        if ($type === 'withdraw') {
            $type = self::TYPE_WITHDRAWAL;
        }
        if (!in_array($type, [self::TYPE_DEPOSIT, self::TYPE_WITHDRAWAL], true)) {
            throw new InvalidArgumentException('Invalid transaction type');
        }
        return $type;
    }

    private function formatRow($row) {
        if (!$row || !is_array($row)) {
            return $row;
        }
        return [
            'id' => (int)$row['id'],
            'gatewaySettingId' => (int)$row['gatewaySettingId'],
            'transactionType' => $row['transactionType'],
            'amount' => (float)$row['amount'],
            'sortOrder' => (int)($row['sortOrder'] ?? 0),
        ];
    }
}
