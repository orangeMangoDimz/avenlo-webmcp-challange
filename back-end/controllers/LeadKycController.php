<?php
/**
 * Lead/Client KYC 控制器
 * 处理客户端的 KYC 提交
 */

require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../models/KycQuestionCategory.php';
require_once __DIR__ . '/../models/KycQuestion.php';
require_once __DIR__ . '/../models/KycQuestionOption.php';
require_once __DIR__ . '/../models/KycConditionalRule.php';
require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/ClientKycAnswer.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/KycTemplateCountry.php';
require_once __DIR__ . '/../models/KycStatusMessageTemplate.php';
require_once __DIR__ . '/../models/KycTimeline.php';
require_once __DIR__ . '/../models/KycResubmitRequest.php';
require_once __DIR__ . '/../models/KycResubmitAnswer.php';
require_once __DIR__ . '/../models/KycEmailNotificationTemplate.php';
require_once __DIR__ . '/../models/ExternalKycGateway.php';
require_once __DIR__ . '/../models/ExternalKycTemplate.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/AdminNotificationDelivery.php';
require_once __DIR__ . '/../models/AdminSystemNotification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/ClientAuthContext.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Logger.php';
// 引入 S3Uploader
require_once __DIR__ . '/../utils/S3Uploader.php';
class LeadKycController {
    private $templateModel;
    private $categoryModel;
    private $questionModel;
    private $optionModel;
    private $ruleModel;
    private $submissionModel;
    private $answerModel;
    private $clientModel;
    private $templateCountryModel;
    private $statusMessageModel;
    private $timelineModel;
    private $resubmitRequestModel;
    private $resubmitAnswerModel;
    private $adminNotificationModel;
    private $adminNotificationDeliveryModel;
    private $adminSystemNotificationModel;
    private $logoText;

    public function __construct() {
        $this->templateModel = new KycTemplate();
        $this->categoryModel = new KycQuestionCategory();
        $this->questionModel = new KycQuestion();
        $this->optionModel = new KycQuestionOption();
        $this->ruleModel = new KycConditionalRule();
        $this->submissionModel = new ClientKycSubmission();
        $this->answerModel = new ClientKycAnswer();
        $this->clientModel = new ClientUser();
        $this->templateCountryModel = new KycTemplateCountry();
        $this->statusMessageModel = new KycStatusMessageTemplate();
        $this->timelineModel = new KycTimeline();
        $this->resubmitRequestModel = new KycResubmitRequest();
        $this->resubmitAnswerModel = new KycResubmitAnswer();
        $this->adminNotificationModel = new AdminNotification();
        $this->adminNotificationDeliveryModel = new AdminNotificationDelivery();
        $this->adminSystemNotificationModel = new AdminSystemNotification();
        $config = require __DIR__ . '/../config/app.php';
        $this->logoText = $config['branding']['logoText'] ?? 'CRM';
    }

    /**
     * 获取客户端可用的 KYC 模板
     * GET /api/kyc/client-template
     */
    public function getClientTemplate() {
        // 获取当前客户ID
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // TODO: 根据客户的国家或其他属性选择合适的模板
        // 目前返回第一个活跃的模板
        // $templates = $this->templateModel->findAll(['status' => 'active'], 'displayOrder ASC', 1);

        // 获取用户信息，特别是国家信息
        $client = $this->clientModel->findById($clientId);

        if (!$client) {
            Response::notFound('Client not found');
        }

        // 如果用户已有进行中的KYC提交，优先使用该提交所绑定的模板
        $latestSubmission = $this->submissionModel->getLatestSubmission($clientId);
        if ($latestSubmission && !empty($latestSubmission['templateId'])) {
            $status = $latestSubmission['submissionStatus'] ?? 'draft';
            // 已完成（approved / rejected）时再 fallback 到模板选择逻辑
            if (!in_array($status, ['approved', 'rejected'], true)) {
                $template = $this->templateModel->findById($latestSubmission['templateId']);
                if ($template) {
                    $template['_selectionReason'] = 'existing_submission';
                    $template['_submission'] = [
                        'id' => (int)$latestSubmission['id'],
                        'status' => $status,
                        'templateId' => (int)$latestSubmission['templateId']
                    ];

                    // 如果 features 为 JSON 字符串，进行解码
                    if (!empty($template['features']) && is_string($template['features'])) {
                        $decodedFeatures = json_decode($template['features'], true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $template['features'] = $decodedFeatures;
                        }
                    }

                    Response::success($template);
                }
            }
        }

        $userCountry = $client['country'] ?? null;

        // 智能模板选择逻辑
        $template = $this->selectBestTemplate($userCountry, $clientId);

        if (!$template) {
            Response::notFound('No suitable KYC template available for your region');
        }

        // 添加调试信息（可选）
        $template['_debug'] = [
            'clientId' => $clientId,
            'userCountry' => $userCountry,
            'selectionReason' => $template['_selectionReason'] ?? 'default'
        ];

        Response::success($template);
    }

    /**
     * 智能选择最适合的KYC模板
     *
     * @param string|null $userCountry 用户国家
     * @param int $clientId 客户ID
     * @return array|null 选中的模板
     */
    private function selectBestTemplate($userCountry, $clientId) {
        // 1. 如果用户有国家信息，优先匹配国家特定模板
        if ($userCountry) {
            $template = $this->findTemplateByCountry($userCountry);
            if ($template) {
                $template['_selectionReason'] = "country_specific:{$userCountry}";
                return $template;
            }
        }

        // 2. 查找全球通用模板 (countryCode = 'ALL')
        $globalTemplate = $this->findGlobalTemplate();
        if ($globalTemplate) {
            $globalTemplate['_selectionReason'] = 'global_template';
            return $globalTemplate;
        }

        // 3. 回退到第一个活跃模板
        $defaultTemplate = $this->findDefaultTemplate();
        if ($defaultTemplate) {
            $defaultTemplate['_selectionReason'] = 'default_fallback';
            return $defaultTemplate;
        }

        return null;
    }

    /**
     * 根据国家查找最匹配的模板
     *
     * @param string $countryCode 国家代码
     * @return array|null
     */
    private function findTemplateByCountry($countryCode) {
        // 查找精确匹配用户国家的活跃模板，按优先级排序
        $sql = "SELECT t.*, tc.countryCode, tc.countryName
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                AND tc.countryCode = :country
                ORDER BY t.displayOrder ASC, t.id ASC
                LIMIT 1";

        $result = $this->templateModel->queryOne($sql, ['country' => $countryCode]);

        if ($result) {
            // 记录匹配的国家信息
            $result['matchedCountry'] = [
                'code' => $result['countryCode'],
                'name' => $result['countryName']
            ];

            // 清理不需要的字段
            unset($result['countryCode'], $result['countryName']);
        }

        return $result;
    }

    /**
     * 查找全球通用模板
     *
     * @return array|null
     */
    private function findGlobalTemplate() {
        $sql = "SELECT t.*, tc.countryCode, tc.countryName
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                AND tc.countryCode = 'ALL'
                ORDER BY t.displayOrder ASC, t.id ASC
                LIMIT 1";

        $result = $this->templateModel->queryOne($sql);

        if ($result) {
            $result['matchedCountry'] = [
                'code' => 'ALL',
                'name' => 'All Countries'
            ];

            unset($result['countryCode'], $result['countryName']);
        }

        return $result;
    }

    /**
     * 获取默认模板（第一个活跃模板）
     *
     * @return array|null
     */
    private function findDefaultTemplate() {
        $templates = $this->templateModel->findAll(
            ['status' => 'active'],
            'displayOrder ASC, id ASC',
            1
        );

        if (!empty($templates)) {
            $template = $templates[0];
            $template['matchedCountry'] = null;
            return $template;
        }

        return null;
    }

