<?php
/**
 * IB Partner 控制器
 * 管理IB合作伙伴的CRUD操作
 */

require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerStatusHistory.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/IbActivityLog.php';
require_once __DIR__ . '/../models/IbNetworkHierarchy.php';
require_once __DIR__ . '/../models/IbProgramSetting.php';
require_once __DIR__ . '/../models/IbReferralCode.php';
require_once __DIR__ . '/../models/IbReferralSettings.php';
require_once __DIR__ . '/../models/ClientNotification.php';
require_once __DIR__ . '/../models/ClientSystemNotification.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLog/IbInitialOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/IbRiskOperationLog.php';
require_once __DIR__ . '/../services/OperationLog/IbFinalOperationLog.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class IbPartnerController {
    private $ibPartnerModel;
    private $partnerStatusHistoryModel;
    private $partnerBindModel;
    private $activityLogModel;
    private $networkHierarchyModel;
    private $referralCodeModel;
    /** @var IbReferralSettings */
    private $ibReferralSettingsModel;
    /** @var array 公共引用，避免多处 require app.php */
    private $appConfig;

    public function __construct() {
        $this->appConfig = require __DIR__ . '/../config/app.php';
        $this->ibPartnerModel = new IbPartner();
        $this->partnerStatusHistoryModel = new IbPartnerStatusHistory();
        $this->partnerBindModel = new IbPartnerBind();
        $this->activityLogModel = new IbActivityLog();
        $this->networkHierarchyModel = new IbNetworkHierarchy();
        $this->referralCodeModel = new IbReferralCode();
        $this->ibReferralSettingsModel = new IbReferralSettings();
    }

    /**
     * 获取IB合作伙伴列表
     * GET /api/ib-partners
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $search = $_GET['search'] ?? null;

        // 构建筛选条件
        $filters = [];

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['tierLevelId'])) {
            $filters['tierLevelId'] = $_GET['tierLevelId'];
        }

        if (isset($_GET['country'])) {
            $filters['country'] = $_GET['country'];
        }

        if (isset($_GET['ibType'])) {
            $filters['ibType'] = $_GET['ibType'];
        }

        // 支持根据 userId 和 applicationId 查询
        if (isset($_GET['userId'])) {
            $filters['userId'] = $_GET['userId'];
        }

        if (isset($_GET['applicationId'])) {
            $filters['applicationId'] = $_GET['applicationId'];
        }

        // 如果有搜索关键词
        if ($search) {
            $result = $this->ibPartnerModel->searchIbPartners($search, $page, $perPage);
        } else {
            $result = $this->ibPartnerModel->getIbPartners($page, $perPage, $filters);
        }

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取单个IB合作伙伴详情
     * GET /api/ib-partners/{id}
     */
