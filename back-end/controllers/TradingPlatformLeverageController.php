<?php
/**
 * Trading Platform Leverage Controller
 * 负责交易平台杠杆选项的用户读取与后台管理
 */

require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/TradingPlatformLeverage.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/RequestInput.php';
require_once __DIR__ . '/../services/OperationLog/PlatformSettingsOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/PlatformSettingsLogSnapshot.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class TradingPlatformLeverageController {
    private $platformModel;
    private $leverageModel;

    public function __construct() {
        $this->platformModel = new TradingPlatform();
        $this->leverageModel = new TradingPlatformLeverage();
    }

    /**
     * 用户侧：按平台获取可用杠杆
     */
    public function getUserLeverages($platformKey) {
        $platformKey = trim((string)$platformKey);
        if ($platformKey === '') {
            Response::success([
                'platform' => null,
                'leverages' => [],
            ], 'Leverages loaded');
        }

        $platform = $this->platformModel->findByKey($platformKey);
        if (!$platform || empty($platform['isEnabled'])) {
            Response::success([
                'platform' => null,
                'leverages' => [],
            ], 'Leverages loaded');
        }

        $leverages = $this->leverageModel->getByPlatform((int)$platform['id'], true);

        Response::success([
            'platform' => [
                'id' => (int)$platform['id'],
                'platformKey' => $platform['platformKey'],
                'displayName' => $platform['displayName'],
                'shortCode' => $platform['shortCode'],
            ],
            'leverages' => $leverages,
        ], 'Leverages loaded');
    }

    /**
     * 管理端：获取全部杠杆
     */
    public function index() {
        $platforms = $this->getConfiguredPlatforms();
        $platformIds = array_map(function ($platform) {
            return (int)$platform['id'];
        }, $platforms);

        $leverages = empty($platformIds)
            ? []
            : $this->leverageModel->getAllWithPlatforms($platformIds, false);

        Response::success([
            'items' => $leverages,
        ], 'Trading platform leverages loaded');
    }

    /**
     * 管理端：获取单条杠杆
     */
    public function show($id) {
        $leverage = $this->findManagedLeverage($id);
        Response::success($leverage, 'Trading platform leverage loaded');
    }

    /**
     * 管理端：新增杠杆
     */
    public function create() {
        $operatorId = $this->resolveOperatorId();
        $data = RequestInput::readJsonBody();
        $input = PlatformSettingsOperationLog::inputFromRequest(is_array($data) ? $data : null);
        $logContext = [
            'input' => $input,
            'operationType' => 'add',
            'failureMethod' => 'platformSettingsLeverageCreateFailure',
            'operatorId' => $operatorId,
        ];

        if (!is_array($data)) {
            $this->logLeverageContextFailure($logContext, 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        $errors = Validator::validateData($data, [
            'platformKey' => 'required|string',
            'leverageValue' => 'required|string',
            'displayLabel' => 'required|string|max:255',
        ]);
        if (!empty($errors)) {
            $this->logLeverageContextFailure($logContext, OperationLogTextHelpers::validationErrorsToMessage($errors));
            Response::validationError($errors);
        }

        $platform = $this->requireConfiguredPlatform($data['platformKey'], false, $logContext);
        $this->assertLeverageManagementAllowed($platform, $logContext);
        $payload = $this->buildValidatedPayload($data, (int)$platform['id'], null, true, $logContext);

        $createdId = $this->leverageModel->create($payload);
        $created = $this->findManagedLeverage($createdId, $logContext);
        PlatformSettingsOperationLog::logLeverageCreateSuccess(
            $input,
            PlatformSettingsLogSnapshot::leverageFromRow($created),
            $operatorId
        );

        Response::created($created, 'Trading platform leverage created successfully');
    }

    /**
     * 管理端：更新杠杆
     */
    public function update($id) {
        $operatorId = $this->resolveOperatorId();
        $input = PlatformSettingsOperationLog::inputFromRequest();
        $logContext = [
            'input' => $input,
            'operationType' => 'edit',
            'failureMethod' => 'platformSettingsLeverageUpdateFailure',
            'operatorId' => $operatorId,
        ];

        $existing = $this->findManagedLeverage($id, $logContext);
        $beforeState = PlatformSettingsLogSnapshot::leverageFromRow($existing);
        $platform = $this->platformModel->findById((int)$existing['platformId']);
        $data = RequestInput::readJsonBody();

        if (!is_array($data)) {
            $this->logLeverageContextFailure($logContext, 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        if ($platform && empty($platform['allowLeverageManagement'])
            && array_key_exists('leverageValue', $data)
            && trim((string)$data['leverageValue']) !== trim((string)$existing['leverageValue'])) {
            $this->logLeverageContextFailure($logContext, 'Leverage value is locked for this platform and cannot be modified.');
            Response::forbidden('Leverage value is locked for this platform and cannot be modified.');
        }

        if (!$this->hasAnyField($data, ['leverageValue', 'displayLabel', 'riskNote', 'displayOrder'])) {
            $this->logLeverageContextFailure($logContext, 'At least one updatable field is required.');
            Response::validationError([
                'payload' => ['At least one updatable field is required.']
            ], 'At least one updatable field is required.');
        }

        $payload = $this->buildValidatedPayload($data, (int)$existing['platformId'], (int)$existing['id'], false, $logContext);

        if (empty($payload)) {
            $this->logLeverageContextFailure($logContext, 'No valid fields were provided for update.');
            Response::validationError([
                'payload' => ['No valid fields were provided for update.']
            ], 'No valid fields were provided for update.');
        }

        $this->leverageModel->update((int)$existing['id'], $payload);
        $updated = $this->findManagedLeverage($existing['id'], $logContext);
        PlatformSettingsOperationLog::logLeverageUpdateSuccess(
            $input,
            $beforeState,
            PlatformSettingsLogSnapshot::leverageFromRow($updated),
            $operatorId
        );

        Response::success($updated, 'Trading platform leverage updated successfully');
    }

    /**
     * 管理端：启用杠杆
     */
    public function enable($id) {
        $operatorId = $this->resolveOperatorId();
        $input = PlatformSettingsOperationLog::inputFromRequest();
        $logContext = [
            'input' => $input,
            'operationType' => 'enable',
            'failureMethod' => 'platformSettingsLeverageToggleFailure',
            'operatorId' => $operatorId,
        ];

        $leverage = $this->findManagedLeverage($id, $logContext);
        $this->leverageModel->update((int)$leverage['id'], ['isEnabled' => 1]);
        $updated = $this->findManagedLeverage($leverage['id'], $logContext);
        PlatformSettingsOperationLog::logLeverageToggleSuccess(
            $input,
            PlatformSettingsLogSnapshot::leverageFromRow($updated),
            true,
            $operatorId
        );
        Response::success($updated, 'Trading platform leverage enabled successfully');
    }

    /**
     * 管理端：禁用杠杆
     */
    public function disable($id) {
        $operatorId = $this->resolveOperatorId();
        $input = PlatformSettingsOperationLog::inputFromRequest();
        $logContext = [
            'input' => $input,
            'operationType' => 'disable',
            'failureMethod' => 'platformSettingsLeverageToggleFailure',
            'operatorId' => $operatorId,
        ];

        $leverage = $this->findManagedLeverage($id, $logContext);
        $this->leverageModel->update((int)$leverage['id'], ['isEnabled' => 0]);
        $updated = $this->findManagedLeverage($leverage['id'], $logContext);
        PlatformSettingsOperationLog::logLeverageToggleSuccess(
            $input,
            PlatformSettingsLogSnapshot::leverageFromRow($updated),
            false,
            $operatorId
        );
        Response::success($updated, 'Trading platform leverage disabled successfully');
    }

    /**
     * 管理端：删除杠杆
     */
    public function delete($id) {
        $operatorId = $this->resolveOperatorId();
        $input = PlatformSettingsOperationLog::inputFromRequest();
        $logContext = [
            'input' => $input,
            'operationType' => 'delete',
            'failureMethod' => 'platformSettingsLeverageDeleteFailure',
            'operatorId' => $operatorId,
        ];

        $leverage = $this->findManagedLeverage($id, $logContext);
        $state = PlatformSettingsLogSnapshot::leverageFromRow($leverage);
        $this->assertLeverageManagementAllowedByPlatformId((int)$leverage['platformId'], $logContext);
        $this->leverageModel->delete((int)$leverage['id']);
        PlatformSettingsOperationLog::logLeverageDeleteSuccess($input, $state, $operatorId);
        Response::success(null, 'Trading platform leverage deleted successfully');
    }

    private function getConfiguredPlatforms() {
        return $this->platformModel->getEnabledPlatforms();
    }

    private function requireConfiguredPlatform($platformKey, $requireEnabledRecord = false, $logContext = null) {
        $platformKey = trim((string)$platformKey);
        $platform = $this->platformModel->findByKey($platformKey);
        if (!$platform) {
            $this->logLeverageContextFailure($logContext, 'Trading platform not found');
            Response::notFound('Trading platform not found');
        }

        if ($requireEnabledRecord && empty($platform['isEnabled'])) {
            $this->logLeverageContextFailure($logContext, 'Trading platform not available');
            Response::notFound('Trading platform not available');
        }

        return $platform;
    }

    private function findManagedLeverage($id, $logContext = null) {
        $leverage = $this->leverageModel->findById((int)$id);
        if (!$leverage) {
            $this->logLeverageContextFailure($logContext, 'Trading platform leverage not found');
            Response::notFound('Trading platform leverage not found');
        }

        $platform = $this->platformModel->findById((int)$leverage['platformId']);
        if (!$platform) {
            $this->logLeverageContextFailure($logContext, 'Trading platform leverage not available');
            Response::notFound('Trading platform leverage not available');
        }

        $leverage['platformKey'] = $platform['platformKey'];
        $leverage['platformName'] = $platform['displayName'];
        $leverage['platformCode'] = $platform['shortCode'];

        return $leverage;
    }

    private function buildValidatedPayload(
        array $data,
        $platformId,
        $excludeId = null,
        $requireMandatoryFields = true,
        $logContext = null
    ) {
        $payload = [];

        if (array_key_exists('leverageValue', $data) || $requireMandatoryFields) {
            $leverageValue = trim((string)($data['leverageValue'] ?? ''));
            if ($leverageValue === '') {
                $this->logLeverageContextFailure($logContext, 'Leverage value is required.');
                Response::validationError([
                    'leverageValue' => ['Leverage value is required.']
                ], 'Leverage value is required.');
            }
            if (!preg_match('/^1:\d+$/', $leverageValue)) {
                $this->logLeverageContextFailure($logContext, 'Leverage value must be in the format 1:number.');
                Response::validationError([
                    'leverageValue' => ['Leverage value must be in the format 1:number.']
                ], 'Leverage value must be in the format 1:number.');
            }

            $duplicate = $this->leverageModel->findByPlatformAndValue((int)$platformId, $leverageValue, $excludeId);
            if ($duplicate) {
                $this->logLeverageContextFailure($logContext, 'This leverage value already exists for the selected platform.');
                Response::validationError([
                    'leverageValue' => ['This leverage value already exists for the selected platform.']
                ], 'This leverage value already exists for the selected platform.');
            }

            $payload['leverageValue'] = $leverageValue;
        }

        if (array_key_exists('displayLabel', $data) || $requireMandatoryFields) {
            $displayLabel = trim((string)($data['displayLabel'] ?? ''));
            if ($displayLabel === '') {
                $this->logLeverageContextFailure($logContext, 'Display label is required.');
                Response::validationError([
                    'displayLabel' => ['Display label is required.']
                ], 'Display label is required.');
            }
            $payload['displayLabel'] = $displayLabel;
        }

        if (array_key_exists('riskNote', $data)) {
            $payload['riskNote'] = trim((string)$data['riskNote']);
        }

        if (array_key_exists('displayOrder', $data)) {
            if ($data['displayOrder'] === '' || !is_numeric($data['displayOrder'])) {
                $this->logLeverageContextFailure($logContext, 'Display order must be numeric.');
                Response::validationError([
                    'displayOrder' => ['Display order must be numeric.']
                ], 'Display order must be numeric.');
            }
            $payload['displayOrder'] = (int)$data['displayOrder'];
        } elseif ($requireMandatoryFields) {
            $payload['displayOrder'] = 0;
        }

        if ($requireMandatoryFields) {
            $payload['platformId'] = (int)$platformId;
            $payload['isEnabled'] = array_key_exists('isEnabled', $data) ? (int)!empty($data['isEnabled']) : 1;
        }

        return $payload;
    }

    /**
     * 锁定平台（如 FP）的杠杆列表必须和外部系统对齐，禁止后台 create / update / delete。
     * 启用 / 禁用走单独的 enable / disable 接口，不受此限制。
     */
    private function assertLeverageManagementAllowed(array $platform, $logContext = null) {
        if (empty($platform['allowLeverageManagement'])) {
            $this->logLeverageContextFailure($logContext, 'Leverage list for this platform is locked and cannot be modified.');
            Response::forbidden('Leverage list for this platform is locked and cannot be modified.');
        }
    }

    private function assertLeverageManagementAllowedByPlatformId($platformId, $logContext = null) {
        $platform = $this->platformModel->findById((int)$platformId);
        if (!$platform) {
            $this->logLeverageContextFailure($logContext, 'Trading platform not found');
            Response::notFound('Trading platform not found');
        }
        $this->assertLeverageManagementAllowed($platform, $logContext);
    }

    private function logLeverageContextFailure($logContext, $message) {
        if (!is_array($logContext)) {
            return;
        }
        PlatformSettingsOperationLog::logFailure(
            $logContext['input'] ?? [],
            $logContext['operationType'] ?? 'edit',
            $logContext['failureMethod'] ?? 'platformSettingsLeverageUpdateFailure',
            $message,
            $logContext['operatorId'] ?? null
        );
    }

    private function resolveOperatorId() {
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            return 0;
        }
        try {
            $payload = JWT::decode($token);
        } catch (Exception $e) {
            return 0;
        }
        return (int) ($payload['userId'] ?? 0);
    }

    private function hasAnyField(array $data, array $fields) {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }

        return false;
    }
}
