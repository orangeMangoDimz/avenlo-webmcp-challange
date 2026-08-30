<?php
/**
 * Transaction Display Content Model
 * 对应表: transactionDisplayContents
 */

require_once __DIR__ . '/BaseModel.php';

class TransactionDisplayContent extends BaseModel {
    protected $table = 'transactionDisplayContents';
    protected $primaryKey = 'id';
    protected $fillable = [
        'scope',
        'contentJson',
        'isActive',
        'createdBy',
        'updatedBy'
    ];

    public function getAllContents() {
        $sql = "SELECT * FROM {$this->table} ORDER BY scope ASC";
        return $this->decodeContentRows($this->query($sql));
    }

    public function getActiveContents() {
        $sql = "SELECT * FROM {$this->table} WHERE isActive = 1 ORDER BY scope ASC";
        return $this->decodeContentRows($this->query($sql));
    }

    public function getByScope($scope, $activeOnly = false) {
        $conditions = ['scope' => $scope];
        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        $row = $this->findOne($conditions);
        return $row ? $this->decodeContentRow($row) : null;
    }

    public function upsertByScope($scope, $data) {
        $existing = $this->findOne(['scope' => $scope]);

        if ($existing) {
            return $this->update((int)$existing['id'], $data);
        }

        $data['scope'] = $scope;
        return $this->create($data);
    }

    protected function getBooleanFields() {
        return ['isActive'];
    }

    private function decodeContentRows($rows) {
        if (!is_array($rows)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $row = $this->decodeContentRow($row);
        }
        unset($row);

        return $rows;
    }

    private function decodeContentRow($row) {
        $content = [];
        if (!empty($row['contentJson'])) {
            $content = json_decode((string)$row['contentJson'], true) ?: [];
        }

        $row['contentJson'] = is_array($content) ? array_values(array_filter($content, function ($item) {
            return is_array($item);
        })) : [];

        return $row;
    }
}
