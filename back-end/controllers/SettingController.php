<?php
/**
 * 系统设置控制器
 */

require_once __DIR__ . '/../models/AdminSystemSetting.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';

class SettingController {
    private $settingModel;

    public function __construct() {
        $this->settingModel = new AdminSystemSetting();
    }

    /**
     * 获取所有设置
     * GET /api/settings
     */
    public function index() {
        $category = $_GET['category'] ?? null;
        $publicOnly = $_GET['public'] ?? false;

        if ($publicOnly) {
            $settings = $this->settingModel->getPublicSettings();
        } elseif ($category) {
            $settings = $this->settingModel->getByCategory($category);
        } else {
            $all = $this->settingModel->findAll([], 'sortOrder ASC');
            $settings = [];
            foreach ($all as $setting) {
                $settings[$setting['settingKey']] = [
                    'value' => $setting['settingValue'],
                    'type' => $setting['settingType'],
                    'category' => $setting['category'],
                    'display_name' => $setting['displayName'],
                    'description' => $setting['description'],
                    'is_editable' => $setting['isEditable']
                ];
            }
        }

        Response::success($settings);
    }

    /**
     * 获取单个设置
     * GET /api/settings/{key}
     */
    public function show($key) {
        $value = $this->settingModel->getValue($key);

        if ($value === null) {
            Response::notFound('Setting not found');
        }

        Response::success(['value' => $value]);
    }

    /**
     * 更新设置
     * PUT /api/settings/{key}
     */
    public function update($key) {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['value'])) {
            Response::error('Value is required', 400);
        }

        // 获取当前用户ID
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $updatedBy = $payload['userId'];

        $success = $this->settingModel->setValue($key, $data['value'], $updatedBy);

        if ($success) {
            Response::success(null, 'Setting updated successfully');
        } else {
            Response::error('Failed to update setting', 500);
        }
    }

    /**
     * 批量更新设置
     * PUT /api/settings/bulk
     */
    public function bulkUpdate() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data) || !is_array($data)) {
            Response::error('Invalid data format', 400);
        }

        // 获取当前用户ID
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $updatedBy = $payload['userId'];

        $success = $this->settingModel->bulkUpdate($data, $updatedBy);

        if ($success) {
            Response::success(null, 'Settings updated successfully');
        } else {
            Response::error('Failed to update settings', 500);
        }
    }
}
