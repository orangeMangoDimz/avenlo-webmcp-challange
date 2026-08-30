<?php
/**
 * 管理员用户资料模型
 */

require_once __DIR__ . '/BaseModel.php';

class AdminUserProfile extends BaseModel {
    protected $table = 'adminUserProfiles';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userId', 'phone', 'phoneCountryCode', 'department', 'timezone', 'language'
    ];

    protected $hidden = [];

    /**
     * 根据用户ID查找资料
     */
    public function findByUserId($userId) {
        return $this->findOne(['userId' => $userId]);
    }

    /**
     * 更新或创建用户资料
     */
    public function updateOrCreate($userId, $data) {
        $profile = $this->findByUserId($userId);

        if ($profile) {
            // 更新现有资料
            $this->update($profile['id'], $data);
            return $this->findByUserId($userId);
        } else {
            // 创建新资料
            $data['userId'] = $userId;
            $id = $this->create($data);
            return $this->findById($id);
        }
    }
}
