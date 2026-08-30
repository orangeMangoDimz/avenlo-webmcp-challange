<?php
/**
 * IB Tier Level 控制器
 * 管理IB层级模板
 */

require_once __DIR__ . '/../models/IbTierLevel.php';
require_once __DIR__ . '/../models/IbProgramSetting.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../services/OperationLog/IbSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class IbTierLevelController {
    private $tierLevelModel;

    public function __construct() {
        $this->tierLevelModel = new IbTierLevel();
    }

    /**
     * 获取所有层级列表
     * GET /api/ib-tier-levels
     */
    public function index() {
        $withStats = $_GET['with_stats'] ?? false;

        if ($withStats) {
            $tiers = $this->tierLevelModel->getTierLevelsWithStats();
        } else {
            $tiers = $this->tierLevelModel->findAll([], 'tierLevel ASC');
        }

        Response::success($tiers);
    }

    /**
     * 获取所有激活的层级（用于选择器）
     * GET /api/ib-tier-levels/active
     */
    public function getActiveTiers() {
        $tiers = $this->tierLevelModel->getActiveTierLevels();
        Response::success($tiers);
    }

    /**
     * 获取单个层级详情
     * GET /api/ib-tier-levels/{id}
     */
    public function show($id) {
        $tier = $this->tierLevelModel->findById($id);

        if (!$tier) {
            Response::notFound('Tier level not found');
        }

        // 获取使用统计
        $tier['usageStats'] = $this->tierLevelModel->getTierUsageStats($id);

        Response::success($tier);
    }

    /**
     * 创建层级
     * POST /api/ib-tier-levels
     */
    public function create() {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // 验证
        $errors = Validator::validateData($data, [
            'tierLevel' => 'required|numeric',
            'tierName' => 'required|string|max:100'
        ]);

        if (!empty($errors)) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsTierAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors)
            );
            Response::validationError($errors);
        }

        if (isset($data['badgeColor']) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['badgeColor'])) {
            IbSettingsOperationLog::logFailure($input, 'add', 'ibSettingsTierAddFailure', 'Invalid badge color');
            Response::validationError(['badgeColor' => ['Badge color must be a hex color like #475569']]);
        }

        // 检查层级编号是否已存在
        $existing = $this->tierLevelModel->findOne(['tierLevel' => $data['tierLevel']]);
        if ($existing) {
            IbSettingsOperationLog::logFailure($input, 'add', 'ibSettingsTierAddFailure', 'Tier level number already exists');
            Response::error('Tier level number already exists', 409);
        }

        $maxTierCount = (new IbProgramSetting())->getMaxTierLevelCount();
        if ((int) $data['tierLevel'] > $maxTierCount) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsTierAddFailure',
                'Tier level exceeds maximum configured count'
            );
            Response::error(
                'Tier level cannot exceed the configured maximum (' . $maxTierCount . ')',
                422,
                null,
                'IB_TIER_LEVEL_EXCEEDS_MAX'
            );
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['createdBy'] = $adminId;

        // 设置默认权限值
        $data['canRecruitSubAgents'] = $data['canRecruitSubAgents'] ?? 0;
        $data['canViewReports'] = $data['canViewReports'] ?? 0;
        $data['canManageClients'] = $data['canManageClients'] ?? 0;

        // 创建层级
        $tierId = $this->tierLevelModel->create($data);

        IbSettingsOperationLog::logTierAddSuccess($input, $data['tierName'] ?? '', $data['tierLevel'] ?? 0);
        Response::created(['id' => $tierId], 'Tier level created successfully');
    }

    /**
     * 更新层级
     * PUT /api/ib-tier-levels/{id}
     */
    public function update($id) {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // 验证层级是否存在
        $tier = $this->tierLevelModel->findById($id);
        if (!$tier) {
            IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsTierEditFailure', 'Tier level not found');
            Response::notFound('Tier level not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        if (isset($data['badgeColor']) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['badgeColor'])) {
            IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsTierEditFailure', 'Invalid badge color');
            Response::validationError(['badgeColor' => ['Badge color must be a hex color like #475569']]);
        }

        $data['updatedBy'] = $adminId;

        // 更新层级
        $this->tierLevelModel->update($id, $data);

        $tierName = trim((string) ($data['tierName'] ?? $tier['tierName'] ?? ''));
        $tierLevel = $data['tierLevel'] ?? $tier['tierLevel'] ?? 0;
        IbSettingsOperationLog::logTierEditSuccess($input, $tierName, $tierLevel);
        Response::success(['id' => $id], 'Tier level updated successfully');
    }

    /**
     * 删除层级
     * DELETE /api/ib-tier-levels/{id}
     */
    public function delete($id) {
        $input = IbSettingsOperationLog::inputFromRequest();

        // 验证层级是否存在
        $tier = $this->tierLevelModel->findById($id);
        if (!$tier) {
            IbSettingsOperationLog::logFailure($input, 'delete', 'ibSettingsTierDeleteFailure', 'Tier level not found');
            Response::notFound('Tier level not found');
        }

        // 检查是否有IB正在使用
        $stats = $this->tierLevelModel->getTierUsageStats($id);
        if ($stats['assignedIbCount'] > 0) {
            $message = 'Cannot delete tier: ' . $stats['assignedIbCount'] . ' IB partner(s) are using this tier level';
            IbSettingsOperationLog::logFailure($input, 'delete', 'ibSettingsTierDeleteFailure', $message);
            Response::error($message, 409);
        }

        $tierName = trim((string) ($tier['tierName'] ?? ''));
        $tierLevel = $tier['tierLevel'] ?? 0;

        // 删除层级
        $this->tierLevelModel->delete($id);

        IbSettingsOperationLog::logTierDeleteSuccess($input, $tierName, $tierLevel);
        Response::success(null, 'Tier level deleted successfully');
    }
}
