<?php
/**
 * External KYC Gateway Controller
 *
 * 管理第三方 KYC 网关（Sumsub 等）的配置和同步：
 *   - 获取列表（默认隐藏 secret）
 *   - 修改配置（含 secret）
 *   - 启用/停用
 *   - 软删除
 *   - 从第三方拉取 level 列表（sync）
 *
 * 暂未提供 create / 硬 delete 接口。
 */

require_once __DIR__ . '/../models/ExternalKycGateway.php';
require_once __DIR__ . '/../models/ExternalKycTemplate.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/KycOperationLogTexts.php';

class ExternalKycGatewayController
{
    private $gatewayModel;
    private $templateModel;

    public function __construct()
    {
        $this->gatewayModel = new ExternalKycGateway();
        $this->templateModel = new ExternalKycTemplate();
    }

    /**
     * 获取所有未软删除的网关列表（默认不返回 secret 字段）
     * GET /api/external-kyc-gateways
     */
    public function index()
    {
        $gateways = $this->gatewayModel->findAll([], 'id ASC');
        Response::success($gateways);
    }

    /**
     * 更新网关配置（支持改 displayName、environment、baseUrl、各类 secret 等）
     * PUT /api/external-kyc-gateways/{id}
     */
    public function update($id)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogKycSettingsFromRequest($input);
        $opLog = new AdminOperationLogWriter();

