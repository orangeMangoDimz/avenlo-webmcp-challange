<?php
/**
 * Withdrawal Verification Template Controller
 */

require_once __DIR__ . '/../models/WithdrawalVerificationTemplate.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestionCategory.php';
require_once __DIR__ . '/../models/WithdrawalVerificationQuestion.php';
require_once __DIR__ . '/../models/WithdrawalVerificationConditionalRule.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplateDocument.php';
require_once __DIR__ . '/../models/WithdrawalVerificationTemplateEditHistory.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationSubmission.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationAnswer.php';
require_once __DIR__ . '/../models/PaymentSupportQuestion.php';
require_once __DIR__ . '/../models/PaymentGatewaySetting.php';
require_once __DIR__ . '/../models/PaymentGatewayFundingSetting.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../services/OperationLog/WithdrawKycTemplateOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/TransactionOperationLogTexts.php';

class WithdrawalVerificationTemplateController {
    private const DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES = 2;
    private const MAX_GATEWAY_AMOUNT_DECIMAL_PLACES = 4;

    private $templateModel;
    private $categoryModel;
    private $questionModel;
    private $ruleModel;
    private $documentModel;
    private $historyModel;
    private $submissionModel;
    private $answerModel;
    private $paymentSupportQuestionModel;
    private $gatewayModel;
    private $gatewayFeeModel;

    public function __construct() {
        $this->templateModel = new WithdrawalVerificationTemplate();
        $this->categoryModel = new WithdrawalVerificationQuestionCategory();
        $this->questionModel = new WithdrawalVerificationQuestion();
        $this->ruleModel = new WithdrawalVerificationConditionalRule();
        $this->documentModel = new WithdrawalVerificationTemplateDocument();
        $this->historyModel = new WithdrawalVerificationTemplateEditHistory();
        $this->submissionModel = new ClientWithdrawalVerificationSubmission();
        $this->answerModel = new ClientWithdrawalVerificationAnswer();
        $this->paymentSupportQuestionModel = new PaymentSupportQuestion();
        $this->gatewayModel = new PaymentGatewaySetting();
        $this->gatewayFeeModel = new PaymentGatewayFundingSetting();
    }