public function show($id) {
        $ibPartner = $this->ibPartnerModel->getIbDetails($id);

        if (!$ibPartner) {
            Response::notFound('IB partner not found');
        }

        $id = (int)$id;
        $baseUrl = rtrim($this->appConfig['client_frontend_url'] ?? 'http://localhost:9502', '/');
        $refRow = $this->ibReferralSettingsModel->getOrCreateByIbPartnerId($id);
        $suffix = trim((string)($refRow['referralSuffix'] ?? ''));
        $ibPartner['ibReferralUrl'] = $baseUrl . '/#/registration/i/' . rawurlencode($suffix);
        $ibPartner['referralSuffix'] = $suffix;

        Response::success($ibPartner);
    }

    /**
     * 更新IB合作伙伴信息
     * PUT /api/ib-partners/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_ib_list'));

        // 验证IB是否存在
        $ibPartner = $this->ibPartnerModel->findById($id);
        if (!$ibPartner) {
            (new AdminOperationLogWriter())->logIbPartnerProfileUpdate(
                $subModule,
                0,
                '',
                [],
                false,
                'IB partner not found'
            );
            Response::notFound('IB partner not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // 可编辑字段
        // clientAlias 不在这里：仅允许 IB 本人通过 /client/ib/dashboard/alias 修改，
        // 避免后台运营误覆盖客户自己起的称呼
        $editableFields = [
            'companyName', 'adminAlias',
            'contactPerson', 'contactEmail', 'contactPhone',
            'country', 'address', 'website', 'status', 'tierLevelId'
        ];

        $updateData = [];
        $changes = [];

        foreach ($editableFields as $field) {
            if (isset($data[$field]) && $data[$field] != $ibPartner[$field]) {
                $changes[$field] = [
                    'old' => $ibPartner[$field],
                    'new' => $data[$field]
                ];
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            (new AdminOperationLogWriter())->logIbPartnerProfileUpdate(
                $subModule,
                (int) $id,
                AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner),
                [],
                false,
                'No changes detected'
            );
            Response::error('No changes detected', 400);
        }

        $updateData['updatedBy'] = $adminId;

        // 更新IB信息
        $this->ibPartnerModel->update($id, $updateData);

        (new AdminOperationLogWriter())->logIbPartnerProfileUpdate(
            $subModule,
            (int) $id,
            AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner),
            $changes
        );

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $id,
            'info_updated',
            'IB partner information updated: ' . implode(', ', array_keys($changes)),
            $adminId,
            'admin',
            ['changes' => $changes],
            $ipAddress
        );

        Response::success(['id' => $id, 'changes' => $changes], 'IB partner updated successfully');
    }

    /**
     * 初审列表：仅返回 status = Pending Initial Review 的 IB，支持按姓名/邮箱搜索与分页
     * GET /api/ib-partners/initial-review
     * Query: page, per_page, search（可选，Search by Name or Email）
     */
    public function initialReviewList() {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
        if ($perPage === 'all' || $perPage === '') {
            $perPage = 0;
        } else {
            $perPage = (int) $perPage;
        }
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : null;
        if ($search === '') {
            $search = null;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ib_initial_review');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage > 0 ? $perPage : 10000);
            return;
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;

        $result = $this->ibPartnerModel->getInitialReviewList(
            $page,
            $perPage > 0 ? $perPage : 10000,
            $search,
            $restrictToSalesId
        );
        $items = $result['items'];
        $timezone = new \DateTimeZone($this->appConfig['timezone'] ?? 'UTC');
        foreach ($items as &$row) {
            $row['statusDisplay'] = IbPartner::statusToDisplay($row['status'] ?? '');
            // 方案 A：接口返回带时区的 ISO 8601，前端按浏览器时区显示
            if (!empty($row['applicationDate'])) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $row['applicationDate'], $timezone);
                if ($dt) {
                    $row['applicationDate'] = $dt->format(\DateTime::ATOM);
                }
            }
        }
        unset($row);

        Response::paginated(
            $items,
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 风险审核列表：仅返回 status = Pending Risk Review 的 IB，支持按姓名/邮箱搜索与分页
     * GET /api/ib-partners/risk-review
     * Query: page, per_page, search（可选）
     */
    public function riskReviewList() {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
        if ($perPage === 'all' || $perPage === '') {
            $perPage = 0;
        } else {
            $perPage = (int) $perPage;
        }
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : null;
        if ($search === '') {
            $search = null;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ib_risk_review');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage > 0 ? $perPage : 10000);
            return;
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;

        $result = $this->ibPartnerModel->getRiskReviewList($page, $perPage > 0 ? $perPage : 10000, $search, $restrictToSalesId);
        $items = $result['items'];
        $timezone = new \DateTimeZone($this->appConfig['timezone'] ?? 'UTC');
        foreach ($items as &$row) {
            $row['statusDisplay'] = IbPartner::statusToDisplay($row['status'] ?? '');
            if (!empty($row['applicationDate'])) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $row['applicationDate'], $timezone);
                if ($dt) {
                    $row['applicationDate'] = $dt->format(\DateTime::ATOM);
                }
            }
        }
        unset($row);

        Response::paginated(
            $items,
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 终审列表：仅返回 status = Pending Final Review 的 IB，支持按姓名/邮箱搜索与分页
     * GET /api/ib-partners/final-review
     */
    public function finalReviewList() {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
        if ($perPage === 'all' || $perPage === '') {
            $perPage = 0;
        } else {
            $perPage = (int) $perPage;
        }
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : null;
        if ($search === '') {
            $search = null;
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_ib_final_review');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage > 0 ? $perPage : 10000);
            return;
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;

        $result = $this->ibPartnerModel->getFinalReviewList($page, $perPage > 0 ? $perPage : 10000, $search, $restrictToSalesId);
        $items = $result['items'];
        $timezone = new \DateTimeZone($this->appConfig['timezone'] ?? 'UTC');
        foreach ($items as &$row) {
            $row['statusDisplay'] = IbPartner::statusToDisplay($row['status'] ?? '');
            if (!empty($row['applicationDate'])) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $row['applicationDate'], $timezone);
                if ($dt) {
                    $row['applicationDate'] = $dt->format(\DateTime::ATOM);
                }
            }
        }
        unset($row);

        Response::paginated(
            $items,
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * IB List：仅显示状态为 Approved 的 IB；返回列表 + 顶部统计（Total IBs / Active / Pending）
     * GET /api/ib-partners/all-list
     */
    public function allList() {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
        if ($perPage === 'all' || $perPage === '') {
            $perPage = 0;
        } else {
            $perPage = (int) $perPage;
        }
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : null;
        if ($search === '') $search = null;

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_iblist');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, $page, $perPage > 0 ? $perPage : 10000);
            return;
        }
        $restrictToSalesId = $scope['scope'] === 'own' ? (int)$scope['restrict_to_sales_id'] : null;

        $stats = $this->ibPartnerModel->getListStats($restrictToSalesId);
        $result = $this->ibPartnerModel->getAllStatusList($page, $perPage > 0 ? $perPage : 10000, $search, 'approved', $restrictToSalesId);
        $items = $result['items'];
        $timezone = new \DateTimeZone($this->appConfig['timezone'] ?? 'UTC');
        $baseUrl = rtrim($this->appConfig['client_frontend_url'] ?? 'http://localhost:9502', '/');
        $ibIds = array_map(function ($r) { return (int)($r['id'] ?? 0); }, $items);
        $ibIds = array_values(array_filter($ibIds));
        $refMap = $this->ibReferralSettingsModel->getOrCreateByIbPartnerIds($ibIds);
        foreach ($items as &$row) {
            $row['statusDisplay'] = IbPartner::statusToDisplay($row['status'] ?? '');
            if (!empty($row['applicationDate'])) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $row['applicationDate'], $timezone);
                if ($dt) {
                    $row['applicationDate'] = $dt->format(\DateTime::ATOM);
                }
            }
            $id = (int)($row['id'] ?? 0);
            $refRow = $refMap[$id] ?? $this->ibReferralSettingsModel->getOrCreateByIbPartnerId($id);
            $suffix = trim((string)($refRow['referralSuffix'] ?? ''));
            $row['ibReferralUrl'] = $baseUrl . '/#/registration/i/' . rawurlencode($suffix);
            $row['referralSuffix'] = $suffix;
        }
        unset($row);

        Response::success([
            'items' => $items,
            'pagination' => [
                'total' => (int) $result['total'],
                'per_page' => (int) $result['per_page'],
                'page' => (int) $result['page'],
                'total_pages' => $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1,
                'has_more' => $result['per_page'] > 0 && ($result['page'] * $result['per_page']) < $result['total']
            ],
            'stats' => $stats
        ]);
    }

    /**
     * 提交初审：将当前用户设为 initialReviewer，更新 ibType、tierLevelId，规则写入 ib_partner_rules，状态改为 pending_risk_review
     * POST /api/ib-partners/{id}/submit-initial-review
     * Body: { ibType, tierLevelId, ruleIds: [] }（不在此接口配置 initialReviewer/riskReviewer/finalReviewer）
     */
    public function submitInitialReview($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            IbInitialOperationLog::logFailure([], 'approve', null, 'initialReviewSubmitFailure', 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientId = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbInitialOperationLog::logFailure($data, 'approve', null, 'initialReviewSubmitFailure', 'IB partner not found');
            Response::notFound('IB partner not found');
        }
        if (($ibPartner['status'] ?? '') !== IbPartner::STATUS_PENDING_INITIAL_REVIEW) {
            IbInitialOperationLog::logFailure(
                $data,
                'approve',
                $clientId > 0 ? $clientId : null,
                'initialReviewSubmitFailure',
                'Only IB in Pending Initial Review can be submitted'
            );
            Response::error('Only IB in Pending Initial Review can be submitted', 400);
        }

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $updateData = [
            'status' => IbPartner::STATUS_PENDING_RISK_REVIEW,
            'initialReviewer' => $adminId,
            'updatedBy' => $adminId
        ];
        if (isset($data['ibType']) && trim((string) $data['ibType']) !== '') {
            $updateData['ibType'] = trim($data['ibType']);
        }
        if (isset($data['tierLevelId']) && $data['tierLevelId'] !== '' && $data['tierLevelId'] !== null) {
            $updateData['tierLevelId'] = (int) $data['tierLevelId'];
        }

        $this->ibPartnerModel->update($id, $updateData);

        $ruleIds = $data['ruleIds'] ?? [];
        if (is_array($ruleIds) && !empty($ruleIds)) {
            $this->ibPartnerModel->setPartnerRulesToIbPartnerRules($id, $ruleIds);
        }

        $this->activityLogModel->logActivity(
            $id,
            'initial_review_submitted',
            'Initial review submitted, status set to Pending Risk Review',
            $adminId
        );

        $this->addPartnerTimeline($id, IbPartner::STATUS_PENDING_INITIAL_REVIEW, IbPartner::STATUS_PENDING_RISK_REVIEW, $adminId, null);

        IbInitialOperationLog::logSubmitInitialReviewSuccess($data, $ibPartner, $id, $updateData, is_array($ruleIds) ? $ruleIds : []);

        Response::success(['id' => $id], 'Initial review submitted successfully');
    }

    /**
     * 拒绝（回退）：将状态回退为 pending_initial_review
     * - 若当前为 pending_risk_review：更新状态、记录 riskReviewer 为当前用户，并记录时间线
     * - 若当前为 pending_final_review：更新状态、记录 finalReviewer 为当前用户，并记录时间线
     * POST /api/ib-partners/{id}/reject
     */
    public function reject($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientIdForLog = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbRiskOperationLog::logFailure(
                $data,
                'reject',
                null,
                'riskReviewRejectFailure',
                'IB partner not found'
            );
            IbFinalOperationLog::logFailure(
                $data,
                'reject',
                null,
                'finalReviewRejectFailure',
                'IB partner not found'
            );
            Response::notFound('IB partner not found');
        }
        $currentStatus = $ibPartner['status'] ?? '';
        $adminId = $this->getCurrentAdminId();

        if ($currentStatus === IbPartner::STATUS_PENDING_RISK_REVIEW) {
            $this->ibPartnerModel->update($id, [
                'status' => IbPartner::STATUS_PENDING_INITIAL_REVIEW,
                'riskReviewer' => $adminId,
                'updatedBy' => $adminId
            ]);
            $this->activityLogModel->logActivity(
                $id,
                'risk_review_rejected',
                'Risk review rejected, status reverted to Pending Initial Review',
                $adminId
            );
            $this->addPartnerTimeline($id, IbPartner::STATUS_PENDING_RISK_REVIEW, IbPartner::STATUS_PENDING_INITIAL_REVIEW, $adminId, null);
            IbRiskOperationLog::logRiskRejectSuccess($data, $ibPartner, $id);
        } elseif ($currentStatus === IbPartner::STATUS_PENDING_FINAL_REVIEW) {
            $this->ibPartnerModel->update($id, [
                'status' => IbPartner::STATUS_PENDING_INITIAL_REVIEW,
                'finalReviewer' => $adminId,
                'updatedBy' => $adminId
            ]);
            $this->activityLogModel->logActivity(
                $id,
                'final_review_rejected',
                'Final review rejected, status reverted to Pending Initial Review',
                $adminId
            );
            $this->addPartnerTimeline($id, IbPartner::STATUS_PENDING_FINAL_REVIEW, IbPartner::STATUS_PENDING_INITIAL_REVIEW, $adminId, null);
            IbFinalOperationLog::logFinalRejectSuccess($data, $ibPartner, $id);
        } else {
            IbRiskOperationLog::logFailure(
                $data,
                'reject',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'riskReviewRejectFailure',
                'Only IB in Pending Risk Review or Pending Final Review can be rejected'
            );
            IbFinalOperationLog::logFailure(
                $data,
                'reject',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'finalReviewRejectFailure',
                'Only IB in Pending Risk Review or Pending Final Review can be rejected'
            );
            Response::error('Only IB in Pending Risk Review or Pending Final Review can be rejected', 400);
        }

        Response::success(['id' => (int) $id], 'Rejection Successful!');
    }

    /**
     * 终审通过：仅当状态为 pending_final_review 时可调用；将状态改为 approved，生成 ibCode（规则：IB-年份-id至少3位补0），记录 finalReviewer 为当前用户
     * POST /api/ib-partners/{id}/approve
     */
    public function approve($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientIdForLog = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbFinalOperationLog::logFailure(
                $data,
                'approve',
                null,
                'finalReviewApproveFailure',
                'IB partner not found'
            );
            Response::notFound('IB partner not found');
        }
        if (($ibPartner['status'] ?? '') !== IbPartner::STATUS_PENDING_FINAL_REVIEW) {
            IbFinalOperationLog::logFailure(
                $data,
                'approve',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'finalReviewApproveFailure',
                'Only IB in Pending Final Review can be approved'
            );
            Response::error('Only IB in Pending Final Review can be approved', 400);
        }

        $adminId = $this->getCurrentAdminId();
        $year = date('Y');
        $idPadded = str_pad((string) (int) $id, 3, '0', STR_PAD_LEFT);
        $ibCode = 'IB-' . $year . '-' . $idPadded;

        $this->ibPartnerModel->update($id, [
            'status' => IbPartner::STATUS_APPROVED,
            'ibCode' => $ibCode,
            'finalReviewer' => $adminId,
            'updatedBy' => $adminId
        ]);

        $this->activityLogModel->logActivity(
            $id,
            'final_review_approved',
            'Final review approved, status set to Approved, ibCode: ' . $ibCode,
            $adminId
        );

        $this->addPartnerTimeline($id, IbPartner::STATUS_PENDING_FINAL_REVIEW, IbPartner::STATUS_APPROVED, $adminId, null);

        IbFinalOperationLog::logApproveSuccess($data, $ibPartner, $id, $ibCode);

        Response::success(['id' => (int) $id, 'ibCode' => $ibCode], 'Approval successful! IB Code: ' . $ibCode);
    }

    /**
     * 可绑定列表：下一级 IB + 完成 KYC 且不在 ibPartners/ibInvitations 的普通客户，支持 search
     * GET /api/ib-partners/{id}/bindables?search=
     * 每项含 itemType: 'ib' | 'client'
     */
    public function getBindables($id) {
        $ibPartner = $this->ibPartnerModel->findById($id);
        if (!$ibPartner) {
            Response::notFound('IB partner not found');
        }
        $search = isset($_GET['search']) ? trim((string) $_GET['search']) : null;
        if ($search === '') {
            $search = null;
        }
        $partners = $this->ibPartnerModel->getBindablePartners((int) $id, $search);
        foreach ($partners as &$p) {
            $p['itemType'] = 'ib';
        }
        unset($p);
        $clients = $this->ibPartnerModel->getBindableClients((int) $id, $search);
        $list = array_merge($partners, $clients);
        Response::success(['items' => $list]);
    }

    /**
     * 绑定：子级 IB（childIds）与普通客户（clientIds）与当前 IB 建立关系
     * POST /api/ib-partners/{id}/bind
     * Body: { childIds: [1, 2, 3], clientIds: [10, 11] }
     */
    public function bind($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            IbFinalOperationLog::logFailure([], 'edit', null, 'finalReviewBindFailure', 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientIdForLog = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbFinalOperationLog::logFailure($data, 'edit', null, 'finalReviewBindFailure', 'IB partner not found');
            Response::notFound('IB partner not found');
        }
        $childIds = $data['childIds'] ?? [];
        $clientIds = $data['clientIds'] ?? [];
        if (!is_array($childIds)) {
            $childIds = [];
        }
        if (!is_array($clientIds)) {
            $clientIds = [];
        }
        $childIds = array_map('intval', $childIds);
        $childIds = array_filter($childIds, function ($v) { return $v > 0; });
        $clientIds = array_map('intval', $clientIds);
        $clientIds = array_filter($clientIds, function ($v) { return $v > 0; });
        if (empty($childIds) && empty($clientIds)) {
            IbFinalOperationLog::logFailure(
                $data,
                'edit',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'finalReviewBindFailure',
                'Please select at least one client to bind'
            );
            Response::error('Please select at least one client to bind', 400);
        }
        $count = $this->partnerBindModel->addBinds((int) $id, $childIds);
        $clientBound = 0;
        if (!empty($clientIds)) {
            $this->partnerBindModel->removeClientBindsByClientIds($clientIds);
            $clientBound = $this->partnerBindModel->addClientBinds((int) $id, $clientIds);
        }
        if ($count > 0 || $clientBound > 0) {
            $this->ibPartnerModel->syncAllTotalClientsFromBindTable();
        }
        $total = $count + $clientBound;

        IbFinalOperationLog::logBindSuccess(
            $data,
            $ibPartner,
            $id,
            array_values($childIds),
            array_values($clientIds),
            $count,
            $clientBound
        );

        Response::success(['bound' => $total], $total > 0 ? "Bound {$total} relationship(s) successfully." : 'No new bindings added.');
    }

    /**
     * 已绑定子级列表：与当前 IB 直接绑定的下一级，供 Unbind 弹窗展示
     * GET /api/ib-partners/{id}/bound-children
     */
    public function getBoundChildren($id) {
        $ibPartner = $this->ibPartnerModel->findById($id);
        if (!$ibPartner) {
            Response::notFound('IB partner not found');
        }
        $list = $this->ibPartnerModel->getBoundChildren((int) $id);
        Response::success(['items' => $list]);
    }

    /**
     * 解绑：子级 IB（childIds）与普通客户（clientIds）
     * POST /api/ib-partners/{id}/unbind
     * Body: { childIds: [1, 2, 3], clientIds: [10, 11] }
     */
    public function unbind($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            IbFinalOperationLog::logFailure([], 'edit', null, 'finalReviewUnbindFailure', 'Invalid JSON body');
            Response::error('Invalid JSON body', 400);
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientIdForLog = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbFinalOperationLog::logFailure($data, 'edit', null, 'finalReviewUnbindFailure', 'IB partner not found');
            Response::notFound('IB partner not found');
        }
        $childIds = $data['childIds'] ?? [];
        $clientIds = $data['clientIds'] ?? [];
        if (!is_array($childIds)) {
            $childIds = [];
        }
        if (!is_array($clientIds)) {
            $clientIds = [];
        }
        $childIds = array_map('intval', $childIds);
        $childIds = array_filter($childIds, function ($v) { return $v > 0; });
        $clientIds = array_map('intval', $clientIds);
        $clientIds = array_filter($clientIds, function ($v) { return $v > 0; });
        if (empty($childIds) && empty($clientIds)) {
            IbFinalOperationLog::logFailure(
                $data,
                'edit',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'finalReviewUnbindFailure',
                'Please select at least one to unbind'
            );
            Response::error('Please select at least one to unbind', 400);
        }
        if (!empty($childIds)) {
            $result = $this->ibPartnerModel->validateUnbindChildren((int) $id, $childIds);
            if (!($result[0] ?? false)) {
                IbFinalOperationLog::logFailure(
                    $data,
                    'edit',
                    $clientIdForLog > 0 ? $clientIdForLog : null,
                    'finalReviewUnbindFailure',
                    $result[1] ?? 'Cannot unbind.'
                );
                Response::error($result[1] ?? 'Cannot unbind.', 400);
            }
        }
        $removed = $this->partnerBindModel->removeBinds((int) $id, $childIds);
        $clientUnbound = 0;
        if (!empty($clientIds)) {
            $clientUnbound = $this->partnerBindModel->removeClientBinds((int) $id, $clientIds);
        }
        if ($removed > 0 || $clientUnbound > 0) {
            $this->ibPartnerModel->syncAllTotalClientsFromBindTable();
        }

        IbFinalOperationLog::logUnbindSuccess(
            $data,
            $ibPartner,
            $id,
            array_values($childIds),
            array_values($clientIds),
            $removed,
            $clientUnbound
        );

        Response::success(['unbound' => $removed + $clientUnbound], 'Unbinding successful!');
    }

    /**
     * 提交风险审核：记录 riskReviewer 为当前用户，状态改为 pending_final_review，可选保存组别（多选写入 ib_partner_trading_groups，ibPartners.groupId 存主组别 id 以兼容旧逻辑）
     * POST /api/ib-partners/{id}/submit-risk-review
     * Body: { groupIds?: number[] } 或兼容旧版 { groupId?: number }
     */
    public function submitRiskReview($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientIdForLog = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbRiskOperationLog::logFailure(
                $data,
                'approve',
                null,
                'riskReviewSubmitFailure',
                'IB partner not found'
            );
            Response::notFound('IB partner not found');
        }
        if (($ibPartner['status'] ?? '') !== IbPartner::STATUS_PENDING_RISK_REVIEW) {
            IbRiskOperationLog::logFailure(
                $data,
                'approve',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'riskReviewSubmitFailure',
                'Only IB in Pending Risk Review can be submitted'
            );
            Response::error('Only IB in Pending Risk Review can be submitted', 400);
        }

        $adminId = $this->getCurrentAdminId();
        $updateData = [
            'status' => IbPartner::STATUS_PENDING_FINAL_REVIEW,
            'riskReviewer' => $adminId,
            'updatedBy' => $adminId
        ];

        /** @var int[]|null null 表示不写入关联表；非 null 则整表替换为该 ID 列表（可为空数组以清空） */
        $tradingGroupIdsToSave = null;
        if (array_key_exists('groupIds', $data)) {
            $rawIds = $data['groupIds'];
            $groupIds = is_array($rawIds) ? $rawIds : [];
            $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), function ($v) {
                return $v > 0;
            })));
            $tradingGroupIdsToSave = $groupIds;
            $updateData['groupId'] = !empty($groupIds) ? min($groupIds) : null;
        } elseif (isset($data['groupId']) && ($data['groupId'] !== '' && $data['groupId'] !== null)) {
            $gid = (int) $data['groupId'];
            if ($gid > 0) {
                $tradingGroupIdsToSave = [$gid];
                $updateData['groupId'] = $gid;
            }
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            if ($tradingGroupIdsToSave !== null) {
                $this->ibPartnerModel->setPartnerTradingGroups((int) $id, $tradingGroupIdsToSave);
            }
            $this->ibPartnerModel->update($id, $updateData);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            Logger::error('submitRiskReview failed: ' . $e->getMessage());
            IbRiskOperationLog::logFailure(
                $data,
                'approve',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'riskReviewSubmitFailure',
                'Failed to save risk review'
            );
            Response::error('Failed to save risk review', 500);
        }

        $this->activityLogModel->logActivity(
            $id,
            'risk_review_submitted',
            'Risk review submitted, status set to Pending Final Review',
            $adminId
        );

        $this->addPartnerTimeline($id, IbPartner::STATUS_PENDING_RISK_REVIEW, IbPartner::STATUS_PENDING_FINAL_REVIEW, $adminId, null);

        $groupIdsForLog = $tradingGroupIdsToSave !== null ? $tradingGroupIdsToSave : [];
        IbRiskOperationLog::logSubmitRiskReviewSuccess($data, $ibPartner, $id, $groupIdsForLog);

        Response::success(['id' => (int) $id], 'Risk review submitted successfully');
    }

    /**
     * 终审通过后编辑：查询改 Tier Level 前的 IB↔IB 绑定阻碍（不含普通客户）
     * GET /api/ib-partners/{id}/tier-change-blockers
     */
    public function getTierChangeBlockers($id) {
        $ibPartner = $this->ibPartnerModel->findById($id);
        if (!$ibPartner) {
            Response::error('IB partner not found', 404, null, '40238');
        }
        if (($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            Response::error('Only approved IB partners can use this endpoint', 400, null, '40230');
        }
        $data = $this->ibPartnerModel->getIbIbTierChangeBlockers((int) $id);
        Response::success($data);
    }

    /**
     * 终审通过后修正 IB Type、Tier Level、规则、组别（状态保持 approved）
     * POST /api/ib-partners/{id}/post-approval-update
     * Body: { ibType?, tierLevelId?, ruleIds: [], groupIds: [] }
     */
    public function postApprovalUpdate($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            IbFinalOperationLog::logFailure([], 'edit', null, 'finalReviewPostApprovalUpdateFailure', 'Invalid JSON body');
            Response::error('Invalid JSON body', 400, null, '40234');
        }

        $ibPartner = $this->ibPartnerModel->findById($id);
        $clientIdForLog = is_array($ibPartner) ? (int) ($ibPartner['userId'] ?? 0) : 0;
        if (!$ibPartner) {
            IbFinalOperationLog::logFailure($data, 'edit', null, 'finalReviewPostApprovalUpdateFailure', 'IB partner not found');
            Response::error('IB partner not found', 404, null, '40238');
        }
        if (($ibPartner['status'] ?? '') !== IbPartner::STATUS_APPROVED) {
            IbFinalOperationLog::logFailure(
                $data,
                'edit',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'finalReviewPostApprovalUpdateFailure',
                'Only approved IB partners can be updated'
            );
            Response::error('Only approved IB partners can be updated', 400, null, '40231');
        }

        $adminId = $this->getCurrentAdminId();

        $oldTierId = isset($ibPartner['tierLevelId']) && $ibPartner['tierLevelId'] !== '' && $ibPartner['tierLevelId'] !== null
            ? (int) $ibPartner['tierLevelId'] : 0;

        $newTierId = null;
        if (array_key_exists('tierLevelId', $data) && $data['tierLevelId'] !== '' && $data['tierLevelId'] !== null) {
            $newTierId = (int) $data['tierLevelId'];
        }

        if ($newTierId !== null && $newTierId > 0 && $newTierId !== $oldTierId) {
            $blockers = $this->ibPartnerModel->getIbIbTierChangeBlockers((int) $id);
            if (!empty($blockers['parentIb']) || !empty($blockers['childIbs'])) {
                IbFinalOperationLog::logFailure(
                    $data,
                    'edit',
                    $clientIdForLog > 0 ? $clientIdForLog : null,
                    'finalReviewPostApprovalUpdateFailure',
                    'Cannot change Tier Level while IB binding relationships exist. Unbind the listed parent or child IBs first.'
                );
                Response::error(
                    'Cannot change Tier Level while IB binding relationships exist. Unbind the listed parent or child IBs first.',
                    400,
                    ['blockers' => $blockers],
                    '40232'
                );
            }
        }

        $updateData = ['updatedBy' => $adminId];
        if (isset($data['ibType']) && trim((string) $data['ibType']) !== '') {
            $updateData['ibType'] = trim((string) $data['ibType']);
        }
        if ($newTierId !== null && $newTierId > 0) {
            $updateData['tierLevelId'] = $newTierId;
        }

        $ruleIds = [];
        $ruleIdsProvided = array_key_exists('ruleIds', $data);
        if ($ruleIdsProvided) {
            $rawRules = $data['ruleIds'];
            if (!is_array($rawRules)) {
                IbFinalOperationLog::logFailure(
                    $data,
                    'edit',
                    $clientIdForLog > 0 ? $clientIdForLog : null,
                    'finalReviewPostApprovalUpdateFailure',
                    'ruleIds must be an array'
                );
                Response::error('ruleIds must be an array', 400, null, '40235');
            }
            $ruleIds = array_values(array_unique(array_filter(array_map('intval', $rawRules), function ($v) {
                return $v > 0;
            })));
        }

        $groupIds = null;
        $groupIdsProvided = array_key_exists('groupIds', $data);
        if ($groupIdsProvided) {
            $rawGroups = $data['groupIds'];
            if (!is_array($rawGroups)) {
                IbFinalOperationLog::logFailure(
                    $data,
                    'edit',
                    $clientIdForLog > 0 ? $clientIdForLog : null,
                    'finalReviewPostApprovalUpdateFailure',
                    'groupIds must be an array'
                );
                Response::error('groupIds must be an array', 400, null, '40236');
            }
            $groupIds = array_values(array_unique(array_filter(array_map('intval', $rawGroups), function ($v) {
                return $v > 0;
            })));
            $updateData['groupId'] = !empty($groupIds) ? min($groupIds) : null;
        }

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            if ($ruleIdsProvided) {
                $this->ibPartnerModel->setPartnerRulesToIbPartnerRules((int) $id, $ruleIds);
            }
            if ($groupIds !== null) {
                $this->ibPartnerModel->setPartnerTradingGroups((int) $id, $groupIds);
            }
            $this->ibPartnerModel->update($id, $updateData);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            Logger::error('postApprovalUpdate failed: ' . $e->getMessage());
            IbFinalOperationLog::logFailure(
                $data,
                'edit',
                $clientIdForLog > 0 ? $clientIdForLog : null,
                'finalReviewPostApprovalUpdateFailure',
                'Failed to save changes'
            );
            Response::error('Failed to save changes', 500, null, '40233');
        }

        $this->activityLogModel->logActivity(
            (int) $id,
            'post_approval_details_updated',
            'IB details updated after approval (ibType, tier level, commission rules, trading groups)',
            $adminId,
            'admin',
            [
                'tierLevelId' => $newTierId !== null && $newTierId > 0 ? $newTierId : null,
                'ruleCount' => $ruleIdsProvided ? count($ruleIds) : null,
                'groupCount' => $groupIds !== null ? count($groupIds) : null,
            ]
        );

        $this->addPartnerTimeline(
            (int) $id,
            IbPartner::STATUS_APPROVED,
            IbPartner::STATUS_APPROVED,
            $adminId,
            'IB profile details were updated by an administrator after approval.'
        );

        IbFinalOperationLog::logPostApprovalUpdateSuccess(
            $data,
            $ibPartner,
            $id,
            $updateData,
            $ruleIds,
            $ruleIdsProvided,
            $groupIds,
            $groupIdsProvided
        );

        Response::success(['id' => (int) $id], 'IB details updated successfully');
    }

    /**
     * 添加 Partner 时间线（写入 ibPartnerStatusHistory，供客户端 ib-dashboard 展示）
     * @param int $partnerId ibPartners.id
     * @param string $previousStatus
     * @param string $newStatus
     * @param int|null $adminId
     * @param string|null $notes
     */
    private function addPartnerTimeline($partnerId, $previousStatus, $newStatus, $adminId, $notes = null) {
        if (empty($partnerId)) {
            return;
        }
        try {
            $this->partnerStatusHistoryModel->logStatusHistory(
                (int) $partnerId,
                $previousStatus,
                $newStatus,
                $adminId,
                $notes
            );
        } catch (\Throwable $e) {
            Logger::error('IbPartner addPartnerTimeline failed: ' . $e->getMessage());
        }
    }

    private function getCurrentAdminId() {
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        return $payload['userId'];
    }

    /**
     * 获取IB统计数据
     * GET /api/ib-partners/statistics
     */
    public function statistics() {
        $stats = $this->ibPartnerModel->getIbStatistics();
        Response::success($stats);
    }

    /**
     * 获取IB客户列表
     * GET /api/ib-partners/{id}/clients
     */
    public function getClients($id) {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;

        $result = $this->ibPartnerModel->getIbClients($id, $page, $perPage);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取IB佣金历史
     * GET /api/ib-partners/{id}/commissions
     */
    public function getCommissions($id) {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;

        $result = $this->ibPartnerModel->getCommissionHistory($id, $page, $perPage);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * 获取IB网络层级树（与客户端 Your IB Network 同结构：id, name, code, initials, type, hasChildren, children）
     * GET /api/ib-partners/{id}/network
     */
    public function getNetwork($id) {
        $maxDepthRaw = $_GET['max_depth'] ?? null;
        if ($maxDepthRaw === null || $maxDepthRaw === '' || (int) $maxDepthRaw <= 0) {
            $maxDepth = (new IbProgramSetting())->getNetworkMaxDepth();
        } else {
            $maxDepth = (int) $maxDepthRaw;
        }
        $id = (int) $id;
        if ($id <= 0) {
            Response::error('Invalid IB partner id', 400);
        }
        $members = $this->buildNetworkTreeForDisplay($id, $maxDepth);
        Response::success($members);
    }

    /**
     * 递归构建网络树（与 ClientIbDashboard 的 getNetworkMembers 一致，供 Detail 关系网图使用）
     */
    private function buildNetworkTreeForDisplay($ibPartnerId, $maxDepth, $currentDepth = 0) {
        if ($currentDepth >= $maxDepth) {
            return [];
        }
        $children = $this->partnerBindModel->getDirectBindChildren($ibPartnerId);
        $members = [];
        foreach ($children as $c) {
            $childId = (int) $c['id'];
            $type = ($c['type'] ?? '') === 'Sub-IB' ? 'ib' : 'client';
            $name = trim($c['referralName'] ?? '') ?: ($c['email'] ?? '—');
            $code = $c['referralCode'] ?? '';
            $initials = $this->getInitialsForNetwork($name);
            $nodeId = $type === 'ib' ? ('ib-' . $childId) : ('client-' . $childId);
            $subMembers = [];
            if ($type === 'ib') {
                $subMembers = $this->buildNetworkTreeForDisplay($childId, $maxDepth, $currentDepth + 1);
            }
            $members[] = [
                'id' => $nodeId,
                'name' => $name,
                'code' => $code,
                // Admin alias shown on network nodes so multi-link IB campaigns are identifiable
                'adminAlias' => $type === 'ib' ? ($c['adminAlias'] ?? '') : '',
                'initials' => $initials,
                'type' => $type,
                // clientUsers.id：Sub-IB 取其绑定用户，Client 即自身，供前端跳转客户详情页
                'clientUserId' => $type === 'ib' ? (int) ($c['userId'] ?? 0) : $childId,
                'hasChildren' => count($subMembers) > 0,
                'children' => $subMembers
            ];
        }
        return $members;
    }

    private function getInitialsForNetwork($name) {
        $name = trim((string) $name);
        if ($name === '') return '—';
        $words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
        }
        return strtoupper(substr($name, 0, 2)) ?: '—';
    }

    /**
     * 获取指定 IB 的网络统计（与客户端 Dashboard getNetworkStatistics 一致）
     * GET /api/ib-partners/{id}/network-stats
     */
    public function getNetworkStats($id) {
        $id = (int) $id;
        if ($id <= 0) {
            Response::error('Invalid IB partner id', 400);
        }
        $allSubIbs = $this->partnerBindModel->getAllSubIbs($id, null);
        $allIbIds = [$id];
        $subIbPartnerIds = [];
        foreach ($allSubIbs as $subIb) {
            $allIbIds[] = $subIb['ibPartnerId'];
            $subIbPartnerIds[] = $subIb['ibPartnerId'];
        }
        $tier2Ibs = 0;
        $tier3Ibs = 0;
        if (!empty($subIbPartnerIds)) {
            $db = Database::getInstance();
            $placeholders = implode(',', array_fill(0, count($subIbPartnerIds), '?'));
            $sql = "SELECT tl.tierLevel, COUNT(*) AS cnt FROM ibPartners ib
                    LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
                    WHERE ib.id IN ({$placeholders})
                    GROUP BY tl.tierLevel";
            $rows = $db->fetchAll($sql, $subIbPartnerIds);
            foreach ($rows as $r) {
                $level = (int)($r['tierLevel'] ?? 0);
                $cnt = (int)($r['cnt'] ?? 0);
                if ($level === 2) $tier2Ibs = $cnt;
                if ($level === 3) $tier3Ibs = $cnt;
            }
        }
        $ibUserIds = [];
        if (!empty($allIbIds)) {
            $placeholders = [];
            $params = [];
            foreach ($allIbIds as $index => $ibId) {
                $key = "ibId{$index}";
                $placeholders[] = ":{$key}";
                $params[$key] = $ibId;
            }
            $sql = "SELECT DISTINCT userId FROM ibPartners WHERE id IN (" . implode(',', $placeholders) . ") AND userId IS NOT NULL";
            $db = Database::getInstance();
            $ibUsers = $db->fetchAll($sql, $params);
            foreach ($ibUsers as $ibUser) {
                $ibUserIds[] = $ibUser['userId'];
            }
        }
        $directClients = 0;
        $whereClause = "WHERE b.isClient = 1";
        $params = [];
        if (!empty($allIbIds)) {
            foreach ($allIbIds as $i => $ibId) {
                $params["ibId{$i}"] = $ibId;
            }
            $whereClause .= " AND b.parentId IN (" . implode(',', array_map(function ($i) { return ":ibId{$i}"; }, array_keys($allIbIds))) . ")";
        }
        if (!empty($ibUserIds)) {
            foreach ($ibUserIds as $i => $uid) {
                $params["userId{$i}"] = $uid;
            }
            $whereClause .= " AND b.childClientId NOT IN (" . implode(',', array_map(function ($i) { return ":userId{$i}"; }, array_keys($ibUserIds))) . ")";
        }
        $sql = "SELECT COUNT(DISTINCT b.childClientId) as count FROM ib_partner_bind b " . $whereClause;
        $db = Database::getInstance();
        $result = $db->fetchOne($sql, $params);
        $directClients = (int)($result['count'] ?? 0);
        $subIbs = $this->partnerBindModel->getDirectSubIbsCount($id, null);
        Response::success([
            'totalNetwork' => $directClients + count($allSubIbs),
            'directClients' => $directClients,
            'subIbs' => $subIbs,
            'tier2Ibs' => $tier2Ibs,
            'tier3Ibs' => $tier3Ibs
        ]);
    }

    /**
     * 分配佣金规则
     * POST /api/ib-partners/{id}/rules
     */
    public function assignRule($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证
        $errors = Validator::validate($data, [
            'ruleId' => 'required|numeric'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 分配规则
        $this->ibPartnerModel->assignRule($id, $data['ruleId'], $adminId);

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $id,
            'rule_assigned',
            'Commission rule assigned: Rule ID ' . $data['ruleId'],
            $adminId
        );

        Response::success(null, 'Rule assigned successfully');
    }

    /**
     * 移除佣金规则
     * DELETE /api/ib-partners/{id}/rules/{ruleId}
     */
    public function removeRule($id, $ruleId) {
        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 移除规则
        $this->ibPartnerModel->removeRule($id, $ruleId);

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $id,
            'rule_removed',
            'Commission rule removed: Rule ID ' . $ruleId,
            $adminId
        );

        Response::success(null, 'Rule removed successfully');
    }

    /**
     * 获取IB活动日志
     * GET /api/ib-partners/{id}/activities
     */
    public function getActivities($id) {
        $limit = $_GET['limit'] ?? 50;

        $activities = $this->activityLogModel->getIbActivities($id, $limit);

        Response::success($activities);
    }

    /**
     * 导出IB列表
     * POST /api/ib-partners/export
     */
    public function export() {
        $data = json_decode(file_get_contents('php://input'), true);
        $format = $data['format'] ?? 'csv';
        $selectedIds = $data['selectedIds'] ?? [];

        // 获取要导出的IB数据
        if (empty($selectedIds)) {
            Response::error('No IB partners selected for export', 400);
        }

        // 使用模型方法获取IB数据
        $ibPartners = $this->ibPartnerModel->getIbPartnersForExport($selectedIds);

        Response::success([
            'format' => $format,
            'count' => count($ibPartners),
            'data' => $ibPartners
        ], 'Export data prepared successfully');
    }

    /**
     * 获取IB合作伙伴的文档（从 ibPartnerDocumentAcknowledgements 表）
     * GET /api/ib-partners/{id}/documents
     */
    public function getDocuments($ibPartnerId) {
        // 验证IB是否存在
        $ibPartner = $this->ibPartnerModel->findById($ibPartnerId);
        if (!$ibPartner) {
            Response::notFound('IB partner not found');
        }

        // 从 ibPartnerDocumentAcknowledgements 表获取文档
        $documents = $this->ibPartnerModel->getDocuments($ibPartnerId);

        // 格式化文档数据
        $formattedDocuments = [];
        foreach ($documents as $doc) {
            $formattedDocuments[] = [
                'id' => $doc['id'],
                'documentType' => 'ib_agreement',
                'title' => $doc['title'],
                'content' => $doc['content'],
                'signedAt' => $doc['signedAt'],
                'source' => 'ib_application',
                'iconClass' => $doc['iconClass'] ?? 'fas fa-file-contract',
                'iconGradient' => $doc['iconGradient'] ?? null,
                'isRequired' => (bool)$doc['isRequired'],
                'displayOrder' => (int)$doc['displayOrder']
            ];
        }

        Response::success($formattedDocuments);
    }

    /**
     * 保存IB规则配置
     * POST /api/ib-partners/{id}/rules/save
     */
    public function saveRules($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证IB是否存在
        $ibPartner = $this->ibPartnerModel->findById($id);
        if (!$ibPartner) {
            Response::notFound('IB partner not found');
        }

        // 获取当前用户
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        // 验证数据
        if (!isset($data['ruleIds']) || !is_array($data['ruleIds'])) {
            Response::validationError(['ruleIds' => 'Rule IDs array is required']);
        }

        // 保存规则配置
        $this->ibPartnerModel->saveRules($id, $data, $adminId);

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $id,
            'rules_saved',
            'Commission rules saved: ' . count($data['ruleIds']) . ' rules',
            $adminId
        );

        // 发送通知给客户端
        try {
            $this->sendRulesUpdatedNotification($ibPartner, count($data['ruleIds']));
        } catch (Exception $e) {
            // 通知发送失败不影响保存操作
            Logger::error("Failed to send rules updated notification to IB partner #{$id}: " . $e->getMessage());
        }

        Response::success(null, 'Rules saved successfully');
    }

    /**
     * 发送规则更新通知给客户端
     * @param array $ibPartner IB合作伙伴信息
     * @param int $ruleCount 规则数量
     */
    private function sendRulesUpdatedNotification($ibPartner, $ruleCount) {
        // 获取客户端用户ID
        $clientId = $ibPartner['userId'] ?? null;
        if (!$clientId) {
            // 如果没有userId，尝试通过contactEmail查找
            if (isset($ibPartner['contactEmail']) && !empty($ibPartner['contactEmail'])) {
                require_once __DIR__ . '/../models/ClientUser.php';
                $clientUserModel = new ClientUser();
                $client = $clientUserModel->findByEmail($ibPartner['contactEmail'], false);
                if ($client) {
                    $clientId = $client['id'];
                }
            }
        }

        if (!$clientId) {
            // 如果找不到客户端用户，不发送通知
            return;
        }

        // 获取品牌配置
        $teamName = $this->appConfig['branding']['teamName'] ?? $this->appConfig['branding']['companyName'] ?? $this->appConfig['logoname'] ?? 'Trading Platform Team';

        // 准备通知内容
        $subject = 'Your IB Commission Rules Have Been Updated';
        $message = "Your IB commission rules have been updated. {$ruleCount} rule(s) have been configured. Please review the changes in your IB dashboard.";

        // 1. 创建 clientNotifications 记录（主通知表）
        $clientNotificationModel = new ClientNotification();
        $notificationId = $clientNotificationModel->create([
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'priority' => 'normal',
            'scheduleType' => 'immediate',
            'status' => 'sent',
            'emailTemplate' => null,
            'createdBy' => null, // 系统创建
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ]);

        if (!$notificationId) {
            Logger::error("Failed to create clientNotifications record for IB partner #{$ibPartner['id']}");
            return;
        }

        // 2. 创建 clientSystemNotifications 记录（客户端通过这个表读取通知）
        $systemNotificationModel = new ClientSystemNotification();
        $systemNotificationModel->create([
            'notificationId' => $notificationId,
            'type' => 'ib_rules_updated',
            'metadata' => json_encode([
                'ibPartnerId' => $ibPartner['id'],
                'ibCode' => $ibPartner['ibCode'] ?? null,
                'ruleCount' => $ruleCount
            ]),
            'clientId' => $clientId,
            'subject' => $subject,
            'message' => $message,
            'isRead' => 0,
            'readAt' => null,
            'createdAt' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 更新 IB 推荐链接后缀（仅后缀可编辑，与 Sales referral-suffix 一致）
     * POST /api/ib-partners/{id}/referral-suffix  Body: { "suffix": "xxx" }
     */
    public function updateReferralSuffix($id) {
        $id = (int)$id;
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $subModule = OperationLogPages::resolveLogClient($body, OperationLogPages::subModuleKeyByAlias('page_ib_list'));
        $opLog = new AdminOperationLogWriter();

        if ($id <= 0) {
            $opLog->logIbReferralSuffixUpdate($subModule, 0, '', '—', '', false, 'Invalid IB partner id');
            Response::error('Invalid IB partner id', 400);
        }
        $ibPartner = $this->ibPartnerModel->findById($id);
        if (!$ibPartner) {
            $opLog->logIbReferralSuffixUpdate($subModule, 0, '', '—', '', false, 'IB partner not found');
            Response::notFound('IB partner not found');
        }
        $displayName = AdminOperationLogWriter::formatIbPartnerDisplayName($ibPartner);
        $suffix = isset($body['suffix']) ? trim((string)$body['suffix']) : '';
        if ($suffix === '') {
            $opLog->logIbReferralSuffixUpdate($subModule, $id, $displayName, '—', '', false, 'Suffix is required and cannot be empty');
            Response::error('Suffix is required and cannot be empty', 400);
        }
        if (strlen($suffix) > 100) {
            $opLog->logIbReferralSuffixUpdate($subModule, $id, $displayName, '—', '', false, 'Suffix must be at most 100 characters');
            Response::error('Suffix must be at most 100 characters', 400);
        }
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $suffix)) {
            $opLog->logIbReferralSuffixUpdate(
                $subModule,
                $id,
                $displayName,
                '—',
                '',
                false,
                'Suffix may only contain letters, numbers, hyphens and underscores'
            );
            Response::error('Suffix may only contain letters, numbers, hyphens and underscores', 400);
        }
        if ($this->ibReferralSettingsModel->isSuffixTakenByOther($suffix, $id)) {
            $opLog->logIbReferralSuffixUpdate(
                $subModule,
                $id,
                $displayName,
                '—',
                '',
                false,
                'This referral code is already in use by another IB'
            );
            Response::error('This referral code is already in use by another IB', 400);
        }
        $oldSuffix = '';
        $existing = $this->ibReferralSettingsModel->getByIbPartnerId($id);
        if ($existing && !empty($existing['referralSuffix'])) {
            $oldSuffix = (string) $existing['referralSuffix'];
        }
        $this->ibReferralSettingsModel->setSuffix($id, $suffix);
        $baseUrl = rtrim($this->appConfig['client_frontend_url'] ?? 'http://localhost:9502', '/');
        $ibReferralUrl = $baseUrl . '/#/registration/i/' . rawurlencode($suffix);

        $opLog->logIbReferralSuffixUpdate(
            $subModule,
            $id,
            $displayName,
            $oldSuffix !== '' ? $oldSuffix : '—',
            $suffix
        );

        Response::success([
            'ibReferralUrl' => $ibReferralUrl,
            'suffix' => $suffix,
        ]);
    }
}