    /**
     * 获取客户当前KYC状态和进度
     * GET /api/kyc/client-status
     */
    public function getClientStatus() {
        // 获取当前客户ID
        $clientId = ClientAuthContext::getCurrentClientUserId();
        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 使用视图查询客户KYC状态
        $sql = "SELECT * FROM vw_client_kyc_status WHERE clientId = :clientId";
        $status = $this->clientModel->queryOne($sql, ['clientId' => $clientId]);

        if (!$status) {
            // 客户没有KYC记录，返回初始状态
            $client = $this->clientModel->findById($clientId);
            $status = [
                'clientId' => $clientId,
                'email' => $client['email'] ?? '',
                'firstName' => $client['firstName'] ?? '',
                'lastName' => $client['lastName'] ?? '',
                'country' => $client['country'] ?? '',
                'submissionId' => null,
                'templateId' => null,
                'templateName' => null,
                'submissionStatus' => 'draft',
                'submittedAt' => null,
                'reviewedAt' => null,
                'rejectionReason' => null,
                'answeredQuestions' => 0,
                'totalQuestions' => 0,
                'progressPercentage' => 0,
                'signedDocuments' => 0,
                'requiredDocuments' => 0,
                'hasKycRecord' => false
            ];
        } else {
            $status['hasKycRecord'] = true;
        }

        // 补一份 template 的第三方标记，让前端分流时能知道走问卷还是走 SDK
        $status['isThirdPartyEnabled'] = false;
        $status['externalTemplateId'] = null;
        if (!empty($status['templateId'])) {
            $tpl = $this->templateModel->findById($status['templateId']);
            if ($tpl) {
                $status['isThirdPartyEnabled'] = !empty($tpl['isThirdPartyEnabled']);
                $status['externalTemplateId']  = $tpl['externalTemplateId'] ?? null;
            }
        }

        // 添加状态描述信息
        $status['statusInfo'] = $this->getStatusInfo($status['submissionStatus']);

        // 添加状态配置信息
        $status['statusConfig'] = $this->statusMessageModel->getStatusConfig($status['submissionStatus']);

        // 添加时间线数据
        if ($status['submissionId']) {
            $status['timeline'] = $this->timelineModel->generateTimelineForStatus(
                $status['submissionId'],
                $clientId,
                $status['submissionStatus']
            );
        } else {
            // 没有 submission 时，不返回时间线（仅基于实际记录展示）
            $status['timeline'] = [];
        }

        Response::success($status);
    }

    /**
     * 开始KYC验证流程（创建草稿submission）
     * POST /api/kyc/client-start
     */
    public function startKycProcess() {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 检查是否已有进行中的submission
        $existingSubmission = $this->submissionModel->getLatestSubmission($clientId);

        if ($existingSubmission && !in_array($existingSubmission['submissionStatus'], ['rejected', 'expired'])) {
            // 已有进行中的submission，直接返回
            Response::success([
                'submissionId' => $existingSubmission['id'],
                'status' => $existingSubmission['submissionStatus'],
                'message' => 'KYC process already started'
            ]);
            return;
        }

        // 获取适合的模板
        $client = $this->clientModel->findById($clientId);
        $userCountry = $client['country'] ?? null;
        $template = $this->selectBestTemplate($userCountry, $clientId);

        if (!$template) {
            Response::notFound('No suitable KYC template available');
        }

        // 创建新的submission
        // isThirdParty 在这里拍下模板当前的第三方标记 —— 之后模板改了也不会影响这条历史记录的判断
        $submissionData = [
            'clientId' => $clientId,
            'templateId' => $template['id'],
            'isThirdParty' => !empty($template['isThirdPartyEnabled']) ? 1 : 0,
            'submissionStatus' => 'draft',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $submissionId = $this->submissionModel->create($submissionData);

        if (!$submissionId) {
            Response::error('Failed to create KYC submission');
        }else{
            // 添加时间线记录：申请开始
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'application_started',
                'Application Started',
                'KYC verification process initiated'
            );
        }

        Response::success([
            'submissionId' => $submissionId,
            'templateId' => $template['id'],
            'templateName' => $template['templateName'],
            'status' => 'draft',
            'message' => 'KYC process started successfully'
        ]);
    }

