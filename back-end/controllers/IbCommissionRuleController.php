<?php
/**
 * IB Commission Rule 控制器
 * 管理IB佣金规则的CRUD操作
 */

require_once __DIR__ . '/../models/IbCommissionRule.php';
require_once __DIR__ . '/../models/IbSymbolExchangeSetting.php';
require_once __DIR__ . '/../models/IbRuleProduct.php';
require_once __DIR__ . '/../models/IbRuleAdditionalRule.php';
require_once __DIR__ . '/../models/IbRuleCommissionTier.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../services/OperationLog/IbSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class IbCommissionRuleController {
    private $ruleModel;
    private $productModel;
    private $additionalRuleModel;
    private $tierModel;

    private const REBATE_RULE_TYPES = ['per_trade_rebate', 'per_lot_rebate'];

    private const ALLOWED_RULE_TYPES = [
        'cash_back_rebate', 'per_lot', 'percentage', 'per_trade', 'hybrid',
        'per_trade_rebate', 'per_lot_rebate',
    ];

    public function __construct() {
        $this->ruleModel = new IbCommissionRule();
        $this->productModel = new IbRuleProduct();
        $this->additionalRuleModel = new IbRuleAdditionalRule();
        $this->tierModel = new IbRuleCommissionTier();
    }

    /**
     * 获取佣金规则列表
     * GET /api/ib-rules
     */
    public function index() {
        $page = (int)($_GET['page'] ?? 1);
        $page = $page < 1 ? 1 : $page;

        $perPageParam = $_GET['per_page'] ?? 10;
        $perPage = (strtolower((string)$perPageParam) === 'all')
            ? 99999
            : (int)$perPageParam;
        if ($perPage < 1) {
            $perPage = 10;
        }

        // 构建筛选条件
        $filters = [];

        if (!empty(trim((string)($_GET['search'] ?? '')))) {
            $filters['search'] = trim($_GET['search']);
        }

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['ruleType'])) {
            $filters['ruleType'] = $_GET['ruleType'];
        }

        if (isset($_GET['targetRegion'])) {
            $filters['targetRegion'] = $_GET['targetRegion'];
        }

        $result = $this->ruleModel->getRules($page, $perPage, $filters);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取所有激活的规则（用于选择器）
     * GET /api/ib-rules/active
     */
    public function getActiveRules() {
        $rules = $this->ruleModel->getActiveRules();
        Response::success($rules);
    }

    /**
     * 按 Tier Level 获取激活的规则（按层级过滤）
     * GET /api/ib-rules/active-by-tier?tier_level_id={id}
     */
    public function getActiveRulesByTierLevel() {
        $tierLevelId = isset($_GET['tier_level_id']) ? $_GET['tier_level_id'] : null;
        if ($tierLevelId === '' || $tierLevelId === false) {
            $tierLevelId = null;
        }
        if ($tierLevelId === null) {
            $rules = $this->ruleModel->getActiveRules();
        } else {
            $rules = $this->ruleModel->getActiveRulesByTierLevel($tierLevelId);
        }
        Response::success($rules);
    }

    /**
     * 获取单个规则详情
     * GET /api/ib-rules/{id}
     */
    public function show($id) {
        $rule = $this->ruleModel->getRuleDetails($id);

        if (!$rule) {
            Response::notFound('Commission rule not found');
        }

        Response::success($rule);
    }

    /**
     * 创建佣金规则
     * POST /api/ib-rules
     */
    public function create() {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // 验证
        if (empty($data['ruleType'])) {
            $data['ruleType'] = 'cash_back_rebate';
        }
        $errors = Validator::validateData($data, [
            'ruleName' => 'required|string|max:200',
            'ruleType' => 'required|in:' . implode(',', self::ALLOWED_RULE_TYPES),
            'paymentCycle' => 'required|in:realtime,daily,weekly,biweekly,monthly,quarterly',
            'minimumPayout' => 'required|numeric',
            'payoutCurrency' => 'required|string|max:10'
        ]);

        if (!empty($errors)) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsRuleAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors)
            );
            Response::validationError($errors);
        }

        $rebateErrors = $this->validateRebateAmountForRuleType($data);
        if (!empty($rebateErrors)) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsRuleAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage($rebateErrors)
            );
            Response::validationError($rebateErrors);
        }
        $data = $this->normalizeRebateRulePayload($data);

        if (isset($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $index => $product) {
                if (!empty($product['productName']) && (!isset($product['productId']) || (int) $product['productId'] <= 0)) {
                    $validationMessage = OperationLogTextHelpers::validationErrorsToMessage([
                        'products' => ["Product at index {$index}: productId is required"]
                    ]);
                    IbSettingsOperationLog::logFailure($input, 'add', 'ibSettingsRuleAddFailure', $validationMessage);
                    Response::validationError(['products' => "Product at index {$index}: productId is required"]);
                }
            }
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['createdBy'] = $adminId;

        // 开始事务
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // 创建规则
            $ruleId = $this->ruleModel->create($data);

            // 如果有产品配置
            if (isset($data['products']) && !empty($data['products'])) {
                $this->productModel->bulkCreateProducts($ruleId, $data['products']);
            }

            // 如果有额外规则
            if (isset($data['additionalRules']) && !empty($data['additionalRules'])) {
                $this->additionalRuleModel->bulkCreateRules($ruleId, $data['additionalRules']);
            }

            $db->commit();

            IbSettingsOperationLog::logRuleAddSuccess($input, $data['ruleName'] ?? '');
            Response::created(['id' => $ruleId], 'Commission rule created successfully');

        } catch (Exception $e) {
            $db->rollback();
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsRuleAddFailure',
                'Failed to create commission rule: ' . $e->getMessage()
            );
            Response::serverError('Failed to create commission rule: ' . $e->getMessage());
        }
    }

    /**
     * 更新佣金规则
     * PUT /api/ib-rules/{id}
     */
    public function update($id) {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // 验证规则是否存在
        $rule = $this->ruleModel->findById($id);
        if (!$rule) {
            IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsRuleEditFailure', 'Commission rule not found');
            Response::notFound('Commission rule not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['updatedBy'] = $adminId;

        if (isset($data['ruleType']) && !in_array($data['ruleType'], self::ALLOWED_RULE_TYPES, true)) {
            IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsRuleEditFailure', 'Invalid rule type');
            Response::validationError(['ruleType' => 'Invalid rule type']);
        }

        $effectiveRuleType = $data['ruleType'] ?? ($rule['ruleType'] ?? '');
        $rebateErrors = $this->validateRebateAmountForRuleType(array_merge($rule, $data));
        if (!empty($rebateErrors)) {
            IbSettingsOperationLog::logFailure(
                $input,
                'edit',
                'ibSettingsRuleEditFailure',
                OperationLogTextHelpers::validationErrorsToMessage($rebateErrors)
            );
            Response::validationError($rebateErrors);
        }
        $data = $this->normalizeRebateRulePayload(array_merge(['ruleType' => $effectiveRuleType], $data));

        // 更新规则基本信息
        $updateData = [];
        $editableFields = [
            'ruleName', 'ruleType', 'ruleDescription', 'targetRegion',
            'paymentCycle', 'paymentDay', 'minimumPayout', 'payoutCurrency',
            'autoPaymentEnabled', 'status',
            'tierId', 'product_type', 'product', 'trading_platforms_key', 'minimum_trade', 'maximum_trade', 'rate', 'fixed_amount', 'rebateAmount'
        ];

        foreach ($editableFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $updateData['updatedBy'] = $adminId;
            $this->ruleModel->update($id, $updateData);
        }

        // IS3b 方案 A：成功日志仅在 updateProducts 写入
        Response::success(['id' => $id], 'Commission rule updated successfully');
    }

    /**
     * 删除佣金规则
     * DELETE /api/ib-rules/{id}
     */
    public function delete($id) {
        $input = IbSettingsOperationLog::inputFromRequest();

        // 验证规则是否存在
        $rule = $this->ruleModel->findById($id);
        if (!$rule) {
            IbSettingsOperationLog::logFailure($input, 'delete', 'ibSettingsRuleDeleteFailure', 'Commission rule not found');
            Response::notFound('Commission rule not found');
        }

        // 检查是否有IB正在使用
        $stats = $this->ruleModel->getAssignmentStats($id);
        if ($stats['assignedIbCount'] > 0) {
            $message = 'Cannot delete rule: ' . $stats['assignedIbCount'] . ' IB partner(s) are using this rule';
            IbSettingsOperationLog::logFailure($input, 'delete', 'ibSettingsRuleDeleteFailure', $message);
            Response::error($message, 409);
        }

        $ruleName = trim((string) ($rule['ruleName'] ?? ''));

        // 删除规则（级联删除产品和额外规则）
        $this->ruleModel->delete($id);

        IbSettingsOperationLog::logRuleDeleteSuccess($input, $ruleName);
        Response::success(null, 'Commission rule deleted successfully');
    }

    /**
     * 复制佣金规则
     * POST /api/ib-rules/{id}/duplicate
     */
    public function duplicate($id) {
        $input = IbSettingsOperationLog::inputFromRequest();

        // 验证规则是否存在
        $rule = $this->ruleModel->findById($id);
        if (!$rule) {
            IbSettingsOperationLog::logFailure($input, 'add', 'ibSettingsRuleDuplicateFailure', 'Commission rule not found');
            Response::notFound('Commission rule not found');
        }

        $sourceRuleName = trim((string) ($rule['ruleName'] ?? ''));

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        try {
            $newRuleId = $this->ruleModel->duplicateRule($id, $adminId);

            IbSettingsOperationLog::logRuleDuplicateSuccess($input, $sourceRuleName);
            Response::created([
                'id' => $newRuleId,
                'originalId' => $id
            ], 'Commission rule duplicated successfully');

        } catch (Exception $e) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsRuleDuplicateFailure',
                'Failed to duplicate rule: ' . $e->getMessage()
            );
            Response::serverError('Failed to duplicate rule: ' . $e->getMessage());
        }
    }

    /**
     * 更新规则产品配置
     * PUT /api/ib-rules/{id}/products
     */
    public function updateProducts($id) {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        $rule = $this->ruleModel->findById($id);
        if (!$rule) {
            IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsRuleEditFailure', 'Commission rule not found');
            Response::notFound('Commission rule not found');
        }
        $ruleName = trim((string) ($rule['ruleName'] ?? ''));

        // 验证
        $errors = Validator::validateData($data, [
            'products' => 'required|array'
        ]);

        if (!empty($errors)) {
            IbSettingsOperationLog::logFailure(
                $input,
                'edit',
                'ibSettingsRuleEditFailure',
                OperationLogTextHelpers::validationErrorsToMessage($errors)
            );
            Response::validationError($errors);
        }

        // 验证每个产品
        foreach ($data['products'] as $index => $product) {
            if (empty($product['productName'])) {
                $validationMessage = OperationLogTextHelpers::validationErrorsToMessage([
                    'products' => ["Product at index {$index}: productName is required"]
                ]);
                IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsRuleEditFailure', $validationMessage);
                Response::validationError(['products' => "Product at index {$index}: productName is required"]);
            }
            if (!isset($product['commissionRate'])) {
                $validationMessage = OperationLogTextHelpers::validationErrorsToMessage([
                    'products' => ["Product at index {$index}: commissionRate is required"]
                ]);
                IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsRuleEditFailure', $validationMessage);
                Response::validationError(['products' => "Product at index {$index}: commissionRate is required"]);
            }
            if (!isset($product['productId']) || (int) $product['productId'] <= 0) {
                $validationMessage = OperationLogTextHelpers::validationErrorsToMessage([
                    'products' => ["Product at index {$index}: productId is required"]
                ]);
                IbSettingsOperationLog::logFailure($input, 'edit', 'ibSettingsRuleEditFailure', $validationMessage);
                Response::validationError(['products' => "Product at index {$index}: productId is required"]);
            }
        }

        try {
            error_log('Updating products for rule ID: ' . $id);
            error_log('Products data: ' . json_encode($data['products']));

            $this->productModel->bulkUpdateProducts($id, $data['products']);
            IbSettingsOperationLog::logRuleEditSuccess($input, $ruleName);
            Response::success(null, 'Products updated successfully');

        } catch (Exception $e) {
            error_log('Failed to update products: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            IbSettingsOperationLog::logFailure(
                $input,
                'edit',
                'ibSettingsRuleEditFailure',
                'Failed to update products: ' . $e->getMessage()
            );
            Response::serverError('Failed to update products: ' . $e->getMessage());
        }
    }

    /**
     * 更新额外规则配置
     * PUT /api/ib-rules/{id}/additional-rules
     */
    public function updateAdditionalRules($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $errors = Validator::validateData($data, [
            'additionalRules' => 'required|array'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // 验证每个额外规则
        foreach ($data['additionalRules'] as $index => $rule) {
            if (empty($rule['productName'])) {
                Response::validationError(['additionalRules' => "Additional rule at index {$index}: productName is required"]);
            }
            if (empty($rule['ruleType'])) {
                Response::validationError(['additionalRules' => "Additional rule at index {$index}: ruleType is required"]);
            }
        }

        try {
            error_log('Updating additional rules for rule ID: ' . $id);
            error_log('Additional rules data: ' . json_encode($data['additionalRules']));

            $this->additionalRuleModel->bulkUpdateRules($id, $data['additionalRules']);
            Response::success(null, 'Additional rules updated successfully');

        } catch (Exception $e) {
            error_log('Failed to update additional rules: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            Response::serverError('Failed to update additional rules: ' . $e->getMessage());
        }
    }

    /**
     * 管理佣金层级
     * PUT /api/ib-rules/additional-rules/{additionalRuleId}/tiers
     */
    public function updateTiers($additionalRuleId) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $errors = Validator::validateData($data, [
            'tiers' => 'required|array'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $this->tierModel->bulkCreateTiers($additionalRuleId, $data['tiers']);
            Response::success(null, 'Commission tiers updated successfully');

        } catch (Exception $e) {
            Response::serverError('Failed to update tiers: ' . $e->getMessage());
        }
    }

    private function validateRebateAmountForRuleType(array $data): array {
        $ruleType = $data['ruleType'] ?? '';
        if (!in_array($ruleType, self::REBATE_RULE_TYPES, true)) {
            return [];
        }
        if (!isset($data['rebateAmount']) || $data['rebateAmount'] === '' || (float) $data['rebateAmount'] <= 0) {
            return ['rebateAmount' => 'rebateAmount must be greater than 0'];
        }
        $rounded = IbSymbolExchangeSetting::roundExchangeRate($data['rebateAmount']);
        if (abs($rounded - (float) $data['rebateAmount']) > 0.000009) {
            return ['rebateAmount' => 'rebateAmount allows at most 5 decimal places'];
        }
        return [];
    }

    private function normalizeRebateRulePayload(array $data): array {
        $ruleType = $data['ruleType'] ?? '';
        if (in_array($ruleType, self::REBATE_RULE_TYPES, true) && isset($data['rebateAmount'])) {
            $data['rebateAmount'] = IbSymbolExchangeSetting::roundExchangeRate($data['rebateAmount']);
        }
        return $data;
    }
}
