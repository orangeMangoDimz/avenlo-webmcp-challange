<?php
/**
 * 部门模型
 */

require_once __DIR__ . '/BaseModel.php';

class Department extends BaseModel {
    protected $table = 'departments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'description', 'displayOrder', 'isActive'
    ];

    /**
     * 获取所有启用的部门（按displayOrder降序排序）
     */
    public function getActiveDepartments() {
        return $this->findAll(['isActive' => 1], 'displayOrder DESC');
    }

    /**
     * 获取所有部门（按displayOrder降序排序）
     */
    public function getAllDepartments() {
        return $this->findAll([], 'displayOrder DESC');
    }
}
