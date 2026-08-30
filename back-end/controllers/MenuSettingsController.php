<?php
/**
 * Menu Settings Controller
 * 菜单设置控制器，用于管理影响菜单显示的系统设置
 */

require_once __DIR__ . '/../models/IbProgramSetting.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';

class MenuSettingsController {
    private $ibSettingModel;
    private $cacheKey = 'menu_settings_cache';
    private $cacheExpiry = 300; // 缓存5分钟（300秒）

    public function __construct() {
        $this->ibSettingModel = new IbProgramSetting();
    }

    /**
     * 获取所有菜单相关设置
     * GET /api/menu-settings
     * 支持缓存机制
     */
    public function index() {
        // 检查缓存
        $cached = $this->getCachedSettings();
        if ($cached !== null) {
            Response::success($cached);
            return;
        }

        // 获取设置
        $settings = $this->loadMenuSettings();

        // 保存到缓存
        $this->setCachedSettings($settings);

        Response::success($settings);
    }

    /**
     * 强制刷新缓存
     * POST /api/menu-settings/refresh
     */
    public function refreshCache() {
        // 清除缓存
        $this->clearCache();

        // 重新加载设置
        $settings = $this->loadMenuSettings();

        // 保存到缓存
        $this->setCachedSettings($settings);

        Response::success($settings, 'Cache refreshed successfully');
    }

    /**
     * 加载菜单相关设置
     */
    private function loadMenuSettings() {
        $settings = [];

        // 获取IB Program启用状态
        $ibProgramSetting = $this->ibSettingModel->getSettingByKey('enable_ib_program');
        $settings['enable_ib_program'] = $ibProgramSetting ?
            filter_var($ibProgramSetting['settingValue'], FILTER_VALIDATE_BOOLEAN) : false;

        // 未来可以在这里添加其他影响菜单显示的设置
        // 例如：
        // $settings['enable_kyc_module'] = ...;
        // $settings['enable_reporting'] = ...;

        return $settings;
    }

    /**
     * 从缓存获取设置
     */
    private function getCachedSettings() {
        // 使用文件缓存（简单实现）
        // 在生产环境中可以考虑使用Redis等
        $cacheFile = $this->getCacheFilePath();

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheData = json_decode(file_get_contents($cacheFile), true);

        if (!$cacheData || !isset($cacheData['expiry']) || !isset($cacheData['data'])) {
            return null;
        }

        // 检查是否过期
        if (time() > $cacheData['expiry']) {
            @unlink($cacheFile);
            return null;
        }

        return $cacheData['data'];
    }

    /**
     * 保存设置到缓存
     */
    private function setCachedSettings($settings) {
        $cacheFile = $this->getCacheFilePath();
        $cacheDir = dirname($cacheFile);

        // 确保缓存目录存在
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheData = [
            'data' => $settings,
            'expiry' => time() + $this->cacheExpiry,
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($cacheFile, json_encode($cacheData));
    }

    /**
     * 清除缓存
     */
    private function clearCache() {
        $cacheFile = $this->getCacheFilePath();
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    /**
     * 获取缓存文件路径
     */
    private function getCacheFilePath() {
        $cacheDir = __DIR__ . '/../cache';
        return $cacheDir . '/menu_settings.json';
    }

    /**
     * 静态方法：清除菜单设置缓存
     * 当相关设置更新时调用此方法
     */
    public static function clearMenuSettingsCache() {
        $cacheFile = __DIR__ . '/../cache/menu_settings.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}
