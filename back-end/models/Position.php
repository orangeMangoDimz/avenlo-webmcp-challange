<?php
/**
 * 职位模型
 */

require_once __DIR__ . '/BaseModel.php';

class Position extends BaseModel {
    protected $table = 'positions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'description', 'displayOrder', 'isActive'
    ];

    /**
     * 获取所有启用的职位（按displayOrder降序排序）
     */
    public function getActivePositions() {
        return $this->findAll(['isActive' => 1], 'displayOrder DESC');
    }

    /**
     * 获取所有职位（按displayOrder降序排序）
     */
    public function getAllPositions() {
        return $this->findAll([], 'displayOrder DESC');
    }
}