    /**
     * 重新开始KYC流程（被拒绝后）
     * POST /api/kyc/client-restart
     */
    public function restartKycProcess() {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 检查最新submission是否为rejected状态
        $latestSubmission = $this->submissionModel->getLatestSubmission($clientId);

        if (!$latestSubmission || $latestSubmission['submissionStatus'] !== 'rejected') {
            Response::badRequest('Can only restart rejected KYC submissions');
        }

        // 获取适合的模板（可能已更新）
        $client = $this->clientModel->findById($clientId);
        $userCountry = $client['country'] ?? null;
        $template = $this->selectBestTemplate($userCountry, $clientId);

        if (!$template) {
            Response::notFound('No suitable KYC template available');
        }

        // 创建新的submission（保留被拒绝的记录）
        $submissionData = [
            'clientId' => $clientId,
            'templateId' => $template['id'],
            'isThirdParty' => !empty($template['isThirdPartyEnabled']) ? 1 : 0,
            'submissionStatus' => 'draft',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $submissionId = $this->submissionModel->create($submissionData);

        if (!$submissionId) {
            Response::error('Failed to restart KYC submission');
        }

        Response::success([
            'submissionId' => $submissionId,
            'templateId' => $template['id'],
            'templateName' => $template['templateName'],
            'status' => 'draft',
            'previousSubmissionId' => $latestSubmission['id'],
            'message' => 'KYC process restarted successfully'
        ]);
    }

    /**
     * 获取状态信息描述
     */
    private function getStatusInfo($status) {
        // 从数据库查询状态消息模板
        $template = $this->statusMessageModel->findOne([
            'statusType' => $status,
            'isActive' => 1
        ]);

        if ($template) {
            return [
                'title' => $template['messageTitle'],
                'description' => $template['messageContent'],
                'type' => $template['messageType'],
                'icon' => $template['iconClass']
            ];
        }

        // 如果数据库中没有找到对应的模板，返回默认值
        return [
            'title' => 'KYC Verification Required',
            'description' => 'Complete your KYC verification to unlock full trading capabilities',
            'type' => 'warning',
            'icon' => 'fas fa-id-card'
        ];
    }

    /**
     * 获取默认时间线（当没有submission时）
     */
    private function getDefaultTimeline($statusType) {
        $templateModel = new KycTimelineTemplate();
        $templates = $templateModel->getTemplatesByStatus($statusType);

        $timeline = [];
        foreach ($templates as $template) {
            $timeline[] = [
                'id' => null,
                'title' => $template['eventTitle'],
                'description' => $template['eventDescription'],
                'date' => 'Pending',
                'completed' => false,
                'current' => false,
                'eventType' => $template['eventType']
            ];
        }

        return $timeline;
    }

    /**
     * 获取模板详情（包含分类、问题、选项）
     * GET /api/kyc/templates/{id}/details
     */
    public function getTemplateDetails($templateId) {
        $template = $this->templateModel->findById($templateId);

        if (!$template) {
            Response::notFound('Template not found');
        }

        // 第三方模板：questions / categories / documents 都用不上，直接只返回 template 本身
        // 让前端按 isThirdPartyEnabled 走第三方 SDK 流程
        if (!empty($template['isThirdPartyEnabled'])) {
            $template['documents'] = [];
            Response::success([
                'template'   => $template,
                'categories' => [],
                'questions'  => [],
            ]);
            return;
        }

        // 获取分类
        $categories = $this->categoryModel->findAll(
            ['templateId' => $templateId, 'isActive' => 1],
            'displayOrder ASC'
        );

        // 获取问题和选项
        $questions = [];
        foreach ($categories as $category) {
            $categoryQuestions = $this->questionModel->findAll(
                ['categoryId' => $category['id'], 'isActive' => 1],
                'displayOrder ASC'
            );

            foreach ($categoryQuestions as &$question) {
                // 获取选项（如果是选择题）
                if (in_array($question['questionType'], ['single_choice', 'multiple_choice'])) {
                    $question['options'] = $this->optionModel->findAll(
                        ['questionId' => $question['id'], 'isActive' => 1],
                        'displayOrder ASC'
                    );
                } else {
                    $question['options'] = [];
                }

                // 获取文档类型（如果是文件上传）
                if ($question['questionType'] === 'file_upload') {
                    $sql = "SELECT * FROM kycQuestionDocumentTypes WHERE questionId = :qid ORDER BY displayOrder";
                    $question['documentTypes'] = $this->questionModel->query($sql, ['qid' => $question['id']]);
                } else {
                    $question['documentTypes'] = [];
                }

                $questions[] = $question;
            }
        }

        // 添加图标到分类
        $categoryIcons = [
            'Personal Information' => 'fa-user',
            'Financial Information' => 'fa-dollar-sign',
            'Investment Experience' => 'fa-chart-line',
            'Risk Assessment' => 'fa-shield-alt',
            'Compliance' => 'fa-clipboard-check'
        ];

        foreach ($categories as &$category) {
            $category['icon'] = $categoryIcons[$category['categoryName']] ?? 'fa-clipboard';
        }

        // 获取模板文档（如果需要文档签名）
        $documents = [];
        if (!empty($template['requireDocumentSignature'])) {
            $sql = "SELECT * FROM kycTemplateDocuments
                    WHERE templateId = :templateId AND isActive = 1
                    ORDER BY displayOrder";
            $documents = $this->templateModel->query($sql, ['templateId' => $templateId]);
        }

        // 将文档添加到 template 对象中
        $template['documents'] = $documents;

        Response::success([
            'template' => $template,
            'categories' => $categories,
            'questions' => $questions
        ]);
    }

    /**
     * 创建 KYC 提交
     * POST /api/kyc/submissions
     */
    public function createSubmission() {
        $input = json_decode(file_get_contents('php://input'), true);
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

//        // 验证
//        $validator = new Validator($input);
//        $validator->required(['templateId']);

        if (!$input['templateId']) {
            Response::validationError("templateId is required");
        }

        // 已有提交只要不是 rejected / expired，一律复用，不再新建。
        // 和 startKycProcess 同口径：避免在途(draft/pending/submitted/under_review)或已通过(approved)的记录之上又插一条 draft。
        $existingSubmission = $this->submissionModel->getLatestSubmission($clientId);

        if ($existingSubmission && !in_array($existingSubmission['submissionStatus'], ['rejected', 'expired'], true)) {
            // 返回现有的提交
            Response::success($existingSubmission);
        }

        // 创建新提交，顺手拍下模板的第三方标记
        $templateForFlag = $this->templateModel->findById($input['templateId']);
        $data = [
            'clientId' => $clientId,
            'templateId' => $input['templateId'],
            'isThirdParty' => !empty($templateForFlag['isThirdPartyEnabled']) ? 1 : 0,
            'submissionStatus' => 'draft',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        $submissionId = $this->submissionModel->create($data);

        if ($submissionId) {
            // 添加时间线记录：申请开始
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'application_started',
                'Application Started',
                'KYC verification process initiated'
            );

            Response::success(['id' => $submissionId], 'KYC submission created successfully', 201);
        } else {
            Response::error('Failed to create KYC submission', 500);
        }
    }

    /**
     * 保存答案
     * PUT /api/kyc/submissions/{id}/answers
     */
    public function saveAnswers($submissionId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        if (!isset($input['answers']) || !is_array($input['answers'])) {
            Response::error('Invalid answers format', 400);
        }

        try {
            // 使用正确的事务处理方法
            $db = Database::getInstance();
            $db->beginTransaction();

            // 预先获取所有现有答案，避免在循环中执行查询
            $existingAnswersQuery = "SELECT id, questionId FROM clientKycAnswers WHERE submissionId = :submissionId";
            $existingAnswersResult = $this->answerModel->query($existingAnswersQuery, ['submissionId' => $submissionId]);

            // 创建现有答案的映射
            $existingAnswersMap = [];
            foreach ($existingAnswersResult as $existingAnswer) {
                $existingAnswersMap[$existingAnswer['questionId']] = $existingAnswer['id'];
            }

            foreach ($input['answers'] as $questionId => $answerData) {
                $saveData = [
                    'submissionId' => $submissionId,
                    'questionId' => $questionId
                ];

                // 根据问题类型保存不同的字段
                $answer = $answerData['answer'] ?? $answerData;
                $questionType = $answerData['questionType'] ?? 'text';

                switch ($questionType) {
                    case 'number':
                        $saveData['answerNumber'] = $answer;
                        break;
                    case 'date':
                        $saveData['answerDate'] = $answer;
                        break;
                    case 'multiple_choice':
                        $saveData['answerValues'] = json_encode($answer);
                        break;
                    default:
                        $saveData['answerText'] = $answer;
                }

                // 使用预先获取的映射来判断是否需要更新或创建
                if (isset($existingAnswersMap[$questionId])) {
                    $this->answerModel->update($existingAnswersMap[$questionId], $saveData);
                } else {
                    $this->answerModel->create($saveData);
                }
            }

            // Update submission status to 'incomplete' if it's currently 'draft'
            if ($submission['submissionStatus'] === 'draft') {
                $this->submissionModel->update($submissionId, [
                    'submissionStatus' => 'incomplete'
                ]);

//                // 记录时间线：个人信息已提交（只记录一次即可）
//                $this->timelineModel->addEvent(
//                    $submissionId,
//                    $clientId,
//                    'personal_info_submitted',
//                    'Personal Information Submitted',
//                    'Basic personal details have been provided'
//                );
            }

            // 计算进度并记录时间线（仅基于实际情况）
            // 已回答题目数
            $answeredCountRow = $this->answerModel->queryOne(
                "SELECT COUNT(DISTINCT questionId) AS cnt FROM clientKycAnswers WHERE submissionId = :sid",
                ['sid' => $submissionId]
            );
            $answeredCount = (int)($answeredCountRow['cnt'] ?? 0);
            // 总题目数
            $templateIdRow = $this->submissionModel->findById($submissionId);
            $templateId = $templateIdRow['templateId'] ?? null;
            $totalCount = 0;
            if ($templateId) {
                $tpl = $this->templateModel->findById($templateId);
                $totalCount = (int)($tpl['totalQuestions'] ?? 0);
            }
            // 最近一次保存的题目及分类（从本次提交中取一个作为代表）
            $lastQuestionId = null;
            foreach ($input['answers'] as $qid => $_) { $lastQuestionId = $qid; }
            $lastCategory = null;
            if ($lastQuestionId) {
                $qRow = $this->questionModel->findById($lastQuestionId);
                if ($qRow) {
                    $catRow = $this->categoryModel->findById($qRow['categoryId']);
                    $lastCategory = $catRow['categoryName'] ?? null;
                }
            }
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'progress_updated',
                $lastCategory . ' Submitted',
                $lastCategory . ' questions have been submitted',
                [
                    'answeredQuestions' => $answeredCount,
                    'totalQuestions' => $totalCount,
                    'lastQuestionId' => $lastQuestionId,
                    'lastCategory' => $lastCategory
                ]
            );

            // 提交事务
            $db->commit();

            Response::success(null, 'Answers saved successfully');
        } catch (Exception $e) {
            // 回滚事务
            $db->rollback();
            Response::error('Failed to save answers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除指定问题的答案
     * DELETE /api/kyc/submissions/{id}/answers
     */
    public function deleteAnswers($submissionId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        if (!isset($input['questionIds']) || !is_array($input['questionIds'])) {
            Response::error('Invalid questionIds format', 400);
        }

        try {
            $db = Database::getInstance();
            $db->beginTransaction();

            // 删除指定问题的答案
            if (!empty($input['questionIds'])) {
                // 构建IN子句的占位符
                $placeholders = str_repeat('?,', count($input['questionIds']) - 1) . '?';
                $sql = "DELETE FROM clientKycAnswers WHERE submissionId = ? AND questionId IN ($placeholders)";

                // 准备参数数组
                $params = array_merge([$submissionId], $input['questionIds']);

                // 执行删除查询
                $db->query($sql, $params);
            }

            // 记录时间线事件（使用现有的枚举值）
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'progress_updated', // 使用现有的枚举值
                'Answers Cleared',
                'Some answers have been cleared for re-submission',
                [
                    'clearedQuestionIds' => $input['questionIds'],
                    'clearedCount' => count($input['questionIds'])
                ]
            );

            $db->commit();

            Response::success(null, 'Answers deleted successfully');
        } catch (Exception $e) {
            $db->rollback();
            Response::error('Failed to delete answers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 上传文件
     * POST /api/kyc/submissions/{id}/upload
     */
    public function uploadFile($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        if (!isset($_FILES['file']) || !isset($_POST['questionId'])) {
            Response::error('File and questionId are required', 400);
        }

        $file = $_FILES['file'];
        $questionId = $_POST['questionId'];

        // 验证文件
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            Response::error('File size exceeds 5MB limit', 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Invalid file type. Only JPG, PNG, and PDF are allowed', 400);
        }

        // 生成唯一文件名（保持不变）
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'q' . $questionId . '_' . time() . '_' . uniqid() . '.' . $extension;

        try {
            // 初始化 S3 上传器
            $s3Uploader = new S3Uploader();

            // 生成 S3 Key（使用kyc路径）
            $s3Key = $s3Uploader->generateS3Key($filename, 'kyc');

            // 上传到 S3（使用临时文件路径）
            $uploadResult = $s3Uploader->uploadFile(
                $file['tmp_name'],
                $s3Key,
                $file['type']
            );

            if (!$uploadResult['success']) {
                Response::error('Failed to upload file to S3: ' . ($uploadResult['error'] ?? 'Unknown error'), 500);
            }

            // 获取 S3 URL
            $s3Url = $uploadResult['url'];

            // 保存 S3 URL 到答案（而不是本地路径）
            $existingAnswer = $this->answerModel->findOne([
                'submissionId' => $submissionId,
                'questionId' => $questionId
            ]);

            if ($existingAnswer) {
                // 追加到现有文件列表
                $existingFiles = json_decode($existingAnswer['uploadedFiles'] ?? '[]', true);
                $existingFiles[] = $s3Url;  // 改为存储 S3 URL

                $this->answerModel->update($existingAnswer['id'], [
                    'uploadedFiles' => json_encode($existingFiles)
                ]);
            } else {
                // 创建新答案记录
                $this->answerModel->create([
                    'submissionId' => $submissionId,
                    'questionId' => $questionId,
                    'uploadedFiles' => json_encode([$s3Url])  // 改为存储 S3 URL
                ]);
            }

            // 记录时间线事件
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'documents_uploaded',
                'Document Uploaded',
                'A document has been uploaded for verification',
                [
                    'questionId' => (int)$questionId,
                    'filename' => $filename,
                    'url' => $s3Url,  // 改为 S3 URL
                    's3Key' => $s3Key  // 可选：保存 S3 Key 以便后续删除
                ]
            );

            Response::success([
                'url' => $s3Url,  // 返回 S3 URL
                'filename' => $filename,
                's3Key' => $s3Key  // 可选
            ], 'File uploaded successfully');

        } catch (Exception $e) {
//            Logger::error("S3 Upload Exception: " . $e->getMessage());
            Response::error('Failed to upload file: ' . $e->getMessage(), 500);
        }

//        // 创建上传目录
//        $uploadDir = __DIR__ . '/../uploads/kyc/' . $clientId . '/';
//        if (!is_dir($uploadDir)) {
//            mkdir($uploadDir, 0755, true);
//        }
//
//        // 生成唯一文件名
//        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
//        $filename = 'q' . $questionId . '_' . time() . '_' . uniqid() . '.' . $extension;
//        $filepath = $uploadDir . $filename;
//
//        // 移动文件
//        if (move_uploaded_file($file['tmp_name'], $filepath)) {
//            // 保存文件路径到答案
//            $existingAnswer = $this->answerModel->findOne([
//                'submissionId' => $submissionId,
//                'questionId' => $questionId
//            ]);
//
//            $relativePath = 'uploads/kyc/' . $clientId . '/' . $filename;
//
//            if ($existingAnswer) {
//                // 追加到现有文件列表
//                $existingFiles = json_decode($existingAnswer['uploadedFiles'] ?? '[]', true);
//                $existingFiles[] = $relativePath;
//
//                $this->answerModel->update($existingAnswer['id'], [
//                    'uploadedFiles' => json_encode($existingFiles)
//                ]);
//            } else {
//                // 创建新答案记录
//                $this->answerModel->create([
//                    'submissionId' => $submissionId,
//                    'questionId' => $questionId,
//                    'uploadedFiles' => json_encode([$relativePath])
//                ]);
//            }
//
//            // 记录文件上传事件（实际发生）
//            $this->timelineModel->addEvent(
//                $submissionId,
//                $clientId,
//                'documents_uploaded',
//                'Document Uploaded',
//                'A document has been uploaded for verification',
//                [
//                    'questionId' => (int)$questionId,
//                    'filename' => $filename,
//                    'url' => $relativePath
//                ]
//            );
//
//            Response::success([
//                'url' => $relativePath,
//                'filename' => $filename
//            ], 'File uploaded successfully');
//        } else {
//            Response::error('Failed to upload file', 500);
//        }
    }

    /**
     * 上传 resubmit 文件
     * POST /api/kyc/submissions/{id}/resubmit-upload
     */
    public function uploadResubmitFile($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        // 检查状态是否为 resubmit_required
        if ($submission['submissionStatus'] !== 'resubmit_required') {
            Response::error('Submission is not in resubmit_required status', 400);
        }

        if (!isset($_FILES['file']) || !isset($_POST['itemIndex'])) {
            Response::error('File and itemIndex are required', 400);
        }

        $file = $_FILES['file'];
        $itemIndex = $_POST['itemIndex']; // 这是 requestedItems 数组中的索引

        // 验证文件
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            Response::error('File size exceeds 5MB limit', 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Invalid file type. Only JPG, PNG, and PDF are allowed', 400);
        }

        try {
            // 获取最新的 resubmit request
            $request = $this->resubmitRequestModel->getLatestRequest($submissionId);
            if (!$request) {
                Response::error('Resubmit request not found', 404);
            }

            // 解析 requestedItems 获取对应的 item 信息
            $requestedItems = json_decode($request['requestedItems'], true);
            if (!isset($requestedItems[$itemIndex])) {
                Response::error('Invalid item index', 400);
            }

            $item = $requestedItems[$itemIndex];

            // 生成唯一文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'resubmit_' . $submissionId . '_item' . $itemIndex . '_' . time() . '_' . uniqid() . '.' . $extension;

            // 初始化 S3 上传器
            $s3Uploader = new S3Uploader();

            // 生成 S3 Key（注意：resubmit 文件使用kyc路径）
            $s3Key = $s3Uploader->generateS3Key($filename, 'kyc');

            // 上传到 S3
            $uploadResult = $s3Uploader->uploadFile(
                $file['tmp_name'],
                $s3Key,
                $file['type']
            );

            if (!$uploadResult['success']) {
                Response::error('Failed to upload file to S3: ' . ($uploadResult['error'] ?? 'Unknown error'), 500);
            }

            // 获取 S3 URL
            $s3Url = $uploadResult['url'];

            // 注意：不在此处保存到数据库，只上传文件
            // 文件信息会在最终提交时（resubmit-answers）统一保存到 kycresubmitanswers 表

            Response::success([
                'url' => $s3Url,  // 返回 S3 URL
                'filename' => $filename,
                'itemIndex' => $itemIndex,
                's3Key' => $s3Key  // 可选
            ], 'File uploaded successfully');
        } catch (Exception $e) {
//            Logger::error("S3 Resubmit Upload Exception: " . $e->getMessage());
            Response::error('Failed to upload resubmit file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取客户已签名的KYC文档
     * GET /api/kyc/my-signed-documents
     */
    public function getMySignedDocuments() {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        try {
            // 获取客户最新的已提交的KYC submission
            $sql = "SELECT id, templateId FROM clientKycSubmissions
                    WHERE clientId = :clientId
                    AND submissionStatus IN ('submitted', 'under_review', 'pending', 'approved')
                    ORDER BY submittedAt DESC
                    LIMIT 1";
            $submission = $this->submissionModel->queryOne($sql, ['clientId' => $clientId]);

            if (!$submission) {
                Response::success([]);
                return;
            }

            // 获取签名的文档
            $sql = "SELECT
                        sig.id,
                        sig.signedAt,
                        sig.ipAddress,
                        doc.id AS documentId,
                        doc.documentTitle,
                        doc.documentContent
                    FROM clientKycDocumentSignatures sig
                    INNER JOIN kycTemplateDocuments doc ON sig.templateDocumentId = doc.id
                    WHERE sig.submissionId = :submissionId
                    ORDER BY doc.displayOrder";

            $documents = $this->submissionModel->query($sql, ['submissionId' => $submission['id']]);

            // 格式化文档数据
            $formattedDocuments = array_map(function($doc) use ($clientId) {
                return [
                    'id' => $doc['id'],
                    'documentId' => $doc['documentId'],
                    'documentType' => 'kyc_document', // 标识为KYC文档
                    'title' => $doc['documentTitle'],
                    'content' => $doc['documentContent'],
                    'signedAt' => $doc['signedAt'],
                    'ipAddress' => $doc['ipAddress']
                ];
            }, $documents);

            Response::success($formattedDocuments);

        } catch (Exception $e) {
            Response::error('Failed to load signed documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 签署模板文档
     * POST /api/kyc/submissions/{id}/sign-documents
     */
    public function signDocuments($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $documentIds = $input['documentIds'] ?? [];

        if (!is_array($documentIds)) {
            Response::validationError(['documentIds' => 'Document IDs must be an array']);
        }

        try {
            $db = Database::getInstance()->getConnection();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // 删除现有签名（如果有）
            $deleteStmt = $db->prepare('DELETE FROM clientKycDocumentSignatures WHERE submissionId = :submissionId');
            $deleteStmt->execute(['submissionId' => $submissionId]);

            // 如果 documentIds 不为空，插入新签名
            if (!empty($documentIds)) {
                $insertStmt = $db->prepare('
                    INSERT INTO clientKycDocumentSignatures
                    (submissionId, templateDocumentId, ipAddress, userAgent, signedAt)
                    VALUES (:submissionId, :documentId, :ipAddress, :userAgent, NOW())
                ');

                foreach ($documentIds as $documentId) {
                    $insertStmt->execute([
                        'submissionId' => $submissionId,
                        'documentId' => $documentId,
                        'ipAddress' => $ipAddress,
                        'userAgent' => $userAgent
                    ]);
                }

                Response::success(null, 'Documents signed successfully');
            } else {
                // 空数组表示取消所有签名
                Response::success(null, 'Document signatures removed successfully');
            }
        } catch (Exception $e) {
            Response::error('Failed to save document signatures: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 提交 KYC 申请（最终提交）
     * POST /api/kyc/submissions/{id}/submit
     */
    public function submitApplication($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }
        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        $template = null;
        if (!empty($submission['templateId'])) {
            $template = $this->templateModel->findById($submission['templateId']);
        }

        // 验证文档签名（如果需要）
        if ($template && !empty($template['requireDocumentSignature'])) {
            // 获取模板需要的文档
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT id FROM kycTemplateDocuments WHERE templateId = :templateId AND isActive = 1');
            $stmt->execute(['templateId' => $template['id']]);
            $requiredDocs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($requiredDocs)) {
                // 检查是否所有文档都已签名
                $stmt = $db->prepare('SELECT COUNT(*) FROM clientKycDocumentSignatures WHERE submissionId = :submissionId');
                $stmt->execute(['submissionId' => $submissionId]);
                $signedCount = $stmt->fetchColumn();

                if ($signedCount < count($requiredDocs)) {
                    Response::error('Please agree to all required documents before submitting', 400);
                }
            }
        }

        $autoApproveEnabled = $template && !empty($template['isAutoApproveEnabled']);
        $now = date('Y-m-d H:i:s');

        if ($autoApproveEnabled) {
            try {
                $this->handleAutoApproval($submissionId, $clientId, $now);
            } catch (Exception $e) {
//                Logger::error('Auto approval failed: ' . $e->getMessage());
                Response::error('Failed to auto-approve KYC application: ' . $e->getMessage(), 500);
            }

            Response::success(null, 'KYC application submitted and auto-approved successfully');
            return;
        }

        // 更新提交状态
        $success = $this->submissionModel->update($submissionId, [
            'submissionStatus' => 'pending',//'under_review',
            'submittedAt' => $now
        ]);

        if ($success) {
            // 时间线：Application Submitted
            try {
                $this->timelineModel->addEvent(
                    $submissionId,
                    $clientId,
                    'application_submitted',
                    'Application Submitted',
                    'All required information and documents submitted',
                    null,
                    null,
                    'completed'
                );

                // 时间线：Under Review (当前状态)
                $this->timelineModel->addEvent(
                    $submissionId,
                    $clientId,
                    'pending',
                    'Pending',
                    'Compliance team is reviewing your application',
                    null,
                    null,
                    'current'
                );
                // 时间线：Decision (待定状态)
                $this->timelineModel->addEvent(
                    $submissionId,
                    $clientId,
                    'review_completed',
                    'Decision',
                    'Final verification decision will be made',
                    null,
                    null,
                    'pending'
                );
            } catch (Exception $e) {
                // 时间线添加失败不应该影响主流程
//                error_log("Failed to add timeline events: " . $e->getMessage());
            }

            try {
                $this->notifyAdminsOfKycSubmission($submissionId, $clientId);
            } catch (Exception $e) {
                // 通知失败不应该影响主流程
//                Logger::error('Failed to create admin notification for KYC submission: ' . $e->getMessage());
            }

            Response::success(null, 'KYC application submitted successfully');
        } else {
            Response::error('Failed to submit KYC application', 500);
        }
    }

    /**
     * 提交重新提交的答案（客户端）
     * POST /api/kyc/submissions/{id}/resubmit-answers
     */
    public function submitResubmitAnswers($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        // 检查状态是否为 resubmit_required
        if ($submission['submissionStatus'] !== 'resubmit_required') {
            Response::error('Submission is not in resubmit_required status', 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['answers']) || !is_array($input['answers'])) {
            Response::validationError(['answers' => 'Answers array is required']);
        }

        try {
            // 1. 获取最新的请求
            $request = $this->resubmitRequestModel->getLatestRequest($submissionId);
            if (!$request) {
                Response::error('Resubmit request not found', 404);
            }

            // 2. 保存重新提交的答案
            $savedCount = $this->resubmitAnswerModel->saveAnswers(
                $request['id'],
                $submissionId,
                $input['answers']
            );

            // 3. 更新提交状态为 pending
            $this->submissionModel->updateStatus($submissionId, 'under_review', null);

            // 4. 更新客户表的kycStatus为submitted（因为pending不在kycStatus的ENUM值中）
            $this->clientModel->update($clientId, [
                'kycStatus' => 'submitted',
                'updatedAt' => date('Y-m-d H:i:s')
            ]);

            // 5. 处理时间线：
            // 5.1 将当前的 current 事件改为 completed
            $currentEvent = $this->timelineModel->findOne([
                'submissionId' => $submissionId,
                'eventStatus' => 'current'
            ]);

            if ($currentEvent) {
                // 更新当前事件为 completed
                $this->timelineModel->update($currentEvent['id'], [
                    'eventStatus' => 'completed'
                ]);
            } else {
                // 如果没有 current 事件，创建一个 completed 事件
                $this->timelineModel->addEvent(
                    $submissionId,
                    $clientId,
                    'resubmit_required',
                    'Additional Information Requested',
                    'Additional information was requested by reviewer',
                    ['requestId' => $request['id']],
                    null,
                    'completed'
                );
            }

            // 5.2 添加新的 current 时间线事件（等待审核）
            // 使用 'application_submitted' 作为 eventType，因为 'resubmission_submitted' 不在 ENUM 中
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'application_submitted',
                'Additional Information Submitted',
                'Your additional information has been submitted and is awaiting review',
                ['requestId' => $request['id']],
                null,
                'current'
            );

            // 6. 删除pending状态的时间线
            $this->timelineModel->deletePendingEvents($submissionId);

            // 7. 标记请求为已完成
            $this->resubmitRequestModel->markAsCompleted($request['id']);

            Response::success([
                'saved' => $savedCount,
                'message' => "Resubmission submitted successfully"
            ]);

        } catch (Exception $e) {
            Response::error('Failed to submit resubmit answers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取重新提交请求信息（客户端）
     * GET /api/kyc/submissions/{id}/resubmit-request
     */
    public function getResubmitRequest($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 验证提交归属
        $submission = $this->submissionModel->findById($submissionId);
        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        // 获取最新的请求
        $request = $this->resubmitRequestModel->getLatestRequest($submissionId);

        if (!$request) {
            Response::notFound('Resubmit request not found');
        }

        // 获取完整的请求信息（包含请求的项目）
        $requestWithItems = $this->resubmitRequestModel->getRequestWithItems($request['id']);

        Response::success($requestWithItems);
    }

    /**
     * 获取我的 KYC 提交状态
     * GET /api/kyc/my-submission
     */
    public function getMySubmission() {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        // 获取最新的提交
        $submission = $this->submissionModel->query(
            "SELECT s.*, t.templateName
             FROM clientKycSubmissions s
             LEFT JOIN kycTemplates t ON s.templateId = t.id
             WHERE s.clientId = :clientId
             ORDER BY s.createdAt DESC
             LIMIT 1",
            ['clientId' => $clientId]
        );

        if (empty($submission)) {
            Response::success(null, 'No KYC submission found');
        }

        Response::success($submission[0]);
    }

    /**
     * 获取第三方 KYC 启动信息
     * GET /api/kyc/external-launch
     *
     * 客户在 KYC 页面打开时，如果当前 client 关联的模板开启了第三方，
     * 调这个接口拿 accessToken / levelName 等，前端用来初始化 Sumsub WebSDK（内部 iframe）。
     *
     * 不存 applicantId：Sumsub 的 accessTokens 接口第一次调用会自动建 applicant，
     * 后续通过 webhook 拿到 applicantId 再回写更稳。
     */
    public function getExternalLaunch() {
        $clientId = ClientAuthContext::getCurrentClientUserId();
        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
            return;
        }

        // 第三方流程必须先有一条 submission（startKycProcess 那边建好的）
        // 这一条 submission 同时也是 externalUserId 的来源（一 submission 一 applicant）
        $submission = $this->submissionModel->getLatestSubmission($clientId);
        if (!$submission) {
            Response::error('No KYC submission found. Please start the KYC process first.', 404);
            return;
        }

        $template = $this->templateModel->findById($submission['templateId']);
        if (!$template) {
            Response::error('KYC template not found', 404);
            return;
        }
        if (empty($template['isThirdPartyEnabled']) || empty($template['externalTemplateId'])) {
            Response::error('Template is not configured for third-party KYC', 400);
            return;
        }

        // status 守门：只在 draft / resubmit_required 时生成 token
        //   - pending：用户已经在 Sumsub 那边，前端应该显示"审核中"，不需要再开 SDK
        //   - approved / rejected：终态，前端应该显示对应结果
        // 这两类情况都返回 200 + 状态信息，让前端知道为啥没有 token
        $launchableStatuses = ['draft', 'resubmit_required'];
        $currentStatus = $submission['submissionStatus'] ?? 'draft';
        if (!in_array($currentStatus, $launchableStatuses, true)) {
            Response::success([
                'provider'         => $template['thirdPartyProvider'] ?? null,
                'submissionId'     => (int)$submission['id'],
                'submissionStatus' => $currentStatus,
                'canLaunch'        => false,
                'reason'           => 'status-not-launchable',
            ]);
            return;
        }

        $externalTemplateModel = new ExternalKycTemplate();
        $externalTemplate = $externalTemplateModel->findById($template['externalTemplateId']);
        if (!$externalTemplate || empty($externalTemplate['isActive'])) {
            Response::error('External KYC template is not available', 400);
            return;
        }

        $gatewayModel = new ExternalKycGateway();
        $gateway = $gatewayModel->findByIdWithSecrets($externalTemplate['gatewayId']);
        if (!$gateway || empty($gateway['isEnabled'])) {
            Response::error('External KYC gateway is not enabled', 400);
            return;
        }

        $provider = strtolower((string)($gateway['provider'] ?? ''));
        if ($provider !== 'sumsub') {
            Response::error("Provider [{$provider}] is not supported yet", 400);
            return;
        }

        // externalUserId：每个 submission 一个 uuid4，第一次生成后落库到 providerApplicantId
        // refresh 时复用同一个 uuid，保证 Sumsub 那边一直是同一个 applicant
        // 注：这一列存的是"我们传给第三方的 userId"，不是 Sumsub 自己的 applicantId（那个我们暂时不存）
        $externalUserId = $submission['providerApplicantId'] ?? null;
        if (empty($externalUserId)) {
            require_once __DIR__ . '/../utils/Uuid.php';
            $externalUserId = Uuid::v4();
            $this->submissionModel->update($submission['id'], [
                'providerApplicantId' => $externalUserId,
            ]);
            $submission['providerApplicantId'] = $externalUserId;
        }

        $levelName = $externalTemplate['externalLevelName'];

        // 拿客户的 email / phone，附给 Sumsub 让 applicant 自带这些信息
        $client = $this->clientModel->findById($clientId);
        $email = $client['email'] ?? null;
        $phone = $client['phone'] ?? null;

        require_once __DIR__ . '/../services/SumsubService.php';
        try {
            $service = new SumsubService($gateway);
            $token = $service->generateAccessToken($externalUserId, $levelName, $email, $phone, 600);
        } catch (Exception $e) {
            Response::error('Failed to launch external KYC: ' . $e->getMessage(), 500);
            return;
        }

        if (!$token) {
            Response::error('Failed to obtain access token from provider', 500);
            return;
        }

        // applicantId 不在 /accessTokens/sdk 的返回里 —— 等 webhook 第一次回调时再写回 submission。
        // 现在 submission 上 providerExternalUserId 已经存好了，webhook 可以靠它定位记录。

        Response::success([
            'provider'         => $gateway['provider'],
            'submissionId'     => (int)$submission['id'],
            'submissionStatus' => $currentStatus,
            'canLaunch'        => true,
            'externalUserId'   => $externalUserId,
            'levelName'        => $levelName,
            'accessToken'      => $token,
            'iframeBaseUrl'    => $gateway['iframeBaseUrl'] ?? null,
            'returnUrl'        => $gateway['returnUrl'] ?? null,
        ]);
    }

    /**
     * 获取提交详情（包含答案）
     * GET /api/kyc/submissions/{id}
     */
    public function getSubmissionDetails($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();
        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        $submission = $this->submissionModel->findById($submissionId);

        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        // 获取答案并格式化为前端需要的格式
        // 使用JOIN查询获取答案和对应的问题类型
        $answersQuery = "
            SELECT
                a.*,
                q.questionType,
                q.questionText
            FROM clientKycAnswers a
            LEFT JOIN kycQuestions q ON a.questionId = q.id
            WHERE a.submissionId = :submissionId
        ";
        $answers = $this->answerModel->query($answersQuery, ['submissionId' => $submissionId]);

        $formattedAnswers = [];
        foreach ($answers as $answer) {
            // 根据问题类型获取对应的答案字段
            $questionType = $answer['questionType'] ?? 'text';
            $answerValue = null;

            switch ($questionType) {
                case 'number':
                    $answerValue = $answer['answerNumber'] ?? null;
                    break;
                case 'date':
                    $answerValue = $answer['answerDate'] ?? null;
                    break;
                case 'multiple_choice':
                    // 如果是多选，解析JSON字符串
                    $answerValue = isset($answer['answerValues']) ? json_decode($answer['answerValues'], true) : null;
                    break;
                case 'file_upload':
                    // 文件上传类型，返回上传的文件列表
                    $answerValue = isset($answer['uploadedFiles']) ? json_decode($answer['uploadedFiles'], true) : [];
                    break;
                default:
                    $answerValue = $answer['answerText'] ?? null;
            }

            $formattedAnswers[$answer['questionId']] = [
                'questionId' => $answer['questionId'],
                'answer' => $answerValue,
                'questionType' => $questionType
            ];
        }

        $submission['answers'] = $formattedAnswers;

        // 获取已签名的文档
        $signedDocsQuery = "
            SELECT
                sig.id,
                sig.templateDocumentId,
                sig.signedAt,
                sig.ipAddress
            FROM clientKycDocumentSignatures sig
            WHERE sig.submissionId = :submissionId
        ";
        $signedDocuments = $this->submissionModel->query($signedDocsQuery, ['submissionId' => $submissionId]);
        $submission['signedDocuments'] = $signedDocuments;

        Response::success($submission);
    }

    /**
     * 获取提交进度信息
     * GET /api/kyc/submissions/{id}/progress
     */
    public function getSubmissionProgress($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        $submission = $this->submissionModel->findById($submissionId);

        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        // 获取模板详情
        $template = $this->templateModel->getTemplateDetails($submission['templateId']);
        $categories = $template['categories'] ?? [];
        $questions = $template['questions'] ?? [];

        // 获取已回答的问题
        $answers = $this->answerModel->getSubmissionAnswers($submissionId);
        $answerMap = [];
        foreach ($answers as $answer) {
            $answerMap[$answer['questionId']] = $this->answerModel->getAnswerValue($answer);
        }

        // 计算每个步骤的完成状态
        $completedSteps = [];
        $nextStepIndex = null;

        foreach ($categories as $index => $category) {
            $categoryQuestions = array_filter($questions, function($q) use ($category) {
                return $q['categoryId'] == $category['id'];
            });

            // 检查这个分类的所有必填问题是否都已回答
            $isStepComplete = true;
            foreach ($categoryQuestions as $question) {
                if ($question['isRequired']) {
                    $answer = $answerMap[$question['id']] ?? null;
                    if ($answer === null || $answer === '' ||
                        (is_array($answer) && empty($answer))) {
                        $isStepComplete = false;
                        break;
                    }
                }
            }

            if ($isStepComplete) {
                $completedSteps[] = $index;
            } else if ($nextStepIndex === null) {
                $nextStepIndex = $index;
            }
        }

        Response::success([
            'submissionId' => $submissionId,
            'templateId' => $submission['templateId'],
            'totalSteps' => count($categories),
            'completedSteps' => $completedSteps,
            'nextStepIndex' => $nextStepIndex,
            'totalQuestions' => count($questions),
            'answeredQuestions' => count($answerMap),
            'progressPercentage' => count($categories) > 0 ? (count($completedSteps) / count($categories)) * 100 : 0
        ]);
    }

    /**
     * 获取下一个应该回答的问题（考虑规则跳转）
     * GET /api/kyc/submissions/{id}/next-question
     */
    public function getNextQuestion($submissionId) {
        try {
            $clientId = ClientAuthContext::getCurrentClientUserId();

            if (!$clientId) {
                Response::unauthorized('Client not authenticated');
            }
            $submission = $this->submissionModel->findById($submissionId);
            if (!$submission) {
                Response::error('Submission not found', 404);
            }

            if ($submission['clientId'] != $clientId) {
                Response::forbidden('Access denied');
            }

            // 获取所有答案
            $answers = $this->answerModel->getSubmissionAnswers($submissionId);
            $answerMap = [];
            foreach ($answers as $answer) {
                $answerMap[$answer['questionId']] = $this->answerModel->getAnswerValue($answer);
            }

            // 评估规则
            $actions = $this->ruleModel->evaluateTemplateRules($submission['templateId'], $answerMap);

            // 获取模板的所有问题和分类
            $template = $this->templateModel->getTemplateDetails($submission['templateId']);
            $categories = $template['categories'] ?? [];
            $questions = [];
            foreach($categories as $categorie){
                if($categorie['questions']){
                    foreach ($categorie['questions'] as $q){
                        $questions[] = $q;
                    }
                }
            }
            if (empty($questions)) {
                Response::error('No questions found in template', 404);
            }

            // 找到下一个应该回答的问题
            $nextQuestion = $this->findNextQuestionToAnswer($questions, $categories, $answerMap, $actions);

            Response::success([
                'nextQuestion' => $nextQuestion,
                'ruleActions' => $actions,
                'totalQuestions' => count($questions),
                'answeredQuestions' => count($answerMap),
                'debug' => [
                    'submissionId' => $submissionId,
                    'clientId' => $clientId,
                    'templateId' => $submission['templateId'],
                    'hasAnswers' => !empty($answerMap),
                    'answerCount' => count($answerMap),
                    'questionCount' => count($questions),
                    'categoryCount' => count($categories)
                ]
            ]);

        } catch (Exception $e) {
            Response::error('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 找到下一个应该回答的问题
     */
    private function findNextQuestionToAnswer($questions, $categories, $answerMap, $ruleActions) {
        // 检查是否有跳转规则被触发
        $jumpActions = array_filter($ruleActions, function($action) {
            return $action['ruleType'] === 'jump_to';
        });

        $startQuestionId = null;
        if (!empty($jumpActions)) {
            // 有跳转规则，从目标问题开始查找
            $lastJumpAction = end($jumpActions);
            $startQuestionId = $lastJumpAction['targetQuestionId'];
        }

        // 按问题顺序查找第一个未回答的必填问题
//        usort($questions, function($a, $b) {
//            return $a['displayOrder'] <=> $b['displayOrder'];
//        });

        $foundStart = $startQuestionId === null; // 如果没有跳转规则，从头开始

        foreach ($questions as $question) {
            // 如果有跳转规则，先找到目标问题
            if (!$foundStart) {
                if ($question['questionId'] == $startQuestionId) {
                    $foundStart = true;
                }
                continue;
            }

            // 检查这个问题是否已经回答
            $hasAnswer = isset($answerMap[$question['questionId']]) &&
                        $answerMap[$question['questionId']] !== null;
            if (!$hasAnswer) {
                // 如果是必填问题，返回这个问题
                if ($question['isRequired']) {
                    // 找到问题所属的分类
                    $category = null;
                    foreach ($categories as $cat) {
                        if ($cat['id'] == $question['categoryId']) {
                            $category = $cat;
                            break;
                        }
                    }

                    $result = [
                        'questionId' => $question['questionId'],
                        'question' => $question,
                        'category' => $category,
                        'stepIndex' => $this->findCategoryIndex($categories, $question['categoryId'])
                    ];
                    return $result;
                }
            }
        }
        // 如果所有必填问题都已回答，返回第一个未完成的步骤
        if (empty($answerMap)) {
            // 如果没有任何答案，返回第一个问题
            if (!empty($questions)) {
                $firstQuestion = $questions[0];
                $category = null;
                foreach ($categories as $cat) {
                    if ($cat['id'] == $firstQuestion['categoryId']) {
                        $category = $cat;
                        break;
                    }
                }

                $result = [
                    'questionId' => $firstQuestion['id'],
                    'question' => $firstQuestion,
                    'category' => $category,
                    'stepIndex' => $this->findCategoryIndex($categories, $firstQuestion['categoryId'])
                ];
                return $result;
            }
        }

        return null; // 所有必填问题都已回答
    }

    /**
     * 评估条件规则
     * POST /api/kyc/submissions/{id}/evaluate-rules
     */
    public function evaluateRules($submissionId) {
        $clientId = ClientAuthContext::getCurrentClientUserId();

        if (!$clientId) {
            Response::unauthorized('Client not authenticated');
        }

        $submission = $this->submissionModel->findById($submissionId);

        if (!$submission || $submission['clientId'] != $clientId) {
            Response::forbidden('Access denied');
        }

        // 获取所有答案
        $answers = $this->answerModel->getSubmissionAnswers($submissionId);

        // 将答案转换为问题ID => 答案值的格式
        $answerMap = [];
        foreach ($answers as $answer) {
            $answerMap[$answer['questionId']] = $this->answerModel->getAnswerValue($answer);
        }

        // 评估规则
        $actions = $this->ruleModel->evaluateTemplateRules($submission['templateId'], $answerMap);

        Response::success([
            'actions' => $actions,
            'count' => count($actions),
            'submissionId' => $submissionId,
            'templateId' => $submission['templateId'],
            'totalAnswers' => count($answerMap)
        ]);
    }

    /**
     * 找到分类在数组中的索引
     */
    private function findCategoryIndex($categories, $categoryId) {
        foreach ($categories as $index => $category) {
            if ($category['id'] == $categoryId) {
                return $index;
            }
        }
        return 0;
    }

    private function sendAutoApprovalEmail($clientId, $submissionId) {
        try {
            require_once __DIR__ . '/../utils/EmailSender.php';
            require_once __DIR__ . '/../models/EmailTemplate.php';

            $clientModel = new ClientUser();
            $templateModel = new EmailTemplate();

            $client = $clientModel->findById($clientId);
            if (!$client) {
                return;
            }

            // 获取邮件模板（从 emailTemplates 表）
            $template = $templateModel->getByKey('kyc_approval_notification');
            if (!$template) {
                return;
            }

            $emailSender = new EmailSender();

            // 准备变量
            $config = require __DIR__ . '/../config/app.php';
            $platformName = $config['branding']['companyShortName']
                ?? $config['branding']['logoText']
                ?? $config['branding']['companyName']
                ?? 'CRM';
            $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
            $dashboardUrl = $config['client_frontend_url'] ?? 'https://example.com';

            $variables = [
                'clientName' => $clientName,
                'platformName' => $platformName,
                'dashboardUrl' => $dashboardUrl
            ];

            // 替换模板变量（使用双花括号格式）
            $subject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
            $body = EmailTemplate::replaceVariables($template['emailBody'], $variables);

            $emailSender->send($client['email'], $subject, $body);
        } catch (Exception $e) {
//            Logger::error('Auto approval email failed: ' . $e->getMessage());
        }
    }

    private function handleAutoApproval($submissionId, $clientId, $submittedAt) {
        $now = date('Y-m-d H:i:s');

        // 确保提交记录标记为已批准
        $this->submissionModel->approve($submissionId, null, 'Auto-approved');
        // 确保提交时间存在
        $this->submissionModel->update($submissionId, [
            'submittedAt' => $submittedAt,
            'updatedAt' => $now
        ]);

        // 更新客户端 KYC 状态
        $this->clientModel->update($clientId, [
            'kycStatus' => 'approved',
            'kycSubmissionId' => $submissionId,
            'verifiedAt' => $now,
            'updatedAt' => $now
        ]);

        // 更新时间线：当前事件标记为已批准，移除后续待定事件
        $this->timelineModel->addEvent(
            $submissionId,
            $clientId,
            'application_submitted',
            'Application Submitted',
            'All required information and documents submitted',
            null,
            null,
            'completed'
        );
        $this->timelineModel->addEvent(
            $submissionId,
            $clientId,
            'approved',
            'Approved',
            'Your KYC application has been approved',
            null,
            null,
            'completed'
        );

        // 发送审批通过通知
        $this->sendAutoApprovalEmail($clientId, $submissionId);
    }

    private function notifyAdminsOfKycSubmission($submissionId, $clientId) {
        $client = $this->clientModel->findById($clientId);
        if (!$client) {
            return;
        }

        $clientName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($clientName === '') {
            $clientName = $client['email'] ?? ('Client #' . $clientId);
        }

        $clientEmail = $client['email'] ?? 'unknown-email';
        $subject = "New KYC Submission from {$clientName}";
        $message = "Client {$clientName} ({$clientEmail}) has submitted a KYC application for review.";
        $metadata = json_encode([
            'submissionId' => (int)$submissionId,
            'clientId' => (int)$clientId,
            'action' => 'view_kyc_submission',
            'actionUrl' => '/kyc-submissions'
        ]);

        $adminId = !empty($client['accountManagerId']) ? (int)$client['accountManagerId'] : 0;
        $this->createAdminNotification($adminId, $subject, $message, $metadata, 'kyc_submission');
    }

    private function createAdminNotification($adminId, $subject, $message, $metadata, $type) {
        $now = date('Y-m-d H:i:s');

        $notificationId = $this->adminNotificationModel->create([
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'normal',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'createdBy' => null,
            'createdAt' => $now
        ]);

        if (!$notificationId) {
            return;
        }

        $this->adminNotificationDeliveryModel->create([
            'notificationId' => $notificationId,
            'channel' => 'system',
            'status' => 'sent',
            'sentAt' => $now,
            'createdAt' => $now
        ]);

        $this->adminSystemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => $type,
            'metadata' => $metadata,
            'adminId' => $adminId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'createdAt' => $now
        ]);
    }
}