        $gateway = $this->gatewayModel->findById($id);
        if (!$gateway) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayUpdateFailure('Gateway not found');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::notFound('Gateway not found');
        }
        $updateData = [];

        // 允许修改的字段（不允许通过这个接口直接改 provider / isEnabled / deletedAt）
        $editableFields = [
            'displayName',
            'environment',
            'baseUrl',
            'appToken',
            'secretKey',
            'webhookSecret',
            'iframeBaseUrl',
            'returnUrl',
            'detailUrl',
        ];
        foreach ($editableFields as $field) {
            if (array_key_exists($field, $input)) {
                $updateData[$field] = $input[$field];
            }
        }

        // configData 允许传对象/数组，落库时序列化
        if (array_key_exists('configData', $input)) {
            $updateData['configData'] = is_array($input['configData'])
                ? json_encode($input['configData'])
                : $input['configData'];
        }

        if (empty($updateData)) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayUpdateFailure('No valid fields to update');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('No valid fields to update', 400);
        }

        $changedFields = $this->collectGatewayChangedFields($gateway, $input, $updateData);

        $currentUser = AuthMiddleware::getCurrentUser();
        if ($currentUser && isset($currentUser['userId'])) {
            $updateData['updatedBy'] = (int)$currentUser['userId'];
        }

        $this->gatewayModel->update($id, $updateData);

        if (!empty($changedFields)) {
            $label = KycOperationLogTexts::resolveGatewayLabel($gateway);
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayUpdate($label, $changedFields);
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
        }

        Response::success($this->gatewayModel->findById($id), 'Gateway updated');
    }

    /**
     * 启用/停用网关
     * PUT /api/external-kyc-gateways/{id}/enabled
     * Body: { "isEnabled": true|false }
     */
    public function setEnabled($id)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogKycSettingsFromRequest($input);
        $opLog = new AdminOperationLogWriter();

        $gateway = $this->gatewayModel->findById($id);
        if (!$gateway) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayEnableFailure('Gateway not found');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::notFound('Gateway not found');
        }

        if (!array_key_exists('isEnabled', $input)) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayEnableFailure('isEnabled is required');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('isEnabled is required', 400);
        }

        $updateData = ['isEnabled' => $input['isEnabled'] ? 1 : 0];
        $enabled = !empty($input['isEnabled']);
        $currentUser = AuthMiddleware::getCurrentUser();
        if ($currentUser && isset($currentUser['userId'])) {
            $updateData['updatedBy'] = (int)$currentUser['userId'];
        }

        $this->gatewayModel->update($id, $updateData);

        if ((int) ($gateway['isEnabled'] ?? 0) !== (int) $updateData['isEnabled']) {
            $label = KycOperationLogTexts::resolveGatewayLabel($gateway);
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayEnable($label, $enabled);
            $opType = $enabled ? 'enable' : 'disable';
            $opLog->logKycSettingsMutation($subModule, $opType, $detailZh, $detailEn);
        }

        Response::success($this->gatewayModel->findById($id), 'Gateway status updated');
    }

    /**
     * 软删除网关
     * DELETE /api/external-kyc-gateways/{id}
     */
    public function softDelete($id)
    {
        $subModule = OperationLogPages::resolveLogKycSettingsFromRequest();
        $opLog = new AdminOperationLogWriter();

        $gateway = $this->gatewayModel->findById($id);
        if (!$gateway) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewayDeleteFailure('Gateway not found');
            $opLog->logKycSettingsMutation($subModule, 'delete', $detailZh, $detailEn);
            Response::notFound('Gateway not found');
        }

        $this->gatewayModel->softDelete($id);

        $label = KycOperationLogTexts::resolveGatewayLabel($gateway);
        list($detailZh, $detailEn) = KycOperationLogTexts::gatewayDelete($label);
        $opLog->logKycSettingsMutation($subModule, 'delete', $detailZh, $detailEn);

        Response::success(null, 'Gateway deleted');
    }

    /**
     * 根据 externalTemplateId 拿"这条等级 + 它所属的 gateway"轻量信息。
     * 给 kycTemplates 详情页用：开启第三方时只展示当前绑定，不需要拉全量 gateway 列表。
     *
     * GET /api/external-kyc-gateways/template/{externalTemplateId}
     */
    public function showTemplateWithGateway($externalTemplateId)
    {
        $tpl = $this->templateModel->findById((int)$externalTemplateId);
        if (!$tpl) {
            Response::notFound('External template not found');
            return;
        }

        // 拿 gateway 的非敏感字段（findById 会自动隐藏 secret）
        $gateway = $this->gatewayModel->findById((int)$tpl['gatewayId']);
        $tpl['gateway'] = $gateway ?: null;

        Response::success($tpl);
    }

    /**
     * 获取某个 gateway 下已同步的 level / template 列表
     * GET /api/external-kyc-gateways/{id}/templates
     */
    public function listTemplates($id)
    {
        $gateway = $this->gatewayModel->findById($id);
        if (!$gateway) {
            Response::notFound('Gateway not found');
        }

        $templates = $this->templateModel->findAll(
            ['gatewayId' => (int)$id],
            'id ASC'
        );
        Response::success($templates);
    }

    /**
     * 从第三方平台同步 level / template 列表，写入 externalKycTemplates
     * POST /api/external-kyc-gateways/{id}/sync
     */
    public function sync($id)
    {
        $subModule = OperationLogPages::resolveLogKycSettingsFromRequest();
        $opLog = new AdminOperationLogWriter();

        $gateway = $this->gatewayModel->findByIdWithSecrets($id);
        if (!$gateway) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewaySyncFailure('Gateway not found');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::notFound('Gateway not found');
        }
        if (empty($gateway['isEnabled'])) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewaySyncFailure('Gateway is not enabled');
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('Gateway is not enabled', 400);
        }

        try {
            $service = $this->resolveProviderService($gateway);
            $levels = $service->fetchLevels();
        } catch (Exception $e) {
            list($detailZh, $detailEn) = KycOperationLogTexts::gatewaySyncFailure('Sync failed: ' . $e->getMessage());
            $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);
            Response::error('Sync failed: ' . $e->getMessage(), 500);
            return;
        }

        $created = 0;
        $updated = 0;
        $restored = 0;
        $seenIds = [];
        foreach ($levels as $level) {
            $levelId = $this->resolveLevelId($gateway['provider'], $level);
            $levelName = $this->resolveLevelName($gateway['provider'], $level);
            // Sumsub 一定返回 id 和 name，缺任何一个的数据不可信，跳过
            if (!$levelId || !$levelName) {
                continue;
            }

            $result = $this->templateModel->upsertFromSync(
                (int)$gateway['id'],
                $levelId,
                [
                    'externalLevelName'  => $levelName,
                    'displayName'        => $this->resolveDisplayName($gateway['provider'], $level, $levelName),
                    // Sumsub 用 desc 字段（不是 description）
                    'description'        => $level['desc'] ?? ($level['description'] ?? null),
                    'applicantType'      => $level['applicantType'] ?? null,
                    'docTypesSummary'    => $this->summarizeDocTypes($level),
                    'externalUpdatedAt'  => $level['modifiedAt'] ?? null,
                    // Sumsub 用 inactive=true 表示停用，取反映射到我们的 isActive
                    'isActive'           => empty($level['inactive']) ? 1 : 0,
                ]
            );

            if (!empty($result['id'])) {
                $seenIds[] = (int)$result['id'];
            }
            switch ($result['action'] ?? '') {
                case 'created':  $created++;  break;
                case 'restored': $restored++; break;
                default:         $updated++;  break;
            }
        }

        // 本次没看到的等级 → 软删（不真删，避免破坏 kycTemplates 上的 FK 关联）
        $deleted = $this->templateModel->softDeleteMissing((int)$gateway['id'], $seenIds);

        $label = KycOperationLogTexts::resolveGatewayLabel($gateway);
        list($detailZh, $detailEn) = KycOperationLogTexts::gatewaySync($label);
        $opLog->logKycSettingsMutation($subModule, 'edit', $detailZh, $detailEn);

        Response::success([
            'provider' => $gateway['provider'],
            'gatewayId' => (int)$gateway['id'],
            'fetched'   => count($levels),
            'created'   => $created,
            'updated'   => $updated,
            'restored'  => $restored,
            'deleted'   => $deleted,
        ], 'Sync completed');
    }

    /**
     * 根据 provider 实例化对应的 service。
     * 目前只支持 sumsub，后续接 jumio/onfido 等再在这里扩。
     */
    private function resolveProviderService(array $gateway)
    {
        $provider = strtolower((string)($gateway['provider'] ?? ''));
        switch ($provider) {
            case 'sumsub':
                require_once __DIR__ . '/../services/SumsubService.php';
                return new SumsubService($gateway);
            default:
                throw new Exception("Provider [{$provider}] is not supported yet");
        }
    }

    private function resolveLevelName($provider, $level)
    {
        // Sumsub 用 name 作为后续调用的关键参数
        return $level['name'] ?? ($level['levelName'] ?? null);
    }

    private function resolveLevelId($provider, $level)
    {
        return $level['id'] ?? ($level['levelId'] ?? null);
    }

    private function resolveDisplayName($provider, $level, $fallback)
    {
        return $level['displayName']
            ?? ($level['title'] ?? $fallback);
    }

    /**
     * 把 requiredIdDocs.docSets 里所有 idDocSetType 抓出来用逗号拼接，
     * 例如 "IDENTITY,SELFIE,PROOF_OF_RESIDENCE,QUESTIONNAIRE"，
     * 让前端能在卡片上一眼看出这个 level 需要哪些文档。
     */
    private function summarizeDocTypes($level)
    {
        $docSets = $level['requiredIdDocs']['docSets'] ?? null;
        if (!is_array($docSets) || empty($docSets)) {
            return null;
        }
        $types = [];
        foreach ($docSets as $set) {
            if (!empty($set['idDocSetType'])) {
                $types[] = $set['idDocSetType'];
            }
        }
        return $types ? implode(',', $types) : null;
    }

    /**
     * 对比网关更新前后，返回用于日志的变更字段名（secret 类合并为 api_credentials）。
     *
     * @return string[]
     */
    private function collectGatewayChangedFields(array $gateway, array $input, array $updateData)
    {
        $changed = [];
        $credentialFields = ['appToken', 'secretKey', 'webhookSecret'];
        $credentialChanged = false;

        foreach ($updateData as $field => $newValue) {
            if ($field === 'updatedBy') {
                continue;
            }
            if (in_array($field, $credentialFields, true)) {
                if (($gateway[$field] ?? null) != $newValue) {
                    $credentialChanged = true;
                }
                continue;
            }
            $oldValue = $gateway[$field] ?? null;
            if ($field === 'configData' && is_string($oldValue)) {
                $decoded = json_decode($oldValue, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $oldValue = $decoded;
                }
            }
            if ($oldValue != $newValue) {
                $changed[] = $field;
            }
        }

        if ($credentialChanged) {
            $changed[] = 'api_credentials';
        }

        return array_values(array_unique($changed));
    }
}
