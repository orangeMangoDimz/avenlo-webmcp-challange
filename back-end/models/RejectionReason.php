<?php
/**
 * Rejection Reason Model
 * 对应表: rejectionReasons
 */

require_once __DIR__ . '/BaseModel.php';

class RejectionReason extends BaseModel {
    protected $table = 'rejectionReasons';
    protected $primaryKey = 'id';
    protected $fillable = [
        'scope',
        'reasonKey',
        'reasonTitle',
        'reasonDescription',
        'isActive',
        'displayOrder'
    ];

    /**
     * 获取活跃的拒绝原因
     */
    public function getActiveReasons($scope = null) {
        $conditions = ['isActive' => 1];
        if ($scope !== null && $scope !== '') {
            $conditions['scope'] = $scope;
        }

        return $this->findAll($conditions, 'displayOrder ASC');
    }

    /**
     * 根据reasonKey查找
     */
    public function findByKey($reasonKey, $scope = null) {
        $conditions = ['reasonKey' => $reasonKey];
        if ($scope !== null && $scope !== '') {
            $conditions['scope'] = $scope;
        }

        return $this->findOne($conditions);
    }
}