    public function index() {
        $filters = [];

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['gatewaySettingId'])) {
            $filters['gatewaySettingId'] = $_GET['gatewaySettingId'];
        }

        if (isset($_GET['gatewayKey'])) {
            $filters['gatewayKey'] = $_GET['gatewayKey'];
        }

        $templates = $this->templateModel->getTemplatesSummary($filters);

        Response::success([
            'templates' => $templates,
            'total' => count($templates)
        ]);
    }

    public function show($id) {
        $template = $this->templateModel->getTemplateDetails($id);

        if (!$template) {
            Response::notFound('Template not found');
        }

        Response::success($template);
    }

    public function getGatewayTemplatePrefill($gatewaySettingId) {
        $this->respondGatewayTemplatePayments($gatewaySettingId, true);
    }

    public function getGatewayDepositPrefill($gatewaySettingId) {
        $this->respondGatewayTemplatePayments($gatewaySettingId, false);
    }

    private function respondGatewayTemplatePayments($gatewaySettingId, $isWithdraw = true) {
        $currentUser = AuthMiddleware::getCurrentUser();
        $clientId = (int)($currentUser['userId'] ?? 0);
        $gatewayFeeSettings = $this->gatewayFeeModel->getByGatewaySettingId((int)$gatewaySettingId, true);

        if ($clientId <= 0) {
            Response::unauthorized('Invalid current user');
        }

        $gateway = $this->gatewayModel->findById((int)$gatewaySettingId);

        $templates = $this->templateModel->getTemplatesSummary([
            'gatewaySettingId' => (int)$gatewaySettingId,
            'status' => 'active'
        ]);

        $template = $templates[0] ?? null;
        if (!$template) {
            $fallbackTemplates = $this->templateModel->getTemplatesSummary([
                'gatewaySettingId' => (int)$gatewaySettingId
            ]);
            $template = $fallbackTemplates[0] ?? null;
        }

        if (!$template) {
            Response::success([
                'template' => [
                    'templateId' => null,
                    'gatewaySettingId' => (int)$gatewaySettingId,
                    'gatewayKey' => $gateway['gatewayKey'] ?? null,
                    'gatewayName' => $gateway['gatewayName'] ?? null,
                    'type' => $gateway['type'] ?? null,
                    'iconClass' => $gateway['iconClass'] ?? null,
                    'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
                    'content' => $isWithdraw ? ($gateway['withdrawalContent'] ?? null) : ($gateway['depositContent'] ?? null),
                    'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies'] ?? null),
                    'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies'] ?? null),
                    'templateName' => null,
                    'description' => null,
                    'status' => null,
                    'prewithdrawenabled' => $isWithdraw ? false : null,
                    'predepositenabled' => $isWithdraw ? null : false,
                    'questions' => $this->paymentSupportQuestionModel->getGatewayQuestions(
                        (int)$gatewaySettingId,
                        $isWithdraw ? PaymentSupportQuestion::SCOPE_WITHDRAW : PaymentSupportQuestion::SCOPE_DEPOSIT
                    ),
                    'fees' => $this->formatGatewayFeePayload($gatewayFeeSettings, $isWithdraw),
                    'payment' => []
                ]
            ]);
        }

        if (($template['status'] ?? null) !== 'active') {
            Response::success([
                'template' => [
                    'templateId' => (int)$template['templateId'],
                    'gatewaySettingId' => (int)$template['gatewaySettingId'],
                    'gatewayKey' => $template['gatewayKey'] ?? null,
                    'gatewayName' => $template['gatewayName'] ?? null,
                    'type' => $gateway['type'] ?? null,
                    'iconClass' => $template['iconClass'] ?? null,
                    'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
                    'content' => $isWithdraw ? ($gateway['withdrawalContent'] ?? null) : ($gateway['depositContent'] ?? null),
                    'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies'] ?? null),
                    'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies'] ?? null),
                    'templateName' => $template['templateName'] ?? null,
                    'description' => $template['description'] ?? null,
                    'status' => $template['status'] ?? null,
                    'prewithdrawenabled' => $isWithdraw ? false : null,
                    'predepositenabled' => $isWithdraw ? null : false,
                    'questions' => $this->paymentSupportQuestionModel->getGatewayQuestions(
                        (int)$gatewaySettingId,
                        $isWithdraw ? PaymentSupportQuestion::SCOPE_WITHDRAW : PaymentSupportQuestion::SCOPE_DEPOSIT
                    ),
                    'fees' => $this->formatGatewayFeePayload($gatewayFeeSettings, $isWithdraw),
                    'payment' => []
                ]
            ]);
        }

        if (!$isWithdraw) {
            Response::success([
                'template' => [
                    'templateId' => (int)$template['templateId'],
                    'gatewaySettingId' => (int)$template['gatewaySettingId'],
                    'gatewayKey' => $template['gatewayKey'] ?? null,
                    'gatewayName' => $template['gatewayName'] ?? null,
                    'type' => $gateway['type'] ?? null,
                    'iconClass' => $template['iconClass'] ?? null,
                    'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
                    'content' => $gateway['depositContent'] ?? null,
                    'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies'] ?? null),
                    'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies'] ?? null),
                    'templateName' => $template['templateName'] ?? null,
                    'description' => $template['description'] ?? null,
                    'status' => $template['status'] ?? null,
                    'prewithdrawenabled' => null,
                    'predepositenabled' => false,
                    'questions' => $this->paymentSupportQuestionModel->getGatewayQuestions(
                        (int)$gatewaySettingId,
                        PaymentSupportQuestion::SCOPE_DEPOSIT
                    ),
                    'fees' => $this->formatGatewayFeePayload($gatewayFeeSettings, $isWithdraw),
                    'payment' => []
                ]
            ]);
        }

        $submissions = array_values(array_filter(
            $this->submissionModel->getClientSubmissions($clientId),
            function ($submission) use ($template, $gatewaySettingId) {
                $matchesTemplate = (int)$submission['templateId'] === (int)$template['templateId']
                    || (int)$submission['gatewaySettingId'] === (int)$gatewaySettingId;

                return $matchesTemplate;
            }
        ));

        Response::success([
            'template' => [
                'templateId' => (int)$template['templateId'],
                'gatewaySettingId' => (int)$template['gatewaySettingId'],
                'gatewayKey' => $template['gatewayKey'] ?? null,
                'gatewayName' => $template['gatewayName'] ?? null,
                'type' => $gateway['type'] ?? null,
                'iconClass' => $template['iconClass'] ?? null,
                'amountDecimalPlaces' => $this->resolveGatewayAmountDecimalPlaces($gateway),
                'content' => $gateway['withdrawalContent'] ?? null,
                'supportedFiatCurrencies' => $this->parseJsonField($gateway['supportedFiatCurrencies'] ?? null),
                'supportedCryptoCurrencies' => $this->parseJsonField($gateway['supportedCryptoCurrencies'] ?? null),
                'templateName' => $template['templateName'] ?? null,
                'description' => $template['description'] ?? null,
                'status' => $template['status'] ?? null,
                'prewithdrawenabled' => true,
                'predepositenabled' => null,
                'questions' => [],
                'fees' => $this->formatGatewayFeePayload($gatewayFeeSettings, $isWithdraw),
                'payment' => array_map(function ($submission) {
                    $status = (string)($submission['submissionStatus'] ?? '');
                    if (in_array($status, ['draft', 'incomplete'], true)) {
                        return [
                            'id' => (int)$submission['id'],
                            'templateId' => (int)$submission['templateId'],
                            'gatewaySettingId' => (int)$submission['gatewaySettingId'],
                            'paymentMethodId' => $submission['paymentMethodId'] !== null ? (int)$submission['paymentMethodId'] : null,
                            'submissionStatus' => $submission['submissionStatus'],
                            'submittedAt' => $submission['submittedAt'],
                            'createdAt' => $submission['createdAt'],
                            'updatedAt' => $submission['updatedAt'],
                            'detail' => []
                        ];
                    }

                    $answers = $this->answerModel->getSubmissionAnswers((int)$submission['id']);
                    $detail = [];

                    foreach ($answers as $answer) {
                        if ((int)($answer['isLocked'] ?? 0) !== 1) {
                            continue;
                        }

                        $scope = trim((string)($answer['scope'] ?? ''));
                        if ($scope === '') {
                            continue;
                        }

                        $detail[$scope] = $this->answerModel->getAnswerValue($answer);
                    }

                    return [
                        'id' => (int)$submission['id'],
                        'templateId' => (int)$submission['templateId'],
                        'gatewaySettingId' => (int)$submission['gatewaySettingId'],
                        'paymentMethodId' => $submission['paymentMethodId'] !== null ? (int)$submission['paymentMethodId'] : null,
                        'submissionStatus' => $submission['submissionStatus'],
                        'submittedAt' => $submission['submittedAt'],
                        'createdAt' => $submission['createdAt'],
                        'updatedAt' => $submission['updatedAt'],
                        'detail' => $detail
                    ];
                }, $submissions)
            ]
        ]);
    }

    private function formatGatewayFeePayload($feeSettings, $isWithdraw) {
        if (!$feeSettings || empty($feeSettings['isActive'])) {
            return null;
        }

        $payload = [
            'calculationMode' => $feeSettings['calculationMode'] ?? 'single',
            'minAmount' => $isWithdraw
                ? ($feeSettings['minWithdrawal'] ?? null)
                : ($feeSettings['minDeposit'] ?? null),
            'maxAmount' => $isWithdraw
                ? ($feeSettings['maxWithdrawal'] ?? null)
                : ($feeSettings['maxDeposit'] ?? null),
            'rules' => array_values(array_filter($feeSettings['feeRules'] ?? [], function ($rule) use ($isWithdraw) {
                $transactionType = $isWithdraw ? 'withdrawal' : 'deposit';
                return ($rule['transactionType'] ?? null) === $transactionType;
            }))
        ];

        return $payload;
    }

    private function parseJsonField($field) {
        if (empty($field)) {
            return [];
        }

        if (is_string($field)) {
            $parsed = json_decode($field, true);
            return is_array($parsed) ? $parsed : [];
        }

        if (is_array($field)) {
            return $field;
        }

        return [];
    }

    private function resolveGatewayAmountDecimalPlaces(array $gateway) {
        $value = $gateway['amountDecimalPlaces'] ?? null;
        if ($value === null || $value === '') {
            return self::DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES;
        }

        $decimalPlaces = (int)$value;
        if ($decimalPlaces < 0 || $decimalPlaces > self::MAX_GATEWAY_AMOUNT_DECIMAL_PLACES) {
            return self::DEFAULT_GATEWAY_AMOUNT_DECIMAL_PLACES;
        }

        return $decimalPlaces;
    }

    public function create() {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        if (empty($input['gatewaySettingId']) || empty($input['name'])) {
            Response::validationError([
                'gatewaySettingId' => empty($input['gatewaySettingId']) ? 'gatewaySettingId is required' : null,
                'name' => empty($input['name']) ? 'name is required' : null
            ]);
        }

        $data = [
            'gatewaySettingId' => (int)$input['gatewaySettingId'],
            'templateName' => $input['name'],
            'description' => $input['description'] ?? null,
            'status' => $input['status'] ?? 'draft',
            'isAutoApproveEnabled' => !empty($input['isAutoApproveEnabled']) ? 1 : 0,
            'requireDocumentSignature' => array_key_exists('requireDocumentSignature', $input)
                ? (!empty($input['requireDocumentSignature']) ? 1 : 0)
                : 1,
            'displayOrder' => $input['displayOrder'] ?? 0,
            'createdBy' => $adminId
        ];

        try {
            $templateId = $this->templateModel->create($data);

            $this->historyModel->logChange(
                $templateId,
                'template_created',
                "Template '{$input['name']}' created",
                $adminId
            );

            Response::created(
                $this->templateModel->getTemplateDetails($templateId),
                'Template created successfully'
            );
        } catch (Exception $e) {
            Response::error('Failed to create template: ' . $e->getMessage(), 500);
        }
    }

    public function update($id) {
        $template = $this->templateModel->findById($id);
        if (!$template) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $this->logTemplateUpdateFailure($input, (int) $id, 'Template not found');
            Response::notFound('Template not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $currentUser = AuthMiddleware::getCurrentUser();
        $adminId = $currentUser['userId'] ?? null;

        $data = [];

        if (isset($input['gatewaySettingId'])) {
            $data['gatewaySettingId'] = (int)$input['gatewaySettingId'];
        }
        if (isset($input['templateName'])) {
            $data['templateName'] = $input['templateName'];
        }
        if (isset($input['description'])) {
            $data['description'] = $input['description'];
        }
        if (isset($input['status'])) {
            $data['status'] = $input['status'];
        }
        if (isset($input['isAutoApproveEnabled'])) {
            $data['isAutoApproveEnabled'] = !empty($input['isAutoApproveEnabled']) ? 1 : 0;
        }
        if (isset($input['requireDocumentSignature'])) {
            $data['requireDocumentSignature'] = !empty($input['requireDocumentSignature']) ? 1 : 0;
        }
        if (isset($input['displayOrder'])) {
            $data['displayOrder'] = $input['displayOrder'];
        }
        $data['updatedBy'] = $adminId;

        try {
            $this->templateModel->update($id, $data);

            $this->historyModel->logChange(
                $id,
                'template_updated',
                "Template '{$template['templateName']}' updated",
                $adminId
            );

            $updatedTemplate = $this->templateModel->getTemplateDetails($id);
            $this->logTemplateUpdate($input, (int) $id, $template, is_array($updatedTemplate) ? $updatedTemplate : $template);

            Response::success(
                $updatedTemplate,
                'Template updated successfully'
            );
        } catch (Exception $e) {
            $this->logTemplateUpdateFailure($input, (int) $id, 'Failed to update template: ' . $e->getMessage());
            Response::error('Failed to update template: ' . $e->getMessage(), 500);
        }
    }

    public function clone($id) {
        $template = $this->templateModel->findById($id);
        if (!$template) {
            Response::notFound('Template not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $newName = $input['newName'] ?? ($template['templateName'] . ' (Copy)');

        try {
            $newTemplateId = $this->templateModel->cloneTemplate($id, $newName);
            Response::created(
                $this->templateModel->getTemplateDetails($newTemplateId),
                'Template cloned successfully'
            );
        } catch (Exception $e) {
            Response::error('Failed to clone template: ' . $e->getMessage(), 500);
        }
    }

    public function getHistory($id) {
        $template = $this->templateModel->findById($id);
        if (!$template) {
            Response::notFound('Template not found');
        }

        $limit = $_GET['limit'] ?? 50;
        $history = $this->historyModel->getTemplateHistory($id, $limit);

        Response::success([
            'history' => $history,
            'total' => count($history)
        ]);
    }

    public function statistics() {
        $sql = "SELECT
                    COUNT(*) AS totalTemplates,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS activeTemplates,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draftTemplates
                FROM withdrawalVerificationTemplates
                WHERE deletedAt IS NULL";

        Response::success($this->templateModel->queryOne($sql));
    }

    private function logTemplateUpdate($input, $id, $template, $updatedTemplate) {
        $meta = TransactionOperationLogTexts::resolveWithdrawKycTemplateMeta($id);
        $tplName = (string) ($updatedTemplate['templateName'] ?? $template['templateName'] ?? '');
        $gateway = $meta['gateway'];

        $onlyAuto = isset($input['isAutoApproveEnabled'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['requireDocumentSignature'])
            && !isset($input['displayOrder'])
            && !isset($input['gatewaySettingId']);
        if ($onlyAuto) {
            $on = filter_var($input['isAutoApproveEnabled'], FILTER_VALIDATE_BOOLEAN);
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycToggleAutoApprove($tplName, $on, $gateway);
            WithdrawKycTemplateOperationLog::logMutation($input, $on ? 'enable' : 'disable', $id, $detailZh, $detailEn);
            return;
        }

        $onlyDocSig = isset($input['requireDocumentSignature'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['isAutoApproveEnabled'])
            && !isset($input['displayOrder'])
            && !isset($input['gatewaySettingId']);
        if ($onlyDocSig) {
            $on = filter_var($input['requireDocumentSignature'], FILTER_VALIDATE_BOOLEAN);
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycToggleDocSignature($tplName, $on, $gateway);
            WithdrawKycTemplateOperationLog::logMutation($input, $on ? 'enable' : 'disable', $id, $detailZh, $detailEn);
            return;
        }

        if (isset($input['templateName']) && (string) $input['templateName'] !== (string) ($template['templateName'] ?? '')) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycRenameTemplate(
                $template['templateName'] ?? '',
                $input['templateName'],
                $gateway
            );
        } elseif (isset($input['status']) && (string) $input['status'] !== (string) ($template['status'] ?? '')) {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycChangeStatus(
                $tplName,
                $template['status'] ?? '',
                $input['status'],
                $gateway
            );
        } else {
            list($detailZh, $detailEn) = TransactionOperationLogTexts::withdrawKycUpdateDescription($tplName, $gateway);
        }

        WithdrawKycTemplateOperationLog::logMutation($input, 'edit', $id, $detailZh, $detailEn);
    }

    private function logTemplateUpdateFailure($input, $templateId, $apiMessage) {
        list($failureMethod, $opType) = $this->resolveTemplateUpdateFailureContext($input);
        WithdrawKycTemplateOperationLog::logMutationFailure(
            $input,
            $opType,
            $templateId,
            $failureMethod,
            $apiMessage
        );
    }

    /**
     * @return array{0:string,1:string} [failureMethod, operationTypeKey]
     */
    private function resolveTemplateUpdateFailureContext($input) {
        if (!is_array($input)) {
            return ['withdrawKycTemplateEditFailure', 'edit'];
        }

        $onlyAuto = isset($input['isAutoApproveEnabled'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['requireDocumentSignature'])
            && !isset($input['displayOrder'])
            && !isset($input['gatewaySettingId']);
        if ($onlyAuto) {
            $on = filter_var($input['isAutoApproveEnabled'], FILTER_VALIDATE_BOOLEAN);
            return ['withdrawKycTemplateAutoApproveFailure', $on ? 'enable' : 'disable'];
        }

        $onlyDocSig = isset($input['requireDocumentSignature'])
            && !isset($input['templateName'])
            && !isset($input['description'])
            && !isset($input['status'])
            && !isset($input['isAutoApproveEnabled'])
            && !isset($input['displayOrder'])
            && !isset($input['gatewaySettingId']);
        if ($onlyDocSig) {
            $on = filter_var($input['requireDocumentSignature'], FILTER_VALIDATE_BOOLEAN);
            return ['withdrawKycTemplateDocSignatureFailure', $on ? 'enable' : 'disable'];
        }

        return ['withdrawKycTemplateEditFailure', 'edit'];
    }
}
