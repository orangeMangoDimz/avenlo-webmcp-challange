<?php
/**
 * IB Settings 控制器
 * 管理IB程序设置和文档模板
 */

require_once __DIR__ . '/MenuSettingsController.php';
require_once __DIR__ . '/../models/IbProgramSetting.php';
require_once __DIR__ . '/../models/IbTierLevel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/IbDocumentTemplate.php';
require_once __DIR__ . '/../models/IbCustomSecurity.php';
require_once __DIR__ . '/../models/IbCustomSymbol.php';
require_once __DIR__ . '/../models/IbSymbolExchangeSetting.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../services/IbProductSyncService.php';
require_once __DIR__ . '/../services/OperationLog/IbSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class IbSettingsController {
    private $settingModel;
    private $documentModel;
    private $securityModel;
    private $symbolModel;
    private $platformModel;
    private $symbolExchangeModel;

    public function __construct() {
        $this->settingModel = new IbProgramSetting();
        $this->documentModel = new IbDocumentTemplate();
        $this->securityModel = new IbCustomSecurity();
        $this->symbolModel = new IbCustomSymbol();
        $this->platformModel = new TradingPlatform();
        $this->symbolExchangeModel = new IbSymbolExchangeSetting();
    }

    /**
     * 获取所有设置
     * GET /api/ib-settings
     */
    public function index() {
        $group = $_GET['group'] ?? null;

        if ($group) {
            $settings = $this->settingModel->getSettingsByGroup($group);
        } else {
            $settings = $this->settingModel->getAllSettingsGrouped();
        }

        Response::success($settings);
    }

    /**
     * 获取单个设置
     * GET /api/ib-settings/{key}
     */
    public function show($key) {
        $setting = $this->settingModel->getSettingByKey($key);

        if (!$setting) {
            Response::notFound('Setting not found');
        }

        Response::success($setting);
    }

    /**
     * 更新设置
     * PUT /api/ib-settings/{key}
     */
    public function update($key) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'settingValue' => 'required'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 验证设置是否存在
        $setting = $this->settingModel->getSettingByKey($key);
        if (!$setting) {
            Response::notFound('Setting not found');
        }

        if ($key === IbProgramSetting::MAX_TIER_LEVEL_COUNT_KEY) {
            AuthMiddleware::checkPermission('page_ib_settings_set_tier_count');
            $newN = (int) $data['settingValue'];
            $this->validateMaxTierLevelCountValue($newN);
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 更新设置
        $updateData = [
            'settingValue' => $data['settingValue'],
            'updatedBy' => $adminId
        ];

        $this->settingModel->update($setting['id'], $updateData);

        if ($key === IbProgramSetting::MAX_TIER_LEVEL_COUNT_KEY) {
            IbProgramSetting::clearMaxTierLevelCountCache();
        }

        // 如果更新了影响菜单的设置，清除菜单设置缓存
        $menuAffectingKeys = ['enable_ib_program'];
        if (in_array($key, $menuAffectingKeys)) {
            MenuSettingsController::clearMenuSettingsCache();
        }

        Response::success(null, 'Setting updated successfully');
    }

    /**
     * 批量更新设置
     * POST /api/ib-settings/bulk-update
     */
    public function bulkUpdate() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'settings' => 'required|array'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        try {
            foreach ($data['settings'] as $key => $value) {
                if ($key === IbProgramSetting::MAX_TIER_LEVEL_COUNT_KEY) {
                    AuthMiddleware::checkPermission('page_ib_settings_set_tier_count');
                    $this->validateMaxTierLevelCountValue((int) $value);
                }
            }

            $this->settingModel->bulkUpdateSettings($data['settings'], $adminId);

            if (array_key_exists(IbProgramSetting::MAX_TIER_LEVEL_COUNT_KEY, $data['settings'])) {
                IbProgramSetting::clearMaxTierLevelCountCache();
            }

            // 如果更新了影响菜单的设置，清除菜单设置缓存
            $menuAffectingKeys = ['enable_ib_program'];
            $updatedKeys = array_keys($data['settings']);
            $hasMenuAffectingChange = !empty(array_intersect($menuAffectingKeys, $updatedKeys));

            if ($hasMenuAffectingChange) {
                MenuSettingsController::clearMenuSettingsCache();
            }

            Response::success(null, 'Settings updated successfully');

        } catch (Exception $e) {
            Response::serverError('Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * 获取所有文档模板
     * GET /api/ib-settings/documents
     */
    public function getDocuments() {
        $activeOnly = $_GET['active_only'] ?? false;

        if ($activeOnly) {
            $documents = $this->documentModel->getActiveDocuments();
        } else {
            $page = $_GET['page'] ?? 1;
            $perPage = $_GET['per_page'] ?? 20;
            $result = $this->documentModel->getDocuments($page, $perPage);

            Response::paginated(
                $result['items'],
                $result['total'],
                $result['page'],
                $result['per_page']
            );
            return;
        }

        Response::success($documents);
    }

    /**
     * 获取单个文档模板
     * GET /api/ib-settings/documents/{id}
     */
    public function getDocument($id) {
        $document = $this->documentModel->findById($id);

        if (!$document) {
            Response::notFound('Document template not found');
        }

        Response::success($document);
    }

    /**
     * 创建文档模板
     * POST /api/ib-settings/documents
     */
    public function createDocument() {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // 验证
        $validator = new Validator($data, [
            'documentTitle' => 'required|string|max:200',
            'documentContent' => 'required|string'
        ]);

        if (!$validator->validate()) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsDocumentAddFailure',
                OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors())
            );
            Response::validationError($validator->getErrors());
        }

        try {
            // 获取当前用户
            $token = JWT::getTokenFromHeader();
            $payload = JWT::decode($token);
            $adminId = $payload['userId'];

            $data['createdBy'] = $adminId;

            // 设置默认值
            $data['isRequired'] = $data['isRequired'] ?? 1;
            $data['isActive'] = $data['isActive'] ?? 1;
            $data['displayOrder'] = $data['displayOrder'] ?? 99;

            // 计算统计数据（如果有内容）
            if (isset($data['documentContent'])) {
                $plainText = strip_tags($data['documentContent']);
                $data['characterCount'] = mb_strlen($plainText);
                $data['wordCount'] = str_word_count($plainText);
                $data['estimatedReadTime'] = max(1, ceil($data['wordCount'] / 200));
            } else {
                $data['characterCount'] = 0;
                $data['wordCount'] = 0;
                $data['estimatedReadTime'] = 0;
            }

            // 设置默认版本
            $data['version'] = $data['version'] ?? '1.0';

            // 创建文档
            $docId = $this->documentModel->create($data);

            if (!$docId) {
                IbSettingsOperationLog::logFailure(
                    $input,
                    'add',
                    'ibSettingsDocumentAddFailure',
                    'Failed to create document: No ID returned'
                );
                Response::serverError('Failed to create document: No ID returned');
            }

            IbSettingsOperationLog::logDocumentAddSuccess($input, $data['documentTitle'] ?? '');
            Response::created(['id' => $docId], 'Document template created successfully');

        } catch (Exception $e) {
            error_log('Create document error: ' . $e->getMessage());
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsDocumentAddFailure',
                'Failed to create document: ' . $e->getMessage()
            );
            Response::serverError('Failed to create document: ' . $e->getMessage());
        }
    }

    /**
     * 更新文档模板
     * PUT /api/ib-settings/documents/{id}
     */
    public function updateDocument($id) {
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // 验证文档是否存在
        $document = $this->documentModel->findById($id);
        if (!$document) {
            IbSettingsOperationLog::logFailure(
                $input,
                'edit',
                'ibSettingsDocumentEditFailure',
                'Document template not found'
            );
            Response::notFound('Document template not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['updatedBy'] = $adminId;

        // 计算统计数据（如果有内容更新）
        if (isset($data['documentContent'])) {
            $plainText = strip_tags($data['documentContent']);
            $data['characterCount'] = mb_strlen($plainText);
            $data['wordCount'] = str_word_count($plainText);
            $data['estimatedReadTime'] = max(1, ceil($data['wordCount'] / 200));
        }

        // 更新文档
        $this->documentModel->updateDocument($id, $data);

        $title = trim((string) ($data['documentTitle'] ?? $document['documentTitle'] ?? ''));
        IbSettingsOperationLog::logDocumentEditSuccess($input, $title);
        Response::success(['id' => $id], 'Document template updated successfully');
    }

    /**
     * 删除文档模板
     * DELETE /api/ib-settings/documents/{id}
     */
    public function deleteDocument($id) {
        $input = IbSettingsOperationLog::inputFromRequest();

        // 验证文档是否存在
        $document = $this->documentModel->findById($id);
        if (!$document) {
            IbSettingsOperationLog::logFailure(
                $input,
                'delete',
                'ibSettingsDocumentDeleteFailure',
                'Document template not found'
            );
            Response::notFound('Document template not found');
        }

        $title = trim((string) ($document['documentTitle'] ?? ''));

        // 删除文档
        $this->documentModel->delete($id);

        IbSettingsOperationLog::logDocumentDeleteSuccess($input, $title);
        Response::success(null, 'Document template deleted successfully');
    }

    /**
     * 复制文档模板
     * POST /api/ib-settings/documents/{id}/duplicate
     */
    public function duplicateDocument($id) {
        $input = IbSettingsOperationLog::inputFromRequest();

        // 验证文档是否存在
        $document = $this->documentModel->findById($id);
        if (!$document) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsDocumentDuplicateFailure',
                'Document template not found'
            );
            Response::notFound('Document template not found');
        }

        $sourceTitle = trim((string) ($document['documentTitle'] ?? ''));

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        try {
            $newDocId = $this->documentModel->duplicateDocument($id, $adminId);

            IbSettingsOperationLog::logDocumentDuplicateSuccess($input, $sourceTitle);
            Response::created([
                'id' => $newDocId,
                'originalId' => $id
            ], 'Document template duplicated successfully');

        } catch (Exception $e) {
            IbSettingsOperationLog::logFailure(
                $input,
                'add',
                'ibSettingsDocumentDuplicateFailure',
                'Failed to duplicate document: ' . $e->getMessage()
            );
            Response::serverError('Failed to duplicate document: ' . $e->getMessage());
        }
    }

    /**
     * 获取自定义证券列表
     * GET /api/ib-settings/custom-securities
     */
    public function getCustomSecurities() {
        $securities = $this->securityModel->findAll([], 'securityName ASC');
        Response::success($securities);
    }

    /**
     * 按平台获取自定义证券列表
     * GET /api/ib-settings/custom-securitiesbyplatform
     * 参数 trading_platforms_key: 必填，对应 tradingPlatforms.platformKey
     */
    public function getCustomSecuritiesByPlatform() {
        $platformKey = isset($_GET['trading_platforms_key']) ? trim($_GET['trading_platforms_key']) : null;
        if ($platformKey === null || $platformKey === '') {
            Response::error('trading_platforms_key is required', 400);
        }
        $securities = $this->securityModel->findAll(
            ['trading_platforms_key' => $platformKey, 'EnabledMark' => 1],
            'securityName ASC'
        );
        Response::success($securities);
    }

    /**
     * 创建自定义证券
     * POST /api/ib-settings/custom-securities
     */
    public function createCustomSecurity() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'securityName' => 'required|string|max:100'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 检查是否已存在
        $existing = $this->securityModel->findOne(['securityName' => $data['securityName']]);
        if ($existing) {
            Response::error('Security name already exists', 409);
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['createdBy'] = $adminId;

        // 创建证券
        $securityId = $this->securityModel->create($data);

        Response::created(['id' => $securityId], 'Custom security created successfully');
    }

    /**
     * 获取自定义交易对列表
     * GET /api/ib-settings/custom-symbols
     */
    public function getCustomSymbols() {
        $symbols = $this->symbolModel->findAll([], 'symbolName ASC');
        Response::success($symbols);
    }

    /**
     * 按平台获取自定义交易对列表
     * GET /api/ib-settings/custom-symbolsbyplatform
     * 参数 trading_platforms_key: 必填，对应 tradingPlatforms.platformKey
     */
    public function getCustomSymbolsByPlatform() {
        $platformKey = isset($_GET['trading_platforms_key']) ? trim($_GET['trading_platforms_key']) : null;
        if ($platformKey === null || $platformKey === '') {
            Response::error('trading_platforms_key is required', 400);
        }
        $symbols = $this->symbolModel->findAll(
            ['trading_platforms_key' => $platformKey, 'EnabledMark' => 1],
            'symbolName ASC'
        );
        Response::success($symbols);
    }

    /**
     * 创建自定义交易对
     * POST /api/ib-settings/custom-symbols
     */
    public function createCustomSymbol() {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $validator = new Validator($data, [
            'securityId' => 'required|integer',
            'symbolName' => 'required|string|max:50'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->getErrors());
        }

        // 验证 securityId 是否存在
        $security = $this->securityModel->findById($data['securityId']);
        if (!$security) {
            Response::error('Security not found', 404);
        }

        // 转换为大写
        $data['symbolName'] = strtoupper($data['symbolName']);

        // 检查是否已存在
        $existing = $this->symbolModel->findOne(['symbolName' => $data['symbolName']]);
        if ($existing) {
            Response::error('Symbol already exists', 409);
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $data['createdBy'] = $adminId;

        // 创建交易对（包含 securityId）
        $symbolId = $this->symbolModel->create($data);

        Response::created(['id' => $symbolId], 'Custom symbol created successfully');
    }

    /**
     * 按平台同步 Securities & Symbols
     * POST /api/ib-settings/sync-products
     * Body: { "platformKey": "financepro" | "mt4" | "mt5" }
     */
    public function syncProducts() {
        $this->requireAuth();
        $input = IbSettingsOperationLog::inputFromRequest();
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $platformKey = isset($data['platformKey']) ? trim($data['platformKey']) : '';

        if ($platformKey === '') {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'platformKey is required');
            Response::error('platformKey is required', 400);
        }

        $platformKey = strtolower($platformKey);
        $allowed = ['mt4', 'mt5', 'financepro'];
        if (!in_array($platformKey, $allowed, true)) {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Unsupported platform: ' . $platformKey);
            Response::error('Unsupported platform: ' . $platformKey, 400);
        }

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = isset($payload['userId']) ? (int)$payload['userId'] : null;

        // FinancePro / MT5：调用第三方接口并写入 ibCustomSecurities / ibCustomSymbols
        $appConfig = require __DIR__ . '/../config/app.php';
        $tradingPlatforms = $appConfig['trading_platforms'] ?? [];
        if (empty($tradingPlatforms[$platformKey] ?? false)) {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Platform is not enabled in config: ' . $platformKey);
            Response::error('Platform is not enabled in config: ' . $platformKey, 400);
        }

        $platform = $this->platformModel->findByKey($platformKey);
        if (!$platform) {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Trading platform not found for: ' . $platformKey);
            Response::error('Trading platform not found for: ' . $platformKey, 404);
        }

        $tradingPlatformsKey = $platform['platformKey'];

        if ($platformKey === 'mt5' || $platformKey === 'mt4') {
            $taskPayload = [
                'type' => 'sync_ib_products',
                'platformKey' => $platformKey,
                'adminId' => $adminId,
                'logIbSettings' => IbSettingsOperationLog::shouldLog($input),
                'requestedAt' => time(),
            ];
            try {
                $this->dispatchSwooleTask($taskPayload);
            } catch (Exception $e) {
                IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', $e->getMessage());
                Response::error('Failed to queue sync task: ' . $e->getMessage(), 500);
            }
            Response::success([
                'success' => true,
                'queued' => true,
                'platformKey' => $platformKey,
            ], strtoupper($platformKey) . ' products sync task accepted.');
        }

        $endpoint = $appConfig['integrations']['finance_pro']['get_security_symbol'] ?? '';
        if ($endpoint === '') {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Trading Platform product sync endpoint not configured');
            Response::error('Trading Platform product sync endpoint not configured', 500);
        }

        try {
            $apiClient = new FinanceProApiClient();
            // GET，不传 includeDisabled，只拉取可用数据
//            $payload = []; $payload['includeDisabled'] = 0;
            $response = $apiClient->request($endpoint, 'GET', null, [
                'expect_success_key' => 'Success',
                'success_value' => true
            ]);
        } catch (Exception $e) {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Trading Platform request failed: ' . $e->getMessage());
            Response::error('Trading Platform request failed: ' . $e->getMessage(), 500);
        }

        $resData = $response['ResData'] ?? null;
        if (!is_array($resData)) {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Invalid Trading Platform response: missing ResData');
            Response::error('Invalid Trading Platform response: missing ResData', 500);
        }

        $securitiesList = $resData['Securities'] ?? [];
        $symbolsList = $resData['Symbols'] ?? [];
        if (!is_array($securitiesList)) {
            $securitiesList = [];
        }
        if (!is_array($symbolsList)) {
            $symbolsList = [];
        }

        if (empty($securitiesList) && empty($symbolsList)) {
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', 'Trading Platform returned no securities or symbols');
            Response::error('Trading Platform returned no securities or symbols', 400);
        }

        $db = Database::getInstance();
        $syncedSecurityTradingIds = [];
        $syncedSymbolTradingIds = [];

        try {
            $db->beginTransaction();

            // 1) 同步 Securities，建立 外部 Security Id -> 本地 id 映射；trading_id 存平台端 Security Id（ResData.Securities[].Id）
            $externalSecurityIdToLocalId = [];
            foreach ($securitiesList as $item) {
                $externalId = isset($item['Id']) ? (int) $item['Id'] : 0;
                $name = isset($item['Name']) ? trim((string) $item['Name']) : '';
                if ($name === '' || $externalId <= 0) {
                    continue;
                }
                $description = isset($item['Description']) ? trim((string) $item['Description']) : null;

                $existing = $this->securityModel->findOne([
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'trading_id' => $externalId
                ]);
                $row = [
                    'securityName' => $name,
                    'securityDescription' => $description,
                    'trading_id' => $externalId,
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'Description' => $description,
                    'EnabledMark' => 1,
                    'createdBy' => $adminId
                ];
                if ($existing) {
                    $this->securityModel->update($existing['id'], $row);
                    $externalSecurityIdToLocalId[$externalId] = (int) $existing['id'];
                } else {
                    $insertId = $this->securityModel->create($row);
                    $externalSecurityIdToLocalId[$externalId] = (int) $insertId;
                }
                $syncedSecurityTradingIds[] = $externalId;
            }

            // 2) 同步 Symbols，绑定到本地 Security；trading_id 存平台端 Symbol Id（ResData.Symbols[].Id）
            foreach ($symbolsList as $item) {
                $externalSymbolId = isset($item['Id']) ? (int) $item['Id'] : 0;
                $name = isset($item['Name']) ? trim((string) $item['Name']) : '';
                if ($name === '' || $externalSymbolId <= 0) {
                    continue;
                }
                $externalSecurityId = isset($item['SecurityId']) ? (int) $item['SecurityId'] : null;
                $securityId = null;
                if ($externalSecurityId !== null && isset($externalSecurityIdToLocalId[$externalSecurityId])) {
                    $securityId = $externalSecurityIdToLocalId[$externalSecurityId];
                }

                $symbolDescription = isset($item['Sescription']) ? trim((string) $item['Sescription']) : null;
                if ($symbolDescription === '' && isset($item['Description'])) {
                    $symbolDescription = trim((string) $item['Description']);
                }

                $commissionType = isset($item['CommissionType']) ? (int) $item['CommissionType'] : null;
                $trade = isset($item['Trade']) ? (int) $item['Trade'] : null;
                $execution = isset($item['Execution']) ? (int) $item['Execution'] : null;

                $row = [
                    'securityId' => $securityId,
                    'symbolName' => $name,
                    'symbolDescription' => $symbolDescription,
                    'trading_id' => $externalSymbolId,
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'Source' => isset($item['Source']) ? trim((string) $item['Source']) : null,
                    'Sescription' => isset($item['Sescription']) ? trim((string) $item['Sescription']) : null,
                    'Digits' => isset($item['Digits']) ? (string) $item['Digits'] : null,
                    'Currency' => isset($item['Currency']) ? trim((string) $item['Currency']) : null,
                    'MarginCurrency' => isset($item['MarginCurrency']) ? trim((string) $item['MarginCurrency']) : null,
                    'CommissionType' => $commissionType,
                    'MinLots' => $this->normalizeDecimal($item['MinLots'] ?? null),
                    'MaxLots' => $this->normalizeDecimal($item['MaxLots'] ?? null),
                    'LotsStep' => $this->normalizeDecimal($item['LotsStep'] ?? null),
                    'ContractSize' => $this->normalizeDecimal($item['ContractSize'] ?? null),
                    'InitialMargin' => $this->normalizeDecimal($item['InitialMargin'] ?? null),
                    'Maintenance' => $this->normalizeDecimal($item['Maintenance'] ?? null),
                    'Hedged' => $this->normalizeDecimal($item['Hedged'] ?? null),
                    'TickSize' => $this->normalizeDecimal($item['TickSize'] ?? null),
                    'TickPrice' => $this->normalizeDecimal($item['TickPrice'] ?? null),
                    'SpreadByDefault' => $this->normalizeDecimal($item['SpreadByDefault'] ?? null),
                    'LimitStopLevel' => $this->normalizeDecimal($item['LimitStopLevel'] ?? null),
                    'Trade' => $trade,
                    'Execution' => $execution,
                    'EnabledMark' => 1,
                    'createdBy' => $adminId
                ];

                $existing = $this->symbolModel->findOne([
                    'trading_platforms_key' => $tradingPlatformsKey,
                    'trading_id' => $externalSymbolId
                ]);
                if ($existing) {
                    $this->symbolModel->update($existing['id'], $row);
                } else {
                    $this->symbolModel->create($row);
                }
                $syncedSymbolTradingIds[] = $externalSymbolId;
            }

            if (!empty($syncedSecurityTradingIds)) {
                $this->disableFinanceProProductsNotInSync('ibCustomSecurities', $tradingPlatformsKey, $syncedSecurityTradingIds);
            }
            if (!empty($syncedSymbolTradingIds)) {
                $this->disableFinanceProProductsNotInSync('ibCustomSymbols', $tradingPlatformsKey, $syncedSymbolTradingIds);
            }

            IbProductSyncService::ensureExchangeSettingsForPlatform($tradingPlatformsKey, $adminId);

            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            IbSettingsOperationLog::logFailure($input, 'import', 'ibSettingsSyncProductsFailure', $e->getMessage());
            Response::error('Trading Platform sync failed: ' . $e->getMessage(), 500);
        }

        $syncTime = $resData['syncTime'] ?? null;
        IbSettingsOperationLog::logSyncSuccess($input, $platformKey, count($securitiesList), count($symbolsList));
        Response::success([
            'success' => true,
            'securitiesCount' => count($securitiesList),
            'symbolsCount' => count($symbolsList),
            'syncTime' => $syncTime
        ], 'Sync completed successfully.');
    }

    /**
     * FinancePro 同步：将本次未返回的 trading_id 标记为不可用（EnabledMark=0）
     */
    private function disableFinanceProProductsNotInSync(string $table, string $platformKey, array $syncedTradingIds): void
    {
        $syncedTradingIds = array_values(array_unique(array_filter(array_map('intval', $syncedTradingIds), static function ($id) {
            return $id > 0;
        })));
        if (empty($syncedTradingIds)) {
            return;
        }

        $db = Database::getInstance();
        $placeholders = [];
        $params = ['platform' => $platformKey];
        foreach ($syncedTradingIds as $index => $tradingId) {
            $paramKey = 'tid' . $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $tradingId;
        }

        $inList = implode(', ', $placeholders);
        $sql = "UPDATE {$table}
                SET EnabledMark = 0
                WHERE trading_platforms_key = :platform
                  AND trading_id IS NOT NULL
                  AND trading_id NOT IN ({$inList})";
        $db->query($sql, $params);
    }

    // decimal 列：FinancePro 可能返回空串/非数字，直接入库会被 MySQL 拒绝，统一归一成 null
    private function normalizeDecimal($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }

    /**
     * 投递任务到配置的 myswoole TCP 服务。
     *
     * @param array $payload
     * @return void
     */
    private function dispatchSwooleTask(array $payload): void
    {
        $address = config_swoole_address();
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, 1.0);
        if (!$socket) {
            throw new Exception('Failed to connect myswoole: ' . $errstr . ' (' . $errno . ')');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fclose($socket);
            throw new Exception('Failed to encode task payload');
        }

        // myswoole Service.php 配置了 package_eof = "$$$###"
        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new Exception('Failed to send task to myswoole');
        }
    }

    /**
     * 查询同步进度
     * GET /api/ib-settings/sync-progress?platformKey=mt5
     */
    public function syncProgress() {
        $this->requireAuth();
        $platformKey = isset($_GET['platformKey']) ? strtolower(trim($_GET['platformKey'])) : '';
        if ($platformKey === '') {
            Response::error('platformKey is required', 400);
        }

        require_once __DIR__ . '/../services/IbProductSyncService.php';
        $progress = IbProductSyncService::getProgress($platformKey);

        if ($progress === null) {
            Response::success([
                'status' => 'idle',
                'message' => 'No sync task in progress',
                'percent' => 0,
            ]);
            return;
        }

        Response::success($progress);
    }

    /**
     * 品种汇率列表
     * GET /api/ib-settings/symbol-exchange-rates
     */
    public function getSymbolExchangeRates() {
        AuthMiddleware::checkPermission('page_ib_settings_readonly');

        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : null;
        $syncMode = isset($_GET['syncMode']) ? trim((string)$_GET['syncMode']) : null;
        if ($syncMode === '') {
            $syncMode = null;
        }

        $items = $this->symbolExchangeModel->listWithSymbols($search, $syncMode);
        $globalSyncMode = $this->symbolExchangeModel->resolveGlobalSyncMode();

        Response::success([
            'items' => $items,
            'globalSyncMode' => $globalSyncMode,
            'lastRefreshedAt' => gmdate('c'),
        ]);
    }

    /**
     * 创建品种汇率配置
     * POST /api/ib-settings/symbol-exchange-rates
     */
    public function createSymbolExchangeRate() {
        AuthMiddleware::checkPermission('page_ib_settings_create_exchange_rate');
        $input = IbSettingsOperationLog::inputFromRequest();
        $adminId = $this->resolveAdminId();

        $symbolId = isset($input['symbolId']) ? (int)$input['symbolId'] : 0;
        if ($symbolId <= 0 && !empty($input['symbol'])) {
            $symbolRow = $this->symbolModel->findOne([
                'symbolName' => trim((string)$input['symbol']),
                'EnabledMark' => 1,
            ]);
            if ($symbolRow) {
                $symbolId = (int)$symbolRow['id'];
            }
        }
        if ($symbolId <= 0) {
            $this->symbolExchangeError($input, 'add', 'Valid symbolId is required', 422, 'IB_EX_RATE_SYMBOL_ID_REQUIRED');
        }

        $symbol = $this->symbolModel->findById($symbolId);
        if (!$symbol || (int)($symbol['EnabledMark'] ?? 0) !== 1) {
            $this->symbolExchangeError($input, 'add', 'Symbol not found or disabled', 404, 'IB_EX_RATE_SYMBOL_NOT_FOUND');
        }

        $existingActive = $this->symbolExchangeModel->findActiveBySymbolId($symbolId);
        if ($existingActive) {
            $this->symbolExchangeError($input, 'add', 'Exchange setting already exists for this symbol', 409, 'IB_EX_RATE_ALREADY_EXISTS');
        }

        $targetCurrency = strtoupper(trim((string)($input['targetCurrency'] ?? 'USD')));
        if ($targetCurrency === '') {
            $this->symbolExchangeError($input, 'add', 'targetCurrency is required', 422, 'IB_EX_RATE_TARGET_CURRENCY_REQUIRED');
        }

        $syncMode = $input['syncMode'] ?? 'auto';
        if (!in_array($syncMode, ['auto', 'manual'], true)) {
            $this->symbolExchangeError($input, 'add', 'Invalid syncMode', 422, 'IB_EX_RATE_INVALID_SYNC_MODE');
        }

        $baseCurrency = null;
        $exchangeRate = null;
        if ($syncMode === 'manual') {
            $baseCurrency = strtoupper(trim((string)($input['baseCurrency'] ?? '')));
            if ($baseCurrency === '') {
                $this->symbolExchangeError($input, 'add', 'baseCurrency is required in manual mode', 422, 'IB_EX_RATE_MANUAL_BASE_REQUIRED');
            }
            if (!isset($input['exchangeRate']) || (float)$input['exchangeRate'] <= 0) {
                $this->symbolExchangeError($input, 'add', 'exchangeRate must be greater than 0 in manual mode', 422, 'IB_EX_RATE_MANUAL_RATE_REQUIRED');
            }
            $exchangeRate = IbSymbolExchangeSetting::roundExchangeRate($input['exchangeRate']);
        }

        $remarks = isset($input['remarks']) ? trim((string)$input['remarks']) : null;
        if ($remarks !== null && mb_strlen($remarks) > 200) {
            $this->symbolExchangeError($input, 'add', 'Remarks must be at most 200 characters', 422, 'IB_EX_RATE_REMARKS_TOO_LONG');
        }

        $payload = [
            'symbolId' => $symbolId,
            'targetCurrency' => $targetCurrency,
            'baseCurrency' => $baseCurrency,
            'exchangeRate' => $exchangeRate,
            'syncMode' => $syncMode,
            'remarks' => $remarks !== '' ? $remarks : null,
            'updatedBy' => $adminId,
        ];

        $existingSoftDeleted = $this->symbolExchangeModel->findBySymbolId($symbolId);
        if ($existingSoftDeleted && !empty($existingSoftDeleted['deletedAt'])) {
            $id = (int)$existingSoftDeleted['id'];
            $payload['deletedAt'] = null;
            $this->symbolExchangeModel->update($id, $payload);
        } else {
            $id = (int)$this->symbolExchangeModel->create($payload);
        }

        $symbolName = trim((string)($symbol['symbolName'] ?? ''));
        IbSettingsOperationLog::logSymbolExchangeAddSuccess($input, $symbolName);
        $detail = $this->symbolExchangeModel->findDetailById($id);
        Response::created($detail, 'Symbol exchange rate created');
    }

    /**
     * 更新品种汇率配置
     * POST /api/ib-settings/symbol-exchange-rates/{id}
     */
    public function updateSymbolExchangeRate($id) {
        AuthMiddleware::checkPermission('page_ib_settings_edit_exchange_rate');
        $input = IbSettingsOperationLog::inputFromRequest();
        $adminId = $this->resolveAdminId();

        $existing = $this->symbolExchangeModel->findActiveById((int)$id);
        if (!$existing) {
            $this->symbolExchangeError($input, 'edit', 'Symbol exchange rate not found', 404, 'IB_EX_RATE_NOT_FOUND');
        }

        $update = ['updatedBy' => $adminId];

        $effectiveSyncMode = array_key_exists('syncMode', $input)
            ? $input['syncMode']
            : ($existing['syncMode'] ?? 'auto');

        if (array_key_exists('syncMode', $input)) {
            if (!in_array($input['syncMode'], ['auto', 'manual'], true)) {
                $this->symbolExchangeError($input, 'edit', 'Invalid syncMode', 422, 'IB_EX_RATE_INVALID_SYNC_MODE');
            }
            $update['syncMode'] = $input['syncMode'];
        }

        if (array_key_exists('targetCurrency', $input)) {
            $targetCurrency = strtoupper(trim((string)$input['targetCurrency']));
            if ($targetCurrency === '') {
                $this->symbolExchangeError($input, 'edit', 'targetCurrency cannot be empty', 422, 'IB_EX_RATE_TARGET_CURRENCY_EMPTY');
            }
            $update['targetCurrency'] = $targetCurrency;
        }

        if ($effectiveSyncMode === 'manual') {
            $baseCurrency = array_key_exists('baseCurrency', $input)
                ? strtoupper(trim((string)$input['baseCurrency']))
                : strtoupper(trim((string)($existing['baseCurrency'] ?? '')));
            $exchangeRate = array_key_exists('exchangeRate', $input)
                ? $input['exchangeRate']
                : ($existing['exchangeRate'] ?? null);

            if ($baseCurrency === '') {
                $this->symbolExchangeError($input, 'edit', 'baseCurrency is required in manual mode', 422, 'IB_EX_RATE_MANUAL_BASE_REQUIRED');
            }
            if ($exchangeRate === null || $exchangeRate === '' || (float)$exchangeRate <= 0) {
                $this->symbolExchangeError($input, 'edit', 'exchangeRate must be greater than 0 in manual mode', 422, 'IB_EX_RATE_MANUAL_RATE_REQUIRED');
            }
            $update['baseCurrency'] = $baseCurrency;
            $update['exchangeRate'] = IbSymbolExchangeSetting::roundExchangeRate($exchangeRate);
        } elseif (array_key_exists('syncMode', $input) && $input['syncMode'] === 'auto') {
            $update['baseCurrency'] = null;
            $update['exchangeRate'] = null;
        }

        if (array_key_exists('remarks', $input)) {
            $remarks = trim((string)$input['remarks']);
            if (mb_strlen($remarks) > 200) {
                $this->symbolExchangeError($input, 'edit', 'Remarks must be at most 200 characters', 422, 'IB_EX_RATE_REMARKS_TOO_LONG');
            }
            $update['remarks'] = $remarks !== '' ? $remarks : null;
        }

        $this->symbolExchangeModel->update((int)$id, $update);
        $detail = $this->symbolExchangeModel->findDetailById((int)$id);
        $symbolName = trim((string)($detail['symbol'] ?? ''));
        IbSettingsOperationLog::logSymbolExchangeEditSuccess($input, $symbolName);
        Response::success($detail, 'Symbol exchange rate updated');
    }

    /**
     * 删除品种汇率配置
     * DELETE /api/ib-settings/symbol-exchange-rates/{id}
     */
    public function deleteSymbolExchangeRate($id) {
        AuthMiddleware::checkPermission('page_ib_settings_delete_exchange_rate');
        $input = IbSettingsOperationLog::inputFromRequest();

        $detail = $this->symbolExchangeModel->findDetailById((int)$id);
        if (!$detail) {
            $this->symbolExchangeError($input, 'delete', 'Symbol exchange rate not found', 404, 'IB_EX_RATE_NOT_FOUND');
        }

        $symbolName = trim((string)($detail['symbol'] ?? ''));
        $this->symbolExchangeModel->softDeleteActiveById((int)$id, $this->resolveAdminId());
        IbSettingsOperationLog::logSymbolExchangeDeleteSuccess($input, $symbolName);
        Response::success(null, 'Symbol exchange rate deleted');
    }

    /**
     * 切换页级汇率同步模式
     * POST /api/ib-settings/symbol-exchange-rates/global-mode
     */
    public function setSymbolExchangeGlobalMode() {
        AuthMiddleware::checkPermission('page_ib_settings_batch_update_exchange_rate_mode');
        $input = IbSettingsOperationLog::inputFromRequest();
        $adminId = $this->resolveAdminId();

        $mode = isset($input['mode']) ? trim((string)$input['mode']) : '';
        if (!in_array($mode, ['auto', 'manual'], true)) {
            $this->symbolExchangeError($input, 'edit', 'mode must be auto or manual', 422, 'IB_EX_RATE_GLOBAL_MODE_INVALID');
        }

        $this->symbolExchangeModel->batchUpdateSyncMode($mode, $adminId);
        IbSettingsOperationLog::logSymbolExchangeBatchModeSuccess($input, $mode);

        Response::success([
            'globalSyncMode' => $this->symbolExchangeModel->resolveGlobalSyncMode(),
            'lastRefreshedAt' => gmdate('c'),
        ], 'Global sync mode updated');
    }

    private function symbolExchangeError($input, $operationType, $message, $statusCode = 422, $errorCode = null) {
        IbSettingsOperationLog::logFailure(
            is_array($input) ? $input : [],
            $operationType,
            'ibSettingsSymbolExchangeFailure',
            $message
        );
        Response::error($message, $statusCode, null, $errorCode);
    }

    private function requireAuth() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Unauthorized', 401);
        }
        try {
            JWT::decode($token);
        } catch (Exception $e) {
            Response::error('Unauthorized', 401);
        }
    }

    private function resolveAdminId(): ?int {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return null;
        }
        try {
            $payload = JWT::decode($token);
            return isset($payload['userId']) ? (int)$payload['userId'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 缩小 max_tier_level_count 时：若仍存在 tierLevel > N 的配置行，须先在 Tier Tab 删除。
     */
    private function validateMaxTierLevelCountValue(int $newN): void {
        if ($newN < 1) {
            Response::error('Tier count must be at least 1', 422, null, 'IB_TIER_COUNT_INVALID');
        }
        if ($newN > IbProgramSetting::MAX_TIER_LEVEL_COUNT_SANITY) {
            Response::error('Tier count is too large', 422, null, 'IB_TIER_COUNT_INVALID');
        }

        $tierModel = new IbTierLevel();
        $above = $tierModel->getTierLevelsAbove($newN);
        if (empty($above)) {
            return;
        }

        $levels = array_map(static function ($row) {
            return (int) ($row['tierLevel'] ?? 0);
        }, $above);
        $levels = array_values(array_filter($levels, static function ($n) {
            return $n > 0;
        }));
        $levelStr = implode(', ', $levels);

        Response::error(
            'Cannot reduce tier count: tier level(s) ' . $levelStr . ' still exist. Please delete them in the Tier list first.',
            422,
            null,
            'IB_TIER_COUNT_REDUCE_BLOCKED'
        );
    }
}
