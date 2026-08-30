<?php
/**
 * IB Invitation 控制器
 * 管理IB邀请功能
 */

require_once __DIR__ . '/../models/IbInvitation.php';
require_once __DIR__ . '/../models/IbActivityLog.php';
require_once __DIR__ . '/../models/EmailTemplate.php';
require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/IbDocumentTemplate.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerStatusHistory.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/IbApplication.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../utils/Encryption.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../services/OperationLog/IbInitialOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class IbInvitationController {
    private $invitationModel;
    private $activityLogModel;
    private $emailTemplateModel;
    private $clientUserModel;
    private $documentTemplateModel;
    private $emailSender;
    private $ibApplicationModel;

    public function __construct() {
        $this->invitationModel = new IbInvitation();
        $this->activityLogModel = new IbActivityLog();
        $this->emailTemplateModel = new EmailTemplate();
        $this->clientUserModel = new ClientUser();
        $this->documentTemplateModel = new IbDocumentTemplate();
        $this->emailSender = new EmailSender();
        $this->ibApplicationModel = new IbApplication();
    }

    /**
     * 发送IB邀请
     * POST /api/ib-invitations
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            IbInitialOperationLog::logFailure([], 'add', null, 'invitationCreateFailure', 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        $clientIdForLog = isset($data['clientId']) ? (int) $data['clientId'] : null;

        // 验证
        $validator = new Validator($data, [
            'clientId' => 'required|numeric',
            'selectedDocuments' => 'array'
        ]);

        if (!$validator->validate()) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'invitationCreateFailure',
                OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors())
            );
            Response::validationError($validator->getErrors());
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $skipSign = isset($data['skipSign']) && filter_var($data['skipSign'], FILTER_VALIDATE_BOOLEAN);

        $salesFilter = AdminSalesPermission::getCurrentSalesOnlyFilter();
        if (!empty($salesFilter['is_sales_only']) && !empty($salesFilter['admin_user_id'])) {
            $salesBinding = Database::getInstance()->fetchOne(
                'SELECT 1 FROM sales_bind WHERE salesId = :salesId AND clientId = :clientId LIMIT 1',
                [
                    'salesId' => (int)$salesFilter['admin_user_id'],
                    'clientId' => (int)$data['clientId']
                ]
            );
            if (!$salesBinding) {
                IbInitialOperationLog::logFailure(
                    $data,
                    'add',
                    $clientIdForLog,
                    'invitationCreateFailure',
                    'You can only invite clients bound to your sales account'
                );
                Response::forbidden('You can only invite clients bound to your sales account');
            }
        }

        // 检查客户是否已有待处理的邀请；skipSign 会直接创建 IB，并复用该邀请记录作为审计轨迹。
        $existing = $this->invitationModel->queryOne(
            "SELECT * FROM ibInvitations
             WHERE clientId = :clientId AND invitationStatus IN ('sent', 'viewed')
             ORDER BY id DESC
             LIMIT 1",
            ['clientId' => $data['clientId']]
        );

        if ($existing && !$skipSign) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                $clientIdForLog,
                'invitationCreateFailure',
                'Client already has a pending invitation'
            );
            Response::error('Client already has a pending invitation', 409);
        }

        // 检查客户是否已与 IB 存在绑定关系（ib_partner_bind），若有则不允许邀请，需先解绑
        require_once __DIR__ . '/../utils/Database.php';
        $binding = Database::getInstance()->fetchOne(
            'SELECT 1 FROM ib_partner_bind WHERE childClientId = :clientId AND isClient = 1 LIMIT 1',
            ['clientId' => $data['clientId']]
        );
        if ($binding) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                $clientIdForLog,
                'invitationCreateFailure',
                'This user is already bound to an IB. Please unbind first before sending an invitation.'
            );
            Response::error('This user is already bound to an IB. Please unbind first before sending an invitation.', 400);
        }

        if ($skipSign) {
            $result = $this->createInitialReviewIbFromInvitation($data, $adminId, $existing);
            IbInitialOperationLog::logInvitationSkipSignSuccess(
                $data,
                (int) $data['clientId'],
                (int) ($result['ibPartnerId'] ?? 0)
            );
            Response::created($result, 'IB partner moved to initial review successfully');
        }

        // 创建邀请
        $invitation = $this->invitationModel->createInvitation(
            $data['clientId'],
            $adminId,
            $data['invitationMessage'] ?? null,
            $data['selectedDocuments'] ?? []
        );

        // 发送邀请邮件
        try {
            $this->sendInvitationEmail($invitation, $data['clientId'], $data['invitationMessage'] ?? null, $data['selectedDocuments'] ?? [], $data);
        } catch (Exception $e) {
            // 记录错误但不阻止邀请创建
            error_log('Failed to send IB invitation email: ' . $e->getMessage());
        }

        IbInitialOperationLog::logInvitationEmailSuccess($data, (int) $data['clientId']);

        Response::created($invitation, 'Invitation sent successfully');
    }

    /**
     * skipSign 邀请不发签署邮件，直接创建 pending_initial_review 的 IB 记录。
     */
    private function createInitialReviewIbFromInvitation(array $data, $adminId, $existingInvitation = null) {
        $clientId = (int)$data['clientId'];
        $client = $this->clientUserModel->findById($clientId);
        if (!$client) {
            IbInitialOperationLog::logFailure($data, 'add', $clientId, 'invitationCreateFailure', 'Client not found');
            Response::notFound('Client not found');
        }

        $ibPartnerModel = new IbPartner();
        $existingIb = $ibPartnerModel->getByClientId($clientId);
        if ($existingIb) {
            IbInitialOperationLog::logFailure(
                $data,
                'add',
                $clientId,
                'invitationCreateFailure',
                'This client already has an IB partner record'
            );
            Response::error('This client already has an IB partner record', 409);
        }

        $ibPartnerId = $ibPartnerModel->create([
            'ibCode' => '',
            'userId' => $clientId,
            'companyName' => '',
            'status' => IbPartner::STATUS_PENDING_INITIAL_REVIEW,
            'ibType' => 'individual',
            'contactEmail' => $client['email'] ?? '',
            'registrationDate' => date('Y-m-d H:i:s'),
        ]);

        // 新建 IB 后挂 IB 标签，clients list 据此区分/筛选 IB
        $ibPartnerModel->tagClientAsIb((int)$clientId);

        $invitation = $existingInvitation ?: $this->invitationModel->createInvitation(
            $clientId,
            $adminId,
            $data['invitationMessage'] ?? null,
            []
        );
        $this->invitationModel->acceptInvitation((int)$invitation['id'], $ibPartnerId);

        $fullName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : ($client['email'] ?? ('Client #' . $clientId));
        $this->activityLogModel->logActivity(
            $ibPartnerId,
            'ib_partner_created',
            'IB invitation skipped signing and moved to Initial Review: ' . $displayName,
            $adminId,
            'admin',
            ['invitationId' => (int)$invitation['id'], 'skipSign' => true]
        );

        try {
            $statusHistoryModel = new IbPartnerStatusHistory();
            $statusHistoryModel->logStatusHistory(
                $ibPartnerId,
                null,
                IbPartner::STATUS_PENDING_INITIAL_REVIEW,
                $adminId,
                null
            );
        } catch (\Throwable $e) {
            error_log('IbInvitation skipSign addPartnerTimeline failed: ' . $e->getMessage());
        }

        return [
            'id' => (int)$invitation['id'],
            'ibPartnerId' => $ibPartnerId,
            'invitationStatus' => 'accepted',
            'skipSign' => true
        ];
    }

    /**
     * 获取邀请列表
     * GET /api/ib-invitations
     */
    public function index() {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 10)));
        $status = $_GET['status'] ?? null;

        $offset = ($page - 1) * $perPage;
        $params = [];
        $whereClause = '';
        if (is_string($status) && trim($status) !== '') {
            $whereClause = 'WHERE inv.invitationStatus = :invitationStatus';
            $params['invitationStatus'] = trim($status);
        }

        $sql = "SELECT
                    inv.*,
                    cu.firstName as clientFirstName,
                    cu.lastName as clientLastName,
                    cu.email as clientEmail,
                    au.fullName as invitedByName
                FROM ibInvitations inv
                INNER JOIN clientUsers cu ON inv.clientId = cu.id
                LEFT JOIN adminUsers au ON inv.invitedBy = au.id
                {$whereClause}
                ORDER BY inv.sentAt DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count FROM ibInvitations inv {$whereClause}";

        $items = $this->invitationModel->query($sql, $params);
        $totalResult = $this->invitationModel->queryOne($countSql, $params);
        $total = $totalResult ? (int)$totalResult['count'] : 0;

        Response::paginated($items, $total, $page, $perPage);
    }

    /**
     * 根据邀请码获取邀请
     * GET /api/ib-invitations/verify/{code}
     */
    public function verifyInvitation($code) {
        $validation = $this->invitationModel->validateInvitation($code);

        if (!$validation['valid']) {
            Response::error($validation['reason'], 400);
        }

        Response::success($validation['invitation']);
    }

    /**
     * 接受邀请
     * POST /api/ib-invitations/{code}/accept
     */
    public function acceptInvitation($code) {
        $validation = $this->invitationModel->validateInvitation($code);

        if (!$validation['valid']) {
            Response::error($validation['reason'], 400);
        }

        $invitation = $validation['invitation'];

        // TODO: 创建IB申请或直接创建IB合作伙伴
        // 这里简化处理，标记为已接受

        $this->invitationModel->acceptInvitation($invitation['id'], null);

        Response::success(null, 'Invitation accepted successfully');
    }

    /**
     * 拒绝邀请
     * POST /api/ib-invitations/{code}/decline
     */
    public function declineInvitation($code) {
        $validation = $this->invitationModel->validateInvitation($code);

        if (!$validation['valid']) {
            Response::error($validation['reason'], 400);
        }

        $invitation = $validation['invitation'];

        $this->invitationModel->declineInvitation($invitation['id']);

        Response::success(null, 'Invitation declined');
    }

    /**
     * 发送IB邀请邮件
     * @param array $invitation 邀请信息
     * @param int $clientId 客户ID
     * @param string|null $customMessage 自定义消息
     * @param array $documentIds 文档ID列表
     * @param array $requestData 请求数据（包含 useHashMode 等参数）
     */
    private function sendInvitationEmail($invitation, $clientId, $customMessage = null, $documentIds = [], $requestData = []) {
        // 获取邮件模板
        $template = $this->emailTemplateModel->getByKey('ib_invitation');

        if (!$template) {
            throw new Exception('IB invitation email template not found');
        }

        // 获取客户信息
        $client = $this->clientUserModel->findById($clientId);
        if (!$client) {
            throw new Exception('Client not found');
        }

        // 获取管理员信息
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $adminUserModel = new AdminUser();
        $admin = $adminUserModel->findById($adminId);
        $invitedByName = $admin ? ($admin['fullName'] ?? 'Admin') : 'Admin';

        // 获取文档信息（保留 id 与 documentTitle，用于构建带链接的列表）
        $documents = [];
        if (!empty($documentIds)) {
            foreach ($documentIds as $docId) {
                $doc = $this->documentTemplateModel->findById($docId);
                if ($doc) {
                    $documents[] = [
                        'id' => (int) $doc['id'],
                        'documentTitle' => $doc['documentTitle'] ?? 'Document'
                    ];
                }
            }
        }

        // 获取配置信息
        $config = require __DIR__ . '/../config/app.php';
        $frontendUrl = $config['client_frontend_url'] ?? 'http://localhost:3000';
        $platformName = $config['branding']['companyName'] ?? $config['logoname'] ?? 'Trading Platform';
        $teamName = $config['branding']['teamName'] ?? 'The Team';

        // 根据前端传递的 useHashMode 参数决定链接格式
        // 如果使用 Hash 模式（Vue Router Hash 模式），链接格式为 origin/#/path，如 http://localhost:9502/#/ib/invitation/xxx
        $useHashMode = isset($requestData['useHashMode']) && $requestData['useHashMode'] === true;

        // 加密邀请码并构建接受链接
        $encryptedCode = Encryption::encryptInvitationCode($invitation['invitationCode']);
        $acceptLink = rtrim($frontendUrl, '/') . ($useHashMode ? '/#/' : '/') . 'ib/invitation/' . $encryptedCode;

        // 文档查看链接：仅 index.php?t= 加密令牌，不暴露接口与参数，仅后端可解码
        $documentViewBaseUrl = rtrim($config['file_base_url'] ?? '', '/') . '/index.php';

        // 构建自定义消息部分
        $customMessageSection = '';
        if (!empty($customMessage)) {
            $customMessageSection = '<div style="background: #e6f3ff; padding: 15px; border-radius: 6px; margin: 15px 0;">
                <strong>Personal Message:</strong>
                <p style="margin: 10px 0 0 0;">' . htmlspecialchars($customMessage) . '</p>
            </div>';
        }

        // 构建文档列表部分：每个文档为可点击链接（蓝色字+下划线），新开 tab 查看文档详情
        $documentsSection = '';
        if (!empty($documents)) {
            require_once __DIR__ . '/../utils/MailLinkToken.php';
            $docListHtml = '<ul style="margin: 0; padding-left: 20px;">';
            foreach ($documents as $doc) {
                $docToken = MailLinkToken::encode([
                    'type' => MailLinkToken::TYPE_IB_DOCUMENT,
                    'documentId' => (int) $doc['id'],
                    'code' => $encryptedCode,
                ]);
                $docViewUrl = $documentViewBaseUrl . '?t=' . rawurlencode($docToken);
                $title = htmlspecialchars($doc['documentTitle']);
                $docListHtml .= '<li style="margin: 6px 0;"><a href="' . htmlspecialchars($docViewUrl) . '" target="_blank" style="color: #2563eb; text-decoration: underline;">' . $title . '</a></li>';
            }
            $docListHtml .= '</ul>';
            $documentsSection = '<div class="documents-list">
                <strong>Required Documents to Review:</strong>
                ' . $docListHtml . '
                <p style="margin-top: 10px; font-size: 13px; color: #718096;">
                    Please review and sign these documents as part of the IB partnership process. Click each document link to open its content in a new tab.
                </p>
            </div>';
        }

        // 准备变量（只包含标量类型，数组已转换为 HTML 字符串）
        $variables = [
            'clientName' => trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? '')),
            'invitationCode' => $invitation['invitationCode'],
            'expiryDate' => date('F d, Y', strtotime($invitation['expiresAt'])),
            'acceptLink' => $acceptLink,
            'invitedByName' => $invitedByName,
            'currentYear' => date('Y'),
            'platformName' => $platformName,
            'customMessageSection' => $customMessageSection,
            'documentsSection' => $documentsSection
        ];

        // 替换邮件主题和内容中的变量
        $subject = EmailTemplate::replaceVariables($template['emailSubject'], $variables);
        $body = EmailTemplate::replaceVariables($template['emailBody'], $variables);

        // 强制「Accept Invitation」按钮文字为白色、背景为蓝，避免各邮件客户端显示不一致
        $body = preg_replace(
            '/class="button"\s*>\s*Accept Invitation\s*<\/a>/',
            'class="button" style="color:#ffffff !important; background:#2563eb;">Accept Invitation</a>',
            $body
        );

        // 发送邮件
        $clientEmail = $client['email'] ?? null;
        if (!$clientEmail) {
            throw new Exception('Client email not found');
        }

        $this->emailSender->send($clientEmail, $subject, $body);
    }

    /**
     * 在浏览器新 tab 中查看单个文档内容（邮件内链接打开，无需登录）
     * 邮件链接通过 index.php?t=加密令牌 进入，由 MailLinkToken 解码后调用（不暴露接口路径）
     * 返回 HTML 页面，样式为蓝色链接+下划线风格
     */
    public function viewDocument($documentId, $encryptedCode) {
        $documentId = (int) $documentId;
        if ($documentId <= 0) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Invalid document.</p></body></html>';
            exit;
        }
        $invitationCode = $encryptedCode ? Encryption::decryptInvitationCode($encryptedCode) : null;
        if (!$invitationCode) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Invalid or expired link.</p></body></html>';
            exit;
        }
        $invitation = $this->invitationModel->getByCode($invitationCode);
        if (!$invitation || !isset($invitation['selectedDocuments'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Invitation not found or document not in this invitation.</p></body></html>';
            exit;
        }
        $selectedIds = is_string($invitation['selectedDocuments']) ? json_decode($invitation['selectedDocuments'], true) : $invitation['selectedDocuments'];
        if (!is_array($selectedIds) || !in_array($documentId, $selectedIds)) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Document not part of this invitation.</p></body></html>';
            exit;
        }
        $doc = $this->documentTemplateModel->findById($documentId);
        if (!$doc) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Document not found.</p></body></html>';
            exit;
        }
        $title = htmlspecialchars($doc['documentTitle'] ?? 'Document');
        $content = $doc['documentContent'] ?? '<p>No content.</p>';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . '</title>';
        echo '<style>body{font-family:system-ui,sans-serif;padding:24px;line-height:1.6;max-width:800px;margin:0 auto;} a{color:#2563eb;text-decoration:underline;}</style>';
        echo '</head><body><h1>' . $title . '</h1><div class="document-content">' . $content . '</div></body></html>';
        exit;
    }

    /**
     * 验证加密的邀请码（客户端调用）
     * POST /api/ib-invitations/verify
     */
    public function verifyEncryptedInvitation() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['encryptedCode'])) {
            Response::error('Encrypted code is required', 400);
        }

        // 解密邀请码
        $invitationCode = Encryption::decryptInvitationCode($data['encryptedCode']);

        if (!$invitationCode) {
            Response::error('Invalid invitation link', 400);
        }

        // 获取邀请信息（不验证状态，我们需要检查是否已接受）
        $invitation = $this->invitationModel->getByCode($invitationCode);

        if (!$invitation) {
            Response::error('Invitation not found', 404);
        }

        // 如果邀请已被接受，返回特殊状态
        if ($invitation['invitationStatus'] === 'accepted') {
            Response::success([
                'invitationCode' => $invitation['invitationCode'],
                'clientId' => $invitation['clientId'],
                'invitationStatus' => 'accepted',
                'alreadyAccepted' => true
            ], 'Invitation already accepted');
        }

        // 验证邀请码是否有效（未接受状态）
        $validation = $this->invitationModel->validateInvitation($invitationCode);

        if (!$validation['valid']) {
            Response::error($validation['reason'], 400);
        }

        // 获取当前登录用户（客户端）
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Authentication required', 401);
        }

        try {
            $payload = JWT::decode($token);
            $clientId = $payload['userId'];

            // 检查邀请码是否匹配当前用户
            if ($invitation['clientId'] != $clientId) {
                Response::error('This invitation is not for your account', 403);
            }

            // 如果邀请状态是 sent，标记为 viewed（表示用户已验证并查看了邀请）
            if ($invitation['invitationStatus'] === 'sent') {
                $this->invitationModel->markAsViewed($invitation['id']);
                $invitation['invitationStatus'] = 'viewed';
            }

            // 验证通过，但不自动接受邀请（需要用户在IB Dashboard页面点击同意按钮）
            // 返回邀请信息
            Response::success([
                'invitationCode' => $invitation['invitationCode'],
                'clientId' => $invitation['clientId'],
                'expiresAt' => $invitation['expiresAt'],
                'invitationStatus' => $invitation['invitationStatus'],
                'invitationId' => $invitation['id'],
                'alreadyAccepted' => false
            ]);
        } catch (Exception $e) {
            Response::error('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    /**
     * 获取当前用户的IB状态（客户端调用）
     * GET /api/ib-invitations/my-status
     */
    public function getMyStatus() {
        // 获取当前登录用户
        $token = JWT::getTokenFromHeader();
        if (!$token) {
            Response::error('Authentication required', 401);
        }

        try {
            $payload = JWT::decode($token);
            $clientId = $payload['userId'];

            // 检查是否有邀请（状态为 sent, viewed, 或 accepted，排除 declined 和 expired）
            $sql = "SELECT * FROM ibInvitations
                    WHERE clientId = :clientId
                    AND invitationStatus IN ('sent', 'viewed', 'accepted')
                    ORDER BY sentAt DESC
                    LIMIT 1";
            $invitation = $this->invitationModel->queryOne($sql, ['clientId' => $clientId]);

            // 检查是否有已接受的邀请
            $acceptedInvitation = $this->invitationModel->findOne([
                'clientId' => $clientId,
                'invitationStatus' => 'accepted'
            ]);

            // 检查IB申请状态（通过邮箱匹配）
            $client = $this->clientUserModel->findById($clientId);
            $ibApplication = null;
            if ($client && $client['email']) {
                $sql = "SELECT * FROM ibApplications
                        WHERE applicantEmail = :email
                        ORDER BY applicationDate DESC
                        LIMIT 1";
                $ibApplication = $this->ibApplicationModel->queryOne($sql, ['email' => $client['email']]);
            }

            $hasInvitation = !empty($invitation); // 有邀请（已验证但未接受，或已接受）
            $hasAcceptedInvitation = !empty($acceptedInvitation); // 已接受的邀请
            $hasApprovedApplication = !empty($ibApplication) && $ibApplication['applicationStatus'] === 'approved';
            // 显示条件：有邀请（已验证）且没有已审核通过的申请
            $showIbProgram = $hasInvitation && !$hasApprovedApplication;

            // 解析 selectedDocuments（如果是JSON字符串）
            $selectedDocuments = null;
            if ($invitation && isset($invitation['selectedDocuments'])) {
                if (is_string($invitation['selectedDocuments'])) {
                    $selectedDocuments = json_decode($invitation['selectedDocuments'], true);
                } else {
                    $selectedDocuments = $invitation['selectedDocuments'];
                }
            }

            Response::success([
                'hasActiveInvitation' => $hasInvitation,
                'hasAcceptedInvitation' => $hasAcceptedInvitation,
                'hasApprovedApplication' => $hasApprovedApplication,
                'showIbProgram' => $showIbProgram,
                'invitation' => $invitation ? [
                    'invitationCode' => $invitation['invitationCode'],
                    'invitationStatus' => $invitation['invitationStatus'],
                    'acceptedAt' => $invitation['respondedAt'] ?? $invitation['sentAt'],
                    'selectedDocuments' => $selectedDocuments
                ] : null,
                'application' => $ibApplication ? [
                    'id' => $ibApplication['id'],
                    'applicationStatus' => $ibApplication['applicationStatus'],
                    'applicationDate' => $ibApplication['applicationDate']
                ] : null
            ]);
        } catch (Exception $e) {
            Response::error('Invalid token: ' . $e->getMessage(), 401);
        }
    }
}
