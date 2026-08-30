<?php
/**
 * 登录页品牌设置模型
 */

require_once __DIR__ . '/BaseModel.php';

class LoginPageBranding extends BaseModel {
    protected $table = 'loginPageBranding';
    protected $primaryKey = 'id';

    protected $fillable = [
        'logoType', 'logoText', 'logoImagePath',
        'taglineEn', 'taglineZh', 'featuresContent', 'updatedBy'
    ];

    /**
     * 获取品牌设置（总是返回单一记录）
     */
    public function getSettings() {
        $config = require __DIR__ . '/../config/app.php';
        $logoname = $config['logoname'];
        $settings = $this->findById(1);
        if (!$settings) {
            // 如果不存在，创建默认设置
            $this->create([
                'logoType' => 'text',
                'logoText' => $logoname,
                'taglineEn' => 'Advanced CFD Trading Platform',
                'taglineZh' => '先进的差价合约交易平台',
                'featuresContent' => '<ul><li>Trade CFDs on Forex, Stocks, and Commodities</li><li>Competitive spreads and fast execution</li><li>Advanced charting and analysis tools</li><li>24/7 customer support</li><li>Secure and regulated platform</li></ul>'
            ]);
            $settings = $this->findById(1);
        }
        return $settings;
    }

    /**
     * 更新品牌设置
     */
    public function updateSettings($data, $adminUserId = null) {
        if ($adminUserId) {
            $data['updatedBy'] = $adminUserId;
        }
        return $this->update(1, $data);
    }

    /**
     * 上传Logo图片
     */
    public function uploadLogo($file) {
        // 验证文件类型
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only PNG, JPG, and SVG are allowed.');
        }

        // 验证文件大小 (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size must be less than 5MB.');
        }

        // 生成唯一文件名
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $uploadDir = __DIR__ . '/../uploads/logos/';

        // 创建目录（如果不存在）
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadPath = $uploadDir . $filename;

        // 移动文件
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return '/uploads/logos/' . $filename;
        }

        throw new Exception('Failed to upload file.');
    }
}
