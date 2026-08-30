<?php
/**
 * 客户管理控制器
 * 专门用于后台管理已验证的客户
 */

require_once __DIR__ . '/../models/ClientUser.php';
require_once __DIR__ . '/../models/ClientKycSubmission.php';
require_once __DIR__ . '/../models/Countrylist.php';
require_once __DIR__ . '/../models/LeadTag.php';
require_once __DIR__ . '/../models/LeadTagAssignment.php';
require_once __DIR__ . '/../models/ClientActivityLog.php';
require_once __DIR__ . '/../models/AdminUser.php';
require_once __DIR__ . '/../models/AdminPermission.php';
require_once __DIR__ . '/../models/KycTemplate.php';
require_once __DIR__ . '/../models/KycTimeline.php';
require_once __DIR__ . '/../models/LegalDocument.php';
require_once __DIR__ . '/../models/LegalDocumentSignature.php';
require_once __DIR__ . '/../models/TradingAccountExternalAccount.php';
require_once __DIR__ . '/../models/TradingAccount.php';
require_once __DIR__ . '/../models/TradingAccountBalance.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/TradingPlatform.php';
require_once __DIR__ . '/../models/ClientPasswordReset.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationSubmission.php';
require_once __DIR__ . '/../models/ClientWithdrawalVerificationAnswer.php';
require_once __DIR__ . '/../models/IbPartner.php';
require_once __DIR__ . '/../models/IbPartnerBind.php';
require_once __DIR__ . '/../models/IbReferralSettings.php';
require_once __DIR__ . '/../controllers/ClientCommissionReportController.php';
require_once __DIR__ . '/../utils/FinanceProApiClient.php';
require_once __DIR__ . '/../utils/Mt5ApiClient.php';
require_once __DIR__ . '/../utils/Mt5GatewayApiClient.php';
require_once __DIR__ . '/../models/FinanceProToken.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/EmailSender.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/AdminSalesPermission.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class ClientManagementController {
    private $userModel;
    private $countryListModel;
    private $kycSubmissionModel;
    private $tagModel;
    private $tagAssignmentModel;
    private $activityLogModel;
    private $adminUserModel;
    private $templateModel;
    private $timelineModel;
    private $externalAccountModel;
    private $accountModel;
    private $balanceModel;
    private $orderModel;
    private $platformModel;
    private $passwordResetModel;
    private $withdrawalSubmissionModel;
    private $withdrawalAnswerModel;
    private $ibPartnerModel;
    private $ibPartnerBindModel;
    private $ibReferralSettingsModel;
    private $appConfig;
    private $financePro;
    private $financeProClient;

    public function __construct() {
        $this->userModel = new ClientUser();
        $this->kycSubmissionModel = new ClientKycSubmission();
        $this->tagModel = new LeadTag();
        $this->countryListModel = new Countrylist();
        $this->tagAssignmentModel = new LeadTagAssignment();
        $this->activityLogModel = new ClientActivityLog();
        $this->adminUserModel = new AdminUser();
        $this->templateModel = new KycTemplate();
        $this->timelineModel = new KycTimeline();
        $this->externalAccountModel = new TradingAccountExternalAccount();
        $this->accountModel = new TradingAccount();
        $this->balanceModel = new TradingAccountBalance();
        $this->orderModel = new Order();
        $this->platformModel = new TradingPlatform();
        $this->passwordResetModel = new ClientPasswordReset();
        $this->withdrawalSubmissionModel = new ClientWithdrawalVerificationSubmission();
        $this->withdrawalAnswerModel = new ClientWithdrawalVerificationAnswer();
        $this->ibPartnerModel = new IbPartner();
        $this->ibPartnerBindModel = new IbPartnerBind();
        $this->ibReferralSettingsModel = new IbReferralSettings();

        $this->appConfig = require __DIR__ . '/../config/app.php';
        $this->financePro = $this->appConfig['integrations']['finance_pro'] ?? [];
        $this->financeProClient = new FinanceProApiClient($this->financePro);
    }

    /**
     * 查找国家匹配的 KYC 模板
     */
    private function findCountrySpecificTemplate(?string $countryCode) {
        if (!$countryCode) {
            return null;
        }

        $normalized = strtoupper(trim((string)$countryCode));
        if ($normalized === '') {
            return null;
        }

        $sql = "SELECT t.*
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                  AND tc.countryCode = :countryCode
                ORDER BY t.displayOrder ASC, t.id ASC
                LIMIT 1";

        return $this->templateModel->queryOne($sql, ['countryCode' => $normalized]);
    }

    /**
     * 查找全球通用模板
     */
    private function findGlobalTemplate() {
        $sql = "SELECT t.*
                FROM kycTemplates t
                INNER JOIN kycTemplateCountries tc ON t.id = tc.templateId
                WHERE t.status = 'active'
                  AND tc.countryCode = 'ALL'
                ORDER BY t.displayOrder ASC, t.id ASC
                LIMIT 1";

        return $this->templateModel->queryOne($sql);
    }

    /**
     * 获取默认模板（第一个活跃模板）
     */
    private function findDefaultTemplate() {
        $templates = $this->templateModel->findAll(
            ['status' => 'active'],
            'displayOrder ASC, id ASC',
            1
        );

        return !empty($templates) ? $templates[0] : null;
    }

    /**
     * 根据客户国家选择合适的 KYC 模板
     */
    private function selectBestTemplateForClient(array $client) {
        $countryCode = $client['country'] ?? null;

        $template = $this->findCountrySpecificTemplate($countryCode);
        if ($template) {
            return $template;
        }

        $template = $this->findGlobalTemplate();
        if ($template) {
            return $template;
        }

        return $this->findDefaultTemplate();
    }

    /**
     * 统一处理客户端记录，提取结构化字段
     */
    private function transformClientRecord(array $client): array {
        // 解析备注中的结构化信息
        if (!empty($client['notes'])) {
            $decoded = json_decode($client['notes'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                if (empty($client['depositCurrency']) && !empty($decoded['depositCurrency'])) {
                    $client['depositCurrency'] = strtoupper($decoded['depositCurrency']);
                }
                if (!empty($decoded['kycOption'])) {
                    $client['kycOption'] = $decoded['kycOption'];
                }
            }
        }

        if (!empty($client['depositCurrency'])) {
            $client['depositCurrency'] = strtoupper($client['depositCurrency']);
        }

        if (isset($client['id'])) {
            $client['id'] = (int)$client['id'];
        }
        if (isset($client['assignedTo']) && $client['assignedTo'] !== null) {
            $client['assignedTo'] = (int)$client['assignedTo'];
        }
        if (isset($client['accountManagerId']) && $client['accountManagerId'] !== null) {
            $client['accountManagerId'] = (int)$client['accountManagerId'];
        }
        if (isset($client['kycSubmissionId']) && $client['kycSubmissionId'] !== null) {
            $client['kycSubmissionId'] = (int)$client['kycSubmissionId'];
        }
        if (array_key_exists('accountManagerNote', $client)) {
            $client['managerNote'] = $client['accountManagerNote'];
        }

        if (!empty($client['accountManagerAssignedAt']) && $client['accountManagerAssignedAt'] !== '0000-00-00 00:00:00') {
            $client['accountManagerAssignedAt'] = date('c', strtotime($client['accountManagerAssignedAt']));
        }

        $managerName = $client['managerName'] ?? null;
        $managerEmail = $client['managerEmail'] ?? null;
        if ($managerName) {
            $client['manager'] = $managerName;
        } elseif ($managerEmail) {
            $client['manager'] = $managerEmail;
        } else {
            $client['manager'] = null;
        }

        $managerLookupId = $client['accountManagerId'] ?? null;
        if (!$client['manager'] && $managerLookupId) {
            $managerInfo = $this->adminUserModel->findById($managerLookupId);
            if ($managerInfo) {
                $client['manager'] = $managerInfo['fullName'] ?? $managerInfo['email'] ?? $client['manager'];
                if (!$managerName) {
                    $client['managerName'] = $managerInfo['fullName'] ?? null;
                }
                if (!$managerEmail) {
                    $client['managerEmail'] = $managerInfo['email'] ?? null;
                }
            }
        }

        unset($client['managerName'], $client['managerEmail']);

        return $client;
    }

    /**
     * 客户详情查无记录时区分：ID 不存在 vs 存在但不在销售可见范围。
     */
    private function respondAdminClientDetailMissing(int $clientId, ?int $restrictToSalesId): void {
        if ($restrictToSalesId !== null && $this->userModel->existsAdminClient($clientId)) {
            Response::error('You do not have permission to perform this action', 403, null, 'CLIENT_VIEW_FORBIDDEN');
        }
        Response::error('Client not found', 404, null, 'CLIENT_NOT_FOUND');
    }

    private function normalizeTradingStatus($status): ?string {
        if ($status === null || $status === '') {
            return null;
        }

        if ((string)$status === '1' || $status === true) {
            return 'Active';
        }

        if ((string)$status === '0' || $status === false) {
            return 'Inactive';
        }

        return (string)$status;
    }

    /**
     * 本地同步表保存了平台资金快照，用它给后台详情做兜底，避免平台接口失败时资金字段缺失。
     */
    private function buildStoredBalanceSummary(array $externalAccount): array {
        $tradingAccountId = $externalAccount['tradingAccountId'] ?? null;
        if (empty($tradingAccountId)) {
            return [];
        }

        try {
            $storedBalance = $this->balanceModel->getByTradingAccountId(
                (int)$tradingAccountId,
                $externalAccount['platformKey'] ?? null
            );
        } catch (Exception $e) {
            return [];
        }

        if (!$storedBalance) {
            return [];
        }

        $summary = [];
        $fieldMap = [
            'balance' => 'Balance',
            'equity' => 'Equity',
            'margin' => 'Margin',
            'free_margin' => 'FreeMargin',
            'margin_level' => 'MarginLevel',
            'total' => 'Total',
            'upl' => 'FloatingPl',
            'credit' => 'Credit'
        ];

        foreach ($fieldMap as $storedField => $summaryField) {
            if (isset($storedBalance[$storedField]) && $storedBalance[$storedField] !== '') {
                $summary[$summaryField] = (float)$storedBalance[$storedField];
            }
        }

        if (!empty($storedBalance['currency'])) {
            $summary['Currency'] = $storedBalance['currency'];
        }

        return $summary;
    }

    private function mergeBalanceSummary(array $summary, array $balanceSummary): array {
        foreach (['Balance', 'Equity', 'Margin', 'FreeMargin', 'MarginLevel', 'Total', 'Currency', 'FloatingPl', 'Credit'] as $field) {
            if (isset($balanceSummary[$field]) && $balanceSummary[$field] !== '') {
                $summary[$field] = $balanceSummary[$field];
            }
        }

        return $summary;
    }

    private function buildMt5BalanceSummary(array $balance): array {
        $summary = [];
        $fieldMap = [
            'balance' => 'Balance',
            'equity' => 'Equity',
            'margin' => 'Margin',
            'marginFree' => 'FreeMargin',
            'marginLevel' => 'MarginLevel'
        ];

        foreach ($fieldMap as $sourceField => $summaryField) {
            if (isset($balance[$sourceField]) && $balance[$sourceField] !== '') {
                $summary[$summaryField] = (float)$balance[$sourceField];
            }
        }

        return $summary;
    }

    private function buildTradingAccountListItem(array $account): array {
        $summary = $account['accountSummary'] ?? [];
        $info = $account['accountInfo'] ?? [];
        $external = $account['externalAccount'] ?? [];

        $createdAt = $info['CreatorTime']
            ?? $external['creatorTime']
            ?? $external['createdAt']
            ?? null;

        return [
            'platformKey' => $account['platformKey'] ?? null,
            'platformName' => $external['platformName'] ?? null,
            // accountId 是平台 login（展示用）；tradingAccountId 才是 CRM 主键，管理操作按它定位
            'tradingAccountId' => $external['tradingAccountId'] ?? null,
            'accountId' => $account['accountId'] ?? null,
            'accountNumber' => $external['accountNumber'] ?? null,
            'login' => ($account['platformKey'] ?? null) === 'mt5'
                ? ($account['accountId'] ?? $external['providerAccountId'] ?? null)
                : ($info['PredefinedLogin'] ?? $external['predefinedLogin'] ?? null),
            'name' => $info['Name'] ?? $external['name'] ?? null,
            'status' => $this->normalizeTradingStatus($info['Status'] ?? $summary['Status'] ?? $external['status'] ?? null),
            'country' => $info['Country'] ?? $external['country'] ?? null,
            'createdAt' => $createdAt,
            'currency' => $summary['Currency'] ?? $external['accountCurrency'] ?? null,
            'total' => $summary['Total'] ?? null,
            'balance' => $summary['Balance'] ?? $summary['platformBalance'] ?? $external['platformBalance'] ?? null,
            'equity' => $summary['Equity'] ?? null,
            'margin' => $summary['Margin'] ?? null,
            'freeMargin' => $summary['FreeMargin'] ?? null,
            'floatingPl' => $summary['FloatingPl'] ?? null,
            'credit' => $summary['Credit'] ?? null,
            'leverage' => $info['Leverage'] ?? $summary['Leverage'] ?? $external['leverage'] ?? null,
            'accountType' => $external['accountType'] ?? null,
            'assignedCommissionRuleId' => isset($external['assignedCommissionRuleId']) && $external['assignedCommissionRuleId'] !== null
                ? (int) $external['assignedCommissionRuleId']
                : null,
            'assignedCommissionIbPartnerId' => isset($external['assignedCommissionIbPartnerId']) && $external['assignedCommissionIbPartnerId'] !== null
                ? (int) $external['assignedCommissionIbPartnerId']
                : null,
            'assignedCommissionRuleName' => $external['assignedCommissionRuleName'] ?? null,
            'assignedCommissionRuleType' => $external['assignedCommissionRuleType'] ?? null,
            'assignedCommissionRuleFixedAmount' => isset($external['assignedCommissionRuleFixedAmount']) && $external['assignedCommissionRuleFixedAmount'] !== null
                ? (float) $external['assignedCommissionRuleFixedAmount']
                : null,
            'assignedCommissionRuleRebateAmount' => isset($external['assignedCommissionRuleRebateAmount']) && $external['assignedCommissionRuleRebateAmount'] !== null
                ? (float) $external['assignedCommissionRuleRebateAmount']
                : null,
            'assignedCommissionRuleIbCode' => $external['assignedCommissionRuleIbCode'] ?? null,
        ];
    }

    private function numericOrZero($value): float {
        return ($value !== null && $value !== '' && is_numeric($value)) ? (float)$value : 0.0;
    }

    private function getClientTransactionTotals(int $userId): array {
        $db = Database::getInstance();

        $depositRow = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS totalDeposit
             FROM deposits
             WHERE userId = :userId
               AND status = 'completed'",
            ['userId' => $userId]
        );

        $withdrawRow = $db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS totalWithdraw
             FROM withdrawals
             WHERE userId = :userId
               AND status IN ('pending', 'processing', 'completed')",
            ['userId' => $userId]
        );

        return [
            'totalDeposit' => round((float)($depositRow['totalDeposit'] ?? 0), 2),
            'totalWithdraw' => round((float)($withdrawRow['totalWithdraw'] ?? 0), 2)
        ];
    }

    private function buildClientOverview(int $userId, ?string $fallbackCurrency = null): array {
        $overview = [
            'currency' => 'USD',
            'walletBalance' => 0.0,
            'totalBalance' => 0.0,
            'totalEquity' => 0.0,
            'totalFloatingPl' => 0.0,
            'totalCredit' => 0.0,
            'totalDeposit' => 0.0,
            'totalWithdraw' => 0.0,
        ];

        $externalAccounts = $this->externalAccountModel->findAllByUserIdWithPlatform($userId);
        foreach ($externalAccounts as $externalAccount) {
            $summary = $this->buildStoredBalanceSummary($externalAccount);
            $balance = $summary['Balance'] ?? $externalAccount['platformBalance'] ?? 0;
            $equity = $summary['Equity'] ?? $balance;
            $floatingPl = $summary['FloatingPl'] ?? ($this->numericOrZero($equity) - $this->numericOrZero($balance));
            // credit 直接读 tradingAccountExternalAccounts.platformCredit（OrderSyncService 写入的快照）
            $credit = $externalAccount['platformCredit'] ?? ($summary['Credit'] ?? 0);

            $overview['totalBalance'] += $this->numericOrZero($balance);
            $overview['totalEquity'] += $this->numericOrZero($equity);
            $overview['totalFloatingPl'] += $this->numericOrZero($floatingPl);
            $overview['totalCredit'] += $this->numericOrZero($credit);

            // Overview is reported in USD; individual account currencies stay on trading rows.
        }

        $transactionTotals = $this->getClientTransactionTotals($userId);
        $overview['totalDeposit'] = $transactionTotals['totalDeposit'];
        $overview['totalWithdraw'] = $transactionTotals['totalWithdraw'];

        // 钱包可用余额：复用 WalletBalanceService，和打钱弹窗/IB 列表同一口径
        require_once __DIR__ . '/../services/WalletBalanceService.php';
        $overview['walletBalance'] = round((new WalletBalanceService())->getAvailableBalance($userId), 2);

        foreach (['totalBalance', 'totalEquity', 'totalFloatingPl', 'totalCredit'] as $field) {
            $overview[$field] = round((float)$overview[$field], 2);
        }

        return $overview;
    }

    private function getClientIbInfo(int $clientId): array {
        $ibPartners = $this->ibPartnerModel->getAllByClientId($clientId);
        foreach ($ibPartners as &$ibPartner) {
            $ibPartner['id'] = !empty($ibPartner['id']) ? (int)$ibPartner['id'] : null;
            $ibPartner['userId'] = !empty($ibPartner['userId']) ? (int)$ibPartner['userId'] : null;
            $ibPartner['tierLevelId'] = !empty($ibPartner['tierLevelId']) ? (int)$ibPartner['tierLevelId'] : null;
            $ibPartner['statusDisplay'] = IbPartner::statusToDisplay($ibPartner['status'] ?? '');
        }
        unset($ibPartner);
        $primaryIb = $ibPartners[0] ?? null;
        $primaryIbId = !empty($primaryIb['id']) ? (int)$primaryIb['id'] : null;

        // 该客户的直属上级 IB：普通客户取直属推荐 IB，客户本身是 IB 则取它自己 IB 的上级 IB；
        // 没有上级 IB 则为 null，前端据此决定是否展示 IB Referal 板块
        $directIbPartnerId = $this->resolveClientUplineStartIbId($clientId, $primaryIbId);

        return [
            'ibPartnerId' => $primaryIbId,
            'ibPartners' => $ibPartners,
            'directIbPartnerId' => $directIbPartnerId
        ];
    }

    /**
     * 解析客户在 IB 网络里的「直属上级 IB」，也是拼上级链路（upline）的起点：
     * - 普通客户：直属推荐 IB（ib_partner_bind 中 isClient=1 的 parentId）
     * - 客户本身是 IB：它自己 IB 的上级 IB（getParentIbPartner 的 parentId）
     * 没有上级时返回 null。
     *
     * @param int $clientId clientUsers.id
     * @param int|null $ownIbPartnerId 该客户自己的主 IB id（无则传 null），由调用方提供避免重复查询
     */
    private function resolveClientUplineStartIbId(int $clientId, ?int $ownIbPartnerId): ?int {
        $directBind = $this->ibPartnerBindModel->getDirectIbForClient($clientId);
        if (!empty($directBind['ibPartnerId'])) {
            return (int)$directBind['ibPartnerId'];
        }
        if ($ownIbPartnerId) {
            $parent = $this->ibPartnerBindModel->getParentIbPartner($ownIbPartnerId);
            if (!empty($parent['parentIbPartnerId'])) {
                return (int)$parent['parentIbPartnerId'];
            }
        }
        return null;
    }

    private function formatIbAllListItem(array $row): array {
        $row['statusDisplay'] = IbPartner::statusToDisplay($row['status'] ?? '');

        if (!empty($row['applicationDate'])) {
            $timezone = new \DateTimeZone($this->appConfig['timezone'] ?? 'UTC');
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $row['applicationDate'], $timezone);
            if ($dt) {
                $row['applicationDate'] = $dt->format(\DateTime::ATOM);
            }
        }

        $id = (int)($row['id'] ?? 0);
        $refRow = $this->ibReferralSettingsModel->getOrCreateByIbPartnerId($id);
        $suffix = trim((string)($refRow['referralSuffix'] ?? ''));
        $baseUrl = rtrim($this->appConfig['client_frontend_url'] ?? 'http://localhost:9502', '/');
        $row['ibReferralUrl'] = $baseUrl . '/#/registration/i/' . rawurlencode($suffix);
        $row['referralSuffix'] = $suffix;

        return $row;
    }

    private function getIbCommissionStatistics(int $ibPartnerId): array {
        $controller = new ClientCommissionReportController();
        return $controller->getStatisticsPayloadForIb($ibPartnerId, true);
    }

    /**
     * 获取已验证客户列表（分页）
     * GET /api/admin-clients
     * 与 ClientUser::getClientListForAdmin 保持一致，与 /clients-list 页面数据源统一
     */
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 25;
        $sortField = $_GET['sort_field'] ?? 'createdAt';
        $sortDirection = $_GET['sort_direction'] ?? 'desc';
        $kycStatus = $_GET['kyc_status'] ?? null;
        $search = $_GET['search'] ?? null;

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::paginated([], 0, (int)$page, (int)$perPage);
            return;
        }
        $options = [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'kyc_status' => $kycStatus,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
            'excludeIbAccepted' => false
        ];
        if ($scope['scope'] === 'own') {
            $options['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $result = $this->userModel->getClientListForAdmin($options);

        $clients = $result['items'];
        $total = $result['total'];

        foreach ($clients as &$client) {
            $client = $this->transformClientRecord($client);
            $client['tags'] = $this->getClientTags($client['id']);
            $client['isOnline'] = $this->isClientOnline($client['id']);
        }

        Response::paginated($clients, $total, $page, $perPage);
    }

    /**
     * 搜索客户
     * 支持按姓名、邮箱、电话、国家、标签搜索
     */
    private function searchClients($keyword, $page = 1, $perPage = 25, $kycStatus = null, $sortField = 'createdAt', $sortDirection = 'desc') {
        $offset = ($page - 1) * $perPage;
        $searchPattern = "%{$keyword}%";

        // 构建搜索SQL
        $sql = "SELECT DISTINCT
                    cu.*,
                    cu.accountManagerAssignedAt,
                    au.fullName AS managerName,
                    au.email AS managerEmail,
                    (SELECT COUNT(*) FROM leadTagAssignments WHERE leadId = cu.id) as tagCount,
                    (SELECT COUNT(*) FROM clientKycSubmissions WHERE clientId = cu.id) as kycSubmissionsCount,
                    (SELECT submissionStatus FROM clientKycSubmissions WHERE clientId = cu.id ORDER BY createdAt DESC LIMIT 1) as latestKycStatus,
                    (SELECT templateName FROM kycTemplates kt
                     INNER JOIN clientKycSubmissions cks ON kt.id = cks.templateId
                     WHERE cks.clientId = cu.id ORDER BY cks.createdAt DESC LIMIT 1) as kycTemplateName,
                    (SELECT verifiedAt FROM clientKycSubmissions WHERE clientId = cu.id AND submissionStatus = 'approved' ORDER BY updatedAt DESC LIMIT 1) as kycVerifiedAt,
                    (SELECT COUNT(*) FROM clientActivityLog WHERE userId = cu.id AND activityType = 'login') as loginCount
                FROM clientUsers cu
                LEFT JOIN adminUsers au ON au.id = cu.accountManagerId
                LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                LEFT JOIN leadTags lt ON lta.tagId = lt.id
                WHERE 1=1 -- 暂时注释掉 emailVerified = 1 过滤条件
                  AND (cu.firstName LIKE ?
                   OR cu.lastName LIKE ?
                   OR cu.email LIKE ?
                   OR cu.phone LIKE ?
                   OR cu.country LIKE ?
                   OR lt.tagName LIKE ?)";

        $params = [
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern
        ];

        // 添加KYC状态筛选
        if ($kycStatus) {
            $sql .= " AND cu.kycStatus = ?";
            $params[] = $kycStatus;
        }

        // 排序
        $allowedSortFields = ['id', 'firstName', 'lastName', 'email', 'createdAt', 'lastLoginAt', 'kycStatus'];
        if (in_array($sortField, $allowedSortFields)) {
            $sql .= " ORDER BY cu.{$sortField} " . ($sortDirection === 'desc' ? 'DESC' : 'ASC');
        } else {
            $sql .= " ORDER BY cu.createdAt DESC";
        }

        // 分页
        $sql .= " LIMIT {$offset}, {$perPage}";

        $clients = $this->userModel->query($sql, $params);

        // 获取搜索结果总数
        $countSql = "SELECT COUNT(DISTINCT cu.id) as total
                     FROM clientUsers cu
                     LEFT JOIN leadTagAssignments lta ON cu.id = lta.leadId
                     LEFT JOIN leadTags lt ON lta.tagId = lt.id
                     WHERE 1=1 -- 暂时注释掉 emailVerified = 1 过滤条件
                       AND (cu.firstName LIKE ?
                        OR cu.lastName LIKE ?
                        OR cu.email LIKE ?
                        OR cu.phone LIKE ?
                        OR cu.country LIKE ?
                        OR lt.tagName LIKE ?)";

        $countParams = [
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $searchPattern
        ];

        if ($kycStatus) {
            $countSql .= " AND cu.kycStatus = ?";
            $countParams[] = $kycStatus;
        }

        $totalResult = $this->userModel->queryOne($countSql, $countParams);
        $total = $totalResult['total'];

        // 为每个客户获取标签
        foreach ($clients as &$client) {
            $client = $this->transformClientRecord($client);
            $client['tags'] = $this->getClientTags($client['id']);
            $client['isOnline'] = $this->isClientOnline($client['id']);
        }

        return [
            'items' => $clients,
            'total' => $total
        ];
    }

    /**
     * 获取客户统计信息
     * GET /api/admin-clients/statistics
     * 注意：仅统计KYC状态为approved的客户（正式客户）
     */
    public function statistics() {
        // 总客户数：只统计KYC已通过的客户
        $totalClientsResult = $this->userModel->query(
            "SELECT COUNT(*) as count
             FROM clientUsers
             WHERE kycStatus = 'approved'"
        );
        $totalClients = $totalClientsResult[0]['count'] ?? 0;

        // 活跃客户：KYC已通过且用户状态为active
        $activeClientsResult = $this->userModel->query(
            "SELECT COUNT(*) as count
             FROM clientUsers
             WHERE status = 'active'
               AND kycStatus = 'approved'"
        );
        $activeClients = $activeClientsResult[0]['count'] ?? 0;

        // 非活跃客户：KYC已通过但用户状态不是active
        $inactiveClientsResult = $this->userModel->query(
            "SELECT COUNT(*) as count
             FROM clientUsers
             WHERE status != 'active'
               AND kycStatus = 'approved'"
        );
        $inactiveClients = $inactiveClientsResult[0]['count'] ?? 0;

        Response::success([
            'totalClients' => (int)$totalClients,
            'activeClients' => (int)$activeClients,
            'inactiveClients' => (int)$inactiveClients
        ]);
    }

    /**
     * 获取可分配的 Sales 列表（「销售」= roleId=Sales 或 拥有 Sales Dashboard View，供分配 Sales Representative 下拉使用）
     * GET /api/admin-clients/assignable-sales
     */
    public function getAssignableSales() {
        $config = require __DIR__ . '/../config/app.php';
        $salesRoleId = (int)($config['special_roles']['sales_role_id'] ?? 0);
        $permRow = (new AdminPermission())->findByKey('page_salesdashboard_view');
        $salesDashboardViewPermissionId = $permRow ? (int)($permRow['id'] ?? 0) : 0;
        if ($salesRoleId <= 0) {
            Response::success(['managers' => []], 'Options loaded');
            return;
        }
        $rows = $this->adminUserModel->getSalesListUserIdsForAssign($salesRoleId, $salesDashboardViewPermissionId);
        $managers = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => $row['fullName'] ?: $row['email'],
                'email' => $row['email']
            ];
        }, $rows);
        Response::success(['managers' => $managers], 'Options loaded');
    }

    /**
     * 获取开户所需下拉选项
     * GET /api/admin-clients/options
     */
    public function getCreateOptions($includeAll = true) {
        // 国家列表
        $countries = $this->countryListModel->getActiveCountries($includeAll);

        // 货币列表（静态配置）
        $currencies = $this->getCurrencyOptions();

        // 可分配的管理员（活跃状态且未删除）
        $managerRows = $this->adminUserModel->query(
            "SELECT id, fullName, email
             FROM adminUsers
             WHERE status = 'active'
               AND deletedAt IS NULL
             ORDER BY fullName"
        );
        $managers = array_map(function ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => $row['fullName'] ?: $row['email'],
                'email' => $row['email']
            ];
        }, $managerRows);

        // 可用的KYC模板
        $templates = array_map(function ($template) {
            return [
                'id' => (int)$template['templateId'],
                'name' => $template['templateName'],
                'description' => $template['description'] ?? '',
                'countries' => $template['countries'] ?? '',
                'countryCodes' => $template['countryCodes'] ?? ''
            ];
        }, $this->templateModel->getActiveTemplates());

        Response::success([
            'countries' => $countries,
            'currencies' => $currencies,
            'managers' => $managers,
            'templates' => $templates
        ], 'Options loaded successfully');
    }

    /**
     * 创建新客户
     * POST /api/admin-clients
     */
    public function create() {
        $rawInput = json_decode(file_get_contents('php://input'), true) ?? [];
        $input = array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $rawInput);

        // 当前管理员信息
        try {
            $token = JWT::getTokenFromHeader();
            if (!$token) {
                Response::unauthorized('Authentication required');
            }
            $payload = JWT::decode($token);
            $adminId = $payload['userId'] ?? null;
        } catch (Exception $e) {
            Response::unauthorized('Invalid or expired token');
        }

        // 数据校验
        $createValidator = new Validator($input, [
            'firstName' => 'required|min:2',
            'lastName' => 'required|min:1',
            'email' => 'required|email|unique:clientUsers,email',
            'country' => 'required',
            'kycTemplateId' => 'required|numeric',
        ]);
        if (!$createValidator->validate()) {
            $this->logClientCreateFailure(
                $input,
                OperationLogTextHelpers::validationErrorsToMessage($createValidator->getErrors())
            );
            Response::validationError($createValidator->getErrors());
        }

        $generatePassword = !empty($input['generatePassword']);
        if (!$generatePassword && empty($input['password'])) {
            $this->logClientCreateFailure($input, 'Password is required when auto-generation is disabled');
            Response::validationError([
                'password' => ['Password is required when auto-generation is disabled']
            ]);
        }

        // 读取系统密码策略
        $appConfig = require __DIR__ . '/../config/app.php';
        $minPasswordLength = $appConfig['security']['password_min_length'] ?? 6;

        if (!$generatePassword && strlen($input['password']) < $minPasswordLength) {
            $this->logClientCreateFailure(
                $input,
                "Password must be at least {$minPasswordLength} characters"
            );
            Response::validationError([
                'password' => ["Password must be at least {$minPasswordLength} characters"]
            ]);
        }

        // 开关开启且填写了手机号时，校验手机号是否已被其他客户使用
        if (!empty($appConfig['security']['check_duplicate_phone'])
            && !empty($input['phone'])
            && $this->userModel->findByPhone($input['phone'])) {
            $this->logClientCreateFailure($input, 'Phone number already exists');
            Response::validationError([
                'phone' => ['Phone number already exists']
            ]);
        }

        // 校验模板
        $templateId = (int)$input['kycTemplateId'];
        $template = $this->templateModel->findById($templateId);
        if (!$template || ($template['status'] ?? '') !== 'active') {
            $this->logClientCreateFailure($input, 'Selected KYC template is not available');
            Response::validationError([
                'kycTemplateId' => ['Selected KYC template is not available']
            ]);
        }

        $accountManagerId = null;
        $assignedManager = null;
        $managerInput = $input['accountManagerId'] ?? $input['assignedTo'] ?? null;
        if ($managerInput !== null && $managerInput !== '') {
            $manager = $this->adminUserModel->findById((int)$managerInput);
            if (!$manager || ($manager['status'] ?? '') !== 'active' || !empty($manager['deletedAt'])) {
                $this->logClientCreateFailure($input, 'Selected account manager is not available');
                Response::validationError([
                    'accountManagerId' => ['Selected account manager is not available']
                ]);
            }
            $accountManagerId = (int)$manager['id'];
            $assignedManager = $manager;
        }

        $countryCode = strtoupper($input['country']);
        if (!preg_match('/^[A-Z]{2,3}$/', $countryCode)) {
            $this->logClientCreateFailure($input, 'Country must be a valid ISO country code (e.g., US, UK)');
            Response::validationError([
                'country' => ['Country must be a valid ISO country code (e.g., US, UK)']
            ]);
        }
        $depositCurrency = strtoupper($input['depositCurrency'] ?? 'USD');
        $currencyCodes = array_column($this->getCurrencyOptions(), 'code');
        if (!in_array($depositCurrency, $currencyCodes, true)) {
            $depositCurrency = 'USD';
        }

        $plainPassword = $generatePassword
            ? $this->generateSecurePassword()
            : $input['password'];
        $passwordHash = $this->userModel->hashPassword($plainPassword);
        $now = date('Y-m-d H:i:s');

        $notesPayload = [
            'depositCurrency' => $depositCurrency,
            'kycTemplateId' => $templateId,
            'autoGeneratedPassword' => $generatePassword
        ];
        if ($accountManagerId) {
            $notesPayload['accountManagerId'] = $accountManagerId;
        }

        $db = Database::getInstance();
        $connection = $db->getConnection();

        try {
            $db->beginTransaction();

            // 创建用户
            $userId = $this->userModel->create([
                'email' => $input['email'],
                'passwordHash' => $passwordHash,
                'firstName' => $input['firstName'],
                'lastName' => $input['lastName'],
                'phone' => $input['phone'] ?? null,
                'country' => $countryCode,
                'emailVerified' => 1,
                'emailVerifiedAt' => $now,
                'status' => 'active',
                'accountManagerId' => $accountManagerId,
                'accountManagerAssignedAt' => $accountManagerId ? $now : null,
                'notes' => json_encode($notesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

            if (!$userId) {
                throw new Exception('Failed to create client user');
            }

            // 若指定了 Account Manager（Sales），写入 sales_bind 绑定关系（仅 Sales-Client）
            if ($accountManagerId) {
                $db->query(
                    "INSERT IGNORE INTO sales_bind (salesId, clientId, createdAt) VALUES (?, ?, ?)",
                    [$accountManagerId, $userId, $now]
                );
            }

            // 创建KYC记录
            $submissionData = [
                'clientId' => $userId,
                'templateId' => $templateId,
                'submissionStatus' => 'draft',
                'submittedAt' => null,
                'reviewedAt' => null,
                'reviewedBy' => null,
                'approvalNotes' => null,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            $kycSubmissionId = $this->kycSubmissionModel->create($submissionData);

            if (!$kycSubmissionId) {
                throw new Exception('Failed to create KYC submission record');
            }

            // 记录活动日志
            $this->activityLogModel->logActivity([
                'userId' => $userId,
                'activityType' => 'register',
                'description' => 'Client account created by administrator',
                'metadata' => [
                    'adminUserId' => $adminId,
                    'kycTemplateId' => $templateId,
                    'accountManagerId' => $accountManagerId,
                    'assignedManagerName' => $assignedManager['fullName'] ?? null,
                    'assignedManagerEmail' => $assignedManager['email'] ?? null
                ]
            ]);

            $this->createLegalDocumentSignatures($userId, [
                'firstName' => $input['firstName'] ?? '',
                'lastName' => $input['lastName'] ?? '',
                'email' => $input['email'] ?? ''
            ]);

            $db->commit();
        } catch (Exception $e) {
            if ($connection->inTransaction()) {
                $db->rollback();
            }
//            Logger::error('Admin create client failed', [
//                'error' => $e->getMessage(),
//                'input' => $input
//            ]);
            $this->logClientCreateFailure($input, 'Failed to create client: ' . $e->getMessage());
            Response::error('Failed to create client: ' . $e->getMessage(), 500);
        }

        // 发送欢迎邮件（事务提交后执行）
        try {
            $emailSender = new EmailSender();
            $fullName = trim($input['firstName'] . ' ' . $input['lastName']);
            $clientFrontendUrl = $appConfig['client_frontend_url'] ?? 'http://localhost:5173';
            $loginUrl = rtrim($clientFrontendUrl, '/') . '/client/login';

            $emailSubject = 'Welcome to Client Portal';
            $emailBody = '<p>Dear ' . htmlspecialchars($fullName ?: $input['email']) . ',</p>';
            $emailBody .= '<p>Your client account has been created successfully. Below are your login details:</p>';
            $emailBody .= '<ul>';
            $emailBody .= '<li><strong>Portal URL:</strong> <a href="' . htmlspecialchars($loginUrl) . '">' . htmlspecialchars($loginUrl) . '</a></li>';
            $emailBody .= '<li><strong>Email:</strong> ' . htmlspecialchars($input['email']) . '</li>';
            if ($generatePassword) {
                $emailBody .= '<li><strong>Temporary Password:</strong> ' . htmlspecialchars($plainPassword) . '</li>';
                $emailBody .= '</ul>';
                $emailBody .= '<p>Please sign in using the details above and change your password immediately after logging in.</p>';
            } else {
                $emailBody .= '</ul>';
            }
            $emailBody .= '<p>Please log in and complete your KYC verification using the <strong>' . htmlspecialchars($template['templateName']) . '</strong> template. This is required before you can open a trading account.</p>';
            $emailBody .= '<p>Best regards,<br>Client Portal Team</p>';

            $emailSender->send($input['email'], $emailSubject, $emailBody);
        } catch (Exception $emailException) {
//            Logger::error('Failed to send client welcome email', [
//                'userId' => $userId ?? null,
//                'error' => $emailException->getMessage()
//            ]);
        }

        $createdClient = $this->userModel->findById($userId);
        $createdClient = $this->transformClientRecord($createdClient);
        $createdClient['kycSubmissionId'] = (int)$kycSubmissionId;
        $createdClient['kycTemplateId'] = $templateId;

        $opLog = new AdminOperationLogWriter();
        $subModule = OperationLogPages::resolveLogClient($input, OperationLogPages::subModuleKeyByAlias('page_leads'));
        $opLog->logClientCreated($subModule, $createdClient, $userId);

        Response::success([
            'client' => $createdClient,
            'generatedPassword' => $generatePassword ? $plainPassword : null
        ], 'Client created successfully', 201);
    }

    /**
     * 获取客户详情
     * GET /api/admin-clients/{id}
     */
    public function show($id) {
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::forbidden('You do not have permission to view this client');
        }
        $options = [];
        if ($scope['scope'] === 'own') {
            $options['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $client = $this->userModel->getAdminClientDetailById((int)$id, $options);

        if (!$client) {
            Response::notFound('Client not found');
        }

        $client = $this->transformClientRecord($client);
        $client['tags'] = $this->getClientTags($id);
        $client['isOnline'] = $this->isClientOnline($id);

        Response::success($client);
    }

    /**
     * 获取客户详情页基础信息
     * GET /api/admin-clients/detail&id={id}
     */
    public function detail() {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            Response::error('Client id is required', 400);
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::forbidden('You do not have permission to view this client');
        }
        $options = [];
        if ($scope['scope'] === 'own') {
            $options['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $client = $this->userModel->getAdminClientDetailById($id, $options);
        if (!$client) {
            $this->respondAdminClientDetailMissing($id, $options['restrict_to_sales_id'] ?? null);
        }

        $client = $this->transformClientRecord($client);
        $client['tags'] = $this->getClientTags($id);
        $client['isOnline'] = $this->isClientOnline($id);
        $client = array_merge($client, $this->getClientIbInfo($id));
        $client['overview'] = $this->buildClientOverview(
            $id,
            $client['depositCurrency'] ?? null
        );

        Response::success($client);
    }

    /**
     * 获取客户详情页 Sales Assignment 信息
     * GET /api/admin-clients/{id}/sales
     */
    public function getClientSalesAssignment($id) {
        $clientId = (int)$id;
        if ($clientId <= 0) {
            Response::error('Client id is required', 400);
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::forbidden('You do not have permission to view this client');
        }
        $options = [];
        if ($scope['scope'] === 'own') {
            $options['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $client = $this->userModel->getAdminClientDetailById($clientId, $options);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $client = $this->transformClientRecord($client);
        Response::success([
            'id' => (int)$client['id'],
            'accountManagerId' => $client['accountManagerId'] ?? null,
            'accountManagerNote' => $client['accountManagerNote'] ?? null,
            'managerNote' => $client['managerNote'] ?? null,
            'accountManagerAssignedAt' => $client['accountManagerAssignedAt'] ?? null,
            'managerName' => $client['managerName'] ?? null,
            'managerEmail' => $client['managerEmail'] ?? null,
            'manager' => $client['manager'] ?? null
        ]);
    }

    /**
     * 获取 IB List(all-list) 中某个 IB 的单条信息
     * GET /api/admin-clients/ib?id={ibPartnerId}
     */
    public function getIbAllListDetail() {
        $ibPartnerId = (int)($_GET['id'] ?? 0);
        if ($ibPartnerId <= 0) {
            Response::validationError(['id' => 'id is required']);
        }

        $item = $this->ibPartnerModel->getAllStatusListItemById($ibPartnerId);
        if (!$item) {
            Response::notFound('IB partner not found in all-list');
        }

        $item = $this->formatIbAllListItem($item);
        $item['statistics'] = $this->getIbCommissionStatistics($ibPartnerId);

        Response::success($item);
    }

    /**
     * 后台 client detail - IB tab：该 IB「整个下级网络」下的客户列表（分页）
     * GET /api/admin-clients/ib-network-clients?ibPartnerId=&page=&per_page=
     */
    public function getIbNetworkClients() {
        $ibPartnerId = (int)($_GET['ibPartnerId'] ?? 0);
        if ($ibPartnerId <= 0) {
            Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
        }
        if (!$this->ibPartnerModel->findById($ibPartnerId)) {
            Response::notFound('IB partner not found');
        }

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $result = $this->ibPartnerModel->getNetworkClients($ibPartnerId, $page, $perPage);

        // 逐行补充钱包可用余额（与 client detail 的 wallet 口径一致）。
        // 仅对当前页（<=100 行）计算，避免全量 N+1。
        require_once __DIR__ . '/../services/WalletBalanceService.php';
        $walletService = new WalletBalanceService();
        foreach ($result['items'] as &$item) {
            $clientId = (int)($item['clientId'] ?? 0);
            $item['walletBalance'] = $clientId > 0 ? $walletService->getAvailableBalance($clientId) : 0;
        }
        unset($item);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    public function exportIbNetworkClients() {
        try {
            require_once __DIR__ . '/../services/AdminIbNetworkClientsExportService.php';

            $ibPartnerId = (int)($_GET['ibPartnerId'] ?? 0);
            $rawBody = json_decode(file_get_contents('php://input'), true);
            if ($ibPartnerId <= 0 && is_array($rawBody)) {
                $ibPartnerId = (int)($rawBody['ibPartnerId'] ?? 0);
            }
            if ($ibPartnerId <= 0) {
                Response::validationError(['ibPartnerId' => 'ibPartnerId is required']);
            }
            if (!$this->ibPartnerModel->findById($ibPartnerId)) {
                Response::notFound('IB partner not found');
            }

            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }

            $active = AdminIbNetworkClientsExportService::getActiveForAdmin($adminUserId);
            $activeStatus = (string)($active['status'] ?? '');
            if (in_array($activeStatus, ['queued', 'running', 'cancelling'], true)) {
                Response::error('Export already in progress', 409, $this->ibNetworkClientsExportProgressPayload($active));
            }
            if ($activeStatus === 'done') {
                AdminIbNetworkClientsExportService::clearActive($adminUserId);
            }

            $jobId = str_replace('.', '', uniqid('aibnc_', true));
            $fileName = 'network_clients_' . $ibPartnerId . '_' . date('Y-m-d') . '.csv';
            AdminIbNetworkClientsExportService::ensureExportDir();
            AdminIbNetworkClientsExportService::writeProgress($jobId, [
                'adminUserId' => $adminUserId,
                'status' => 'queued',
                'cancelRequested' => false,
                'percent' => 0,
                'processed' => 0,
                'total' => 0,
                'message' => 'Queued',
                'file' => $jobId . '.csv',
                'fileName' => $fileName,
                'ibPartnerId' => $ibPartnerId,
            ]);
            AdminIbNetworkClientsExportService::writeActive($adminUserId, $jobId);

            $payload = [
                'type' => 'export_admin_ib_network_clients',
                'jobId' => $jobId,
                'adminUserId' => $adminUserId,
                'userId' => $adminUserId,
                'userType' => 'admin',
                'ibPartnerId' => $ibPartnerId,
                'requestedAt' => time(),
            ];

            try {
                $this->dispatchIbNetworkClientsSwooleTask($payload);
            } catch (Exception $e) {
                AdminIbNetworkClientsExportService::writeProgress($jobId, [
                    'adminUserId' => $adminUserId,
                    'status' => 'error',
                    'cancelRequested' => false,
                    'percent' => 0,
                    'message' => $e->getMessage(),
                    'file' => null,
                ]);
                AdminIbNetworkClientsExportService::clearActive($adminUserId);
                Response::error('Failed to queue export task: ' . $e->getMessage(), 500);
            }

            Response::success([
                'jobId' => $jobId,
                'queued' => true,
            ], 'Export task accepted');
        } catch (Exception $e) {
            Response::error('Failed to export: ' . $e->getMessage(), 500);
        }
    }

    public function exportIbNetworkClientsActive() {
        try {
            require_once __DIR__ . '/../services/AdminIbNetworkClientsExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $progress = AdminIbNetworkClientsExportService::getActiveForAdmin($adminUserId);
            Response::success($this->ibNetworkClientsExportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportIbNetworkClientsStatus() {
        try {
            require_once __DIR__ . '/../services/AdminIbNetworkClientsExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeIbNetworkClientsJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbNetworkClientsExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            require_once __DIR__ . '/../services/ExportJobTimeoutReaper.php';
            $progress = ExportJobTimeoutReaper::reapIfStale(
                $jobId,
                $progress,
                [AdminIbNetworkClientsExportService::class, 'writeProgress'],
                [AdminIbNetworkClientsExportService::class, 'clearActive']
            );
            Response::success($this->ibNetworkClientsExportProgressPayload($progress));
        } catch (Exception $e) {
            Response::error('Failed to fetch export status: ' . $e->getMessage(), 500);
        }
    }

    public function exportIbNetworkClientsCancel() {
        try {
            require_once __DIR__ . '/../services/AdminIbNetworkClientsExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $rawBody = json_decode(file_get_contents('php://input'), true);
            $jobId = '';
            if (is_array($rawBody) && !empty($rawBody['jobId'])) {
                $jobId = trim((string)$rawBody['jobId']);
            } elseif (!empty($_GET['jobId'])) {
                $jobId = trim((string)$_GET['jobId']);
            }
            if ($jobId === '' || !$this->isSafeIbNetworkClientsJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbNetworkClientsExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            $status = (string)($progress['status'] ?? '');
            if ($status === 'cancelled') {
                Response::success($this->ibNetworkClientsExportProgressPayload($progress), 'Already cancelled');
            }
            if ($status === 'error') {
                AdminIbNetworkClientsExportService::clearActive($adminUserId);
                Response::success($this->ibNetworkClientsExportProgressPayload($progress), 'Export already failed');
            }
            AdminIbNetworkClientsExportService::requestCancel($jobId);
            $updated = AdminIbNetworkClientsExportService::readProgress($jobId);
            Response::success($this->ibNetworkClientsExportProgressPayload($updated), 'Cancel requested');
        } catch (Exception $e) {
            Response::error('Failed to cancel export: ' . $e->getMessage(), 500);
        }
    }

    public function exportIbNetworkClientsDownload() {
        try {
            require_once __DIR__ . '/../services/AdminIbNetworkClientsExportService.php';
            $currentUser = AuthMiddleware::getCurrentUser();
            $adminUserId = (int)($currentUser['userId'] ?? 0);
            if ($adminUserId <= 0) {
                Response::error('Unauthorized', 401);
            }
            $jobId = isset($_GET['jobId']) ? trim((string)$_GET['jobId']) : '';
            if ($jobId === '' || !$this->isSafeIbNetworkClientsJobId($jobId)) {
                Response::validationError(['jobId' => 'jobId is required']);
            }
            $progress = AdminIbNetworkClientsExportService::readProgress($jobId);
            if ($progress === null || (int)($progress['adminUserId'] ?? 0) !== $adminUserId) {
                Response::notFound('Export job not found');
            }
            if (($progress['status'] ?? '') !== 'done') {
                Response::error('Export is not ready', 400);
            }
            $csvFile = AdminIbNetworkClientsExportService::csvPath($jobId);
            if (!file_exists($csvFile) || !is_readable($csvFile)) {
                Response::error('Export file missing', 404);
            }

            AdminIbNetworkClientsExportService::clearActive($adminUserId);

            $filename = trim((string)($progress['fileName'] ?? ''));
            if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+\.csv$/', $filename)) {
                $filename = 'network_clients_' . date('Y-m-d') . '.csv';
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($csvFile));
            header('Cache-Control: no-store');
            readfile($csvFile);
            exit;
        } catch (Exception $e) {
            Response::error('Failed to download export: ' . $e->getMessage(), 500);
        }
    }

    private function isSafeIbNetworkClientsJobId($value) {
        $value = trim((string)$value);
        return $value !== '' && strlen($value) <= 80 && preg_match('/^[A-Za-z0-9._-]+$/', $value);
    }

    private function dispatchIbNetworkClientsSwooleTask(array $payload): void
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

        $written = @fwrite($socket, $json . '$$$###');
        fclose($socket);
        if ($written === false || $written <= 0) {
            throw new Exception('Failed to send task to myswoole');
        }
    }

    private function ibNetworkClientsExportProgressPayload(?array $progress): array
    {
        if ($progress === null) {
            return ['active' => false];
        }
        return [
            'active' => true,
            'jobId' => (string)($progress['jobId'] ?? ''),
            'status' => (string)($progress['status'] ?? ''),
            'percent' => (int)($progress['percent'] ?? 0),
            'message' => (string)($progress['message'] ?? ''),
            'processed' => (int)($progress['processed'] ?? 0),
            'total' => (int)($progress['total'] ?? 0),
            'downloadReady' => !empty($progress['downloadReady']) || (($progress['status'] ?? '') === 'done'),
            'fileName' => (string)($progress['fileName'] ?? ''),
        ];
    }

    /**
     * 获取客户所属 IB 的上级链路。
     * GET /api/admin-clients/{clientId}/ib-upline
     */
    public function getClientIbUpline($clientId) {
        $clientId = (int)$clientId;
        if ($clientId <= 0) {
            Response::error('Client id is required', 400);
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::forbidden('You do not have permission to view this client');
        }
        $options = [];
        if ($scope['scope'] === 'own') {
            $options['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $client = $this->userModel->getAdminClientDetailById($clientId, $options);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $ownIbPartners = $this->ibPartnerModel->getAllByClientId($clientId);
        $ownIbPartnerId = !empty($ownIbPartners[0]['id']) ? (int)$ownIbPartners[0]['id'] : null;
        $startIbId = $this->resolveClientUplineStartIbId($clientId, $ownIbPartnerId);
        if (!$startIbId) {
            Response::success([
                'clientId' => $clientId,
                'directIbPartnerId' => null,
                'upline' => []
            ]);
        }

        $ibIds = $this->ibPartnerBindModel->getUplineIbPartnerIds($startIbId);
        $upline = $this->getIbUplineRows($ibIds);

        Response::success([
            'clientId' => $clientId,
            'directIbPartnerId' => $startIbId,
            'upline' => $upline
        ]);
    }

    private function getIbUplineRows(array $ibIds): array {
        $ibIds = array_values(array_unique(array_filter(array_map('intval', $ibIds), function ($id) {
            return $id > 0;
        })));
        if (empty($ibIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ibIds), '?'));
        $rows = $this->ibPartnerModel->query(
            "SELECT
                ib.id,
                ib.ibCode,
                ib.adminAlias,
                ib.companyName,
                ib.contactEmail,
                ib.status,
                ib.userId,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(cu.firstName,''), ' ', COALESCE(cu.lastName,''))), ''), ib.companyName, cu.email, ib.contactEmail) AS ibName,
                COALESCE(cu.email, ib.contactEmail) AS email,
                tl.tierLevel,
                tl.tierName AS tierLevelName
             FROM ibPartners ib
             LEFT JOIN clientUsers cu ON cu.id = ib.userId
             LEFT JOIN ibTierLevels tl ON tl.id = ib.tierLevelId
             WHERE ib.id IN ({$placeholders})",
            $ibIds
        );

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = [
                'id' => (int)$row['id'],
                // clientUsers.id — lets the frontend link an upline IB node to its client detail page
                'userId' => (int)($row['userId'] ?? 0),
                'ibCode' => $row['ibCode'] ?? '',
                // Admin alias shown on network nodes so multi-link IB campaigns are identifiable
                'adminAlias' => $row['adminAlias'] ?? '',
                'ibName' => $row['ibName'] ?? '',
                'companyName' => $row['companyName'] ?? '',
                'email' => $row['email'] ?? '',
                'tierLevel' => isset($row['tierLevel']) ? (int)$row['tierLevel'] : null,
                'tierLevelName' => $row['tierLevelName'] ?? '',
                'status' => $row['status'] ?? ''
            ];
        }

        $ordered = [];
        foreach ($ibIds as $index => $id) {
            if (isset($byId[$id])) {
                $ordered[] = array_merge($byId[$id], [
                    'depthFromClient' => $index + 1
                ]);
            }
        }

        return $ordered;
    }

    /**
     * 获取指定客户的资金记录列表，三类交易统一成详情页需要的轻量字段。
     * GET /api/admin-clients/{id}/transactions?type=all|deposit|withdrawal|internal_transfer|credit&page=1&limit=10
     */
    public function getTransactions($clientId) {
        $clientId = (int)$clientId;
        if ($clientId <= 0) {
            Response::error('Client id is required', 400);
        }

        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::forbidden('You do not have permission to view this client');
        }
        $clientOptions = [];
        if ($scope['scope'] === 'own') {
            $clientOptions['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $client = $this->userModel->getAdminClientDetailById($clientId, $clientOptions);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $type = strtolower(trim((string)($_GET['type'] ?? 'all')));
        $typeAliases = [
            'deposits' => 'deposit',
            'withdrawals' => 'withdrawal',
            'internal-transfer' => 'internal_transfer',
            'internal-transfers' => 'internal_transfer',
            'internaltransfer' => 'internal_transfer',
            'internaltransfers' => 'internal_transfer'
        ];
        $type = $typeAliases[$type] ?? $type;
        $allowedTypes = ['all', 'deposit', 'withdrawal', 'internal_transfer', 'credit'];
        if (!in_array($type, $allowedTypes, true)) {
            Response::validationError([
                'type' => 'type must be one of: all, deposit, withdrawal, internal_transfer, credit'
            ]);
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit'])
            ? (int)$_GET['limit']
            : (isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10);
        $limit = min(max(1, $limit), 200);
        $offset = ($page - 1) * $limit;

        $queries = [];
        $params = [];

        if ($type === 'all' || $type === 'deposit') {
            $queries[] = "SELECT
                    d.id,
                    d.transactionId,
                    'deposit' AS transactionType,
                    d.status,
                    d.amount,
                    COALESCE(d.currencyCode, 'USD') AS currency,
                    pgs.gatewayName AS gatewayName,
                    NULL AS fromLabel,
                    CASE
                        WHEN d.tradingAccountId IS NULL THEN 'Wallet'
                        WHEN tp.platformKey = 'mt5' AND tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                        ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                    END AS toLabel,
                    NULL AS fromPlatformName,
                    CASE WHEN d.tradingAccountId IS NULL THEN NULL ELSE COALESCE(tp.displayName, tp.platformKey) END AS toPlatformName,
                    d.requestedAt AS transactionDate
                FROM deposits d
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = d.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON d.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                WHERE d.userId = :depositClientId
                GROUP BY d.id";
            $params['depositClientId'] = $clientId;
        }

        if ($type === 'all' || $type === 'withdrawal') {
            $queries[] = "SELECT
                    w.id,
                    w.transactionId,
                    'withdrawal' AS transactionType,
                    w.status,
                    w.amount,
                    COALESCE(w.currencyCode, 'USD') AS currency,
                    pgs.gatewayName AS gatewayName,
                    CASE
                        WHEN w.tradingAccountId IS NULL THEN 'Wallet'
                        WHEN tp.platformKey = 'mt5' AND tea.providerAccountId IS NOT NULL AND tea.providerAccountId <> '' THEN tea.providerAccountId
                        ELSE COALESCE(ta.accountNumber, tea.providerAccountId, tea.name)
                    END AS fromLabel,
                    NULL AS toLabel,
                    CASE WHEN w.tradingAccountId IS NULL THEN NULL ELSE COALESCE(tp.displayName, tp.platformKey) END AS fromPlatformName,
                    NULL AS toPlatformName,
                    w.requestedAt AS transactionDate
                FROM withdrawals w
                LEFT JOIN paymentGatewaySettings pgs ON pgs.id = w.gatewaySettingId
                LEFT JOIN tradingAccounts ta ON w.tradingAccountId = ta.id
                LEFT JOIN tradingPlatforms tp ON ta.platformId = tp.id
                LEFT JOIN tradingAccountExternalAccounts tea ON tea.tradingAccountId = ta.id
                WHERE w.userId = :withdrawalClientId
                GROUP BY w.id";
            $params['withdrawalClientId'] = $clientId;
        }

        if ($type === 'all' || $type === 'credit') {
            // trading_credit_deals 是同步任务从平台拉来的赠金/信用流水，按 trading_account.userId 限定到当前 client
            // amount 始终为正数，方向由 direction 决定；这里参考 deposit/withdrawal 的展示方式，
            // direction=2 时把金额转负，便于 admin 一眼看出资金方向（IN=正，OUT=负）。
            $queries[] = "SELECT
                    tcd.id,
                    CONCAT('CR-', tcd.id) AS transactionId,
                    'credit' AS transactionType,
                    'completed' AS status,
                    CASE WHEN tcd.direction = 2 THEN -tcd.amount ELSE tcd.amount END AS amount,
                    COALESCE(ta_c.accountCurrency, 'USD') AS currency,
                    NULL AS gatewayName,
                    NULL AS fromLabel,
                    CASE
                        WHEN tp_c.platformKey = 'mt5' AND tea_c.providerAccountId IS NOT NULL AND tea_c.providerAccountId <> '' THEN tea_c.providerAccountId
                        ELSE COALESCE(ta_c.accountNumber, tea_c.providerAccountId, tea_c.name)
                    END AS toLabel,
                    NULL AS fromPlatformName,
                    COALESCE(tp_c.displayName, tp_c.platformKey) AS toPlatformName,
                    tcd.deal_time AS transactionDate
                FROM trading_credit_deals tcd
                INNER JOIN tradingAccounts ta_c ON ta_c.id = tcd.trading_account_id
                LEFT JOIN tradingPlatforms tp_c ON ta_c.platformId = tp_c.id
                LEFT JOIN tradingAccountExternalAccounts tea_c ON tea_c.tradingAccountId = ta_c.id
                WHERE ta_c.userId = :creditClientId
                GROUP BY tcd.id";
            $params['creditClientId'] = $clientId;
        }

        if ($type === 'all' || $type === 'internal_transfer') {
            $queries[] = "SELECT
                    it.id,
                    it.transactionId,
                    'internal_transfer' AS transactionType,
                    it.status,
                    it.amount,
                    'USD' AS currency,
                    NULL AS gatewayName,
                    CASE
                        WHEN it.fromType = 'wallet' OR it.fromType = 'available_balance' THEN 'Wallet'
                        WHEN it.fromTradingAccountId IS NULL THEN 'Wallet'
                        WHEN tp_from.platformKey = 'mt5' AND tea_from.providerAccountId IS NOT NULL AND tea_from.providerAccountId <> '' THEN tea_from.providerAccountId
                        ELSE COALESCE(ta_from.accountNumber, tea_from.providerAccountId, tea_from.name)
                    END AS fromLabel,
                    CASE
                        WHEN it.toTradingAccountId IS NULL THEN 'Wallet'
                        WHEN tp_to.platformKey = 'mt5' AND tea_to.providerAccountId IS NOT NULL AND tea_to.providerAccountId <> '' THEN tea_to.providerAccountId
                        ELSE COALESCE(ta_to.accountNumber, tea_to.providerAccountId, tea_to.name)
                    END AS toLabel,
                    CASE WHEN it.fromTradingAccountId IS NULL THEN NULL ELSE COALESCE(tp_from.displayName, tp_from.platformKey) END AS fromPlatformName,
                    CASE WHEN it.toTradingAccountId IS NULL THEN NULL ELSE COALESCE(tp_to.displayName, tp_to.platformKey) END AS toPlatformName,
                    it.requestedAt AS transactionDate
                FROM internalTransfers it
                LEFT JOIN tradingAccounts ta_from ON it.fromTradingAccountId = ta_from.id
                LEFT JOIN tradingPlatforms tp_from ON ta_from.platformId = tp_from.id
                LEFT JOIN tradingAccountExternalAccounts tea_from ON tea_from.tradingAccountId = ta_from.id
                LEFT JOIN tradingAccounts ta_to ON it.toTradingAccountId = ta_to.id
                LEFT JOIN tradingPlatforms tp_to ON ta_to.platformId = tp_to.id
                LEFT JOIN tradingAccountExternalAccounts tea_to ON tea_to.tradingAccountId = ta_to.id
                WHERE it.userId = :internalTransferClientId
                GROUP BY it.id";
            $params['internalTransferClientId'] = $clientId;
        }

        $unionSql = implode(" UNION ALL ", $queries);
        $countRow = Database::getInstance()->fetchOne(
            "SELECT COUNT(*) AS total FROM ({$unionSql}) tx",
            $params
        );
        $total = (int)($countRow['total'] ?? 0);

        $rows = Database::getInstance()->fetchAll(
            "SELECT * FROM ({$unionSql}) tx
             ORDER BY tx.transactionDate DESC, tx.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );

        $buildAccountEndpoint = static function ($account, $platformName) {
            if ($account === null || $account === '') {
                return null;
            }

            return [
                'account' => $account,
                'platformName' => $platformName ?: null
            ];
        };

        $items = array_map(function ($row) use ($buildAccountEndpoint) {
            return [
                'id' => (int)$row['id'],
                'transactionId' => $row['transactionId'] ?? null,
                'type' => $row['transactionType'] ?? null,
                'status' => $row['status'] ?? null,
                'amount' => isset($row['amount']) ? (float)$row['amount'] : 0.0,
                'currency' => $row['currency'] ?? 'USD',
                'gatewayName' => $row['gatewayName'] ?? null,
                'from' => $buildAccountEndpoint($row['fromLabel'] ?? null, $row['fromPlatformName'] ?? null),
                'to' => $buildAccountEndpoint($row['toLabel'] ?? null, $row['toPlatformName'] ?? null),
                'date' => $row['transactionDate'] ?? null
            ];
        }, $rows);

        Response::success([
            'clientId' => $clientId,
            'type' => $type,
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / $limit),
                'has_more' => ($page * $limit) < $total
            ]
        ], 'Client transactions loaded');
    }

    /**
     * 更新客户信息
     * PUT /api/admin-clients/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $client = $this->userModel->findById($id);
        if (!$client) {
            $this->logClientProfileUpdateFailure($data, 0, 'Client not found');
            Response::notFound('Client not found');
        }

        // 验证数据
        $allowedFields = ['firstName', 'lastName', 'email', 'phone', 'country', 'status', 'emailVerified'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            $this->logClientProfileUpdateFailure($data, $id, 'No valid fields to update');
            Response::error('No valid fields to update', 400);
        }

        // 如果更新邮箱，检查是否已存在
        if (isset($updateData['email']) && $updateData['email'] !== $client['email']) {
            $existingUser = $this->userModel->findByEmail($updateData['email']);
            if ($existingUser && $existingUser['id'] != $id) {
                $this->logClientProfileUpdateFailure($data, $id, 'Email already exists');
                Response::error('Email already exists', 400);
            }
        }

        // 开关开启且更新了手机号时，校验是否已被其他客户使用（排除自己）
        $appConfig = require __DIR__ . '/../config/app.php';
        if (!empty($appConfig['security']['check_duplicate_phone'])
            && isset($updateData['phone']) && $updateData['phone'] !== ''
            && $this->userModel->findByPhone($updateData['phone'], $id)) {
            $this->logClientProfileUpdateFailure($data, $id, 'Phone number already exists');
            Response::error('Phone number already exists', 400);
        }

        // 计算本次真正变化的资料字段（增量同步用；admin 表单每次全量提交，必须跟旧值逐个比对）
        $mergedProfile = array_merge($client, $updateData);
        $nameChanged = (isset($updateData['firstName']) && (string)$updateData['firstName'] !== (string)($client['firstName'] ?? ''))
                    || (isset($updateData['lastName']) && (string)$updateData['lastName'] !== (string)($client['lastName'] ?? ''));
        $emailChanged = isset($updateData['email']) && (string)$updateData['email'] !== (string)($client['email'] ?? '');
        $phoneChanged = isset($updateData['phone']) && (string)$updateData['phone'] !== (string)($client['phone'] ?? '');
        $countryChanged = isset($updateData['country']) && (string)$updateData['country'] !== (string)($client['country'] ?? '');

        // 只含变化字段的增量集，交给 swoole 异步同步（只同步 MT4/MT5，FP 不同步资料）
        $syncFields = [];
        if ($nameChanged)    $syncFields['name'] = trim(($mergedProfile['firstName'] ?? '') . ' ' . ($mergedProfile['lastName'] ?? ''));
        if ($phoneChanged)   $syncFields['phone'] = $mergedProfile['phone'] ?? null;
        if ($countryChanged) $syncFields['country'] = $mergedProfile['country'] ?? null;
        if ($emailChanged)   $syncFields['email'] = $mergedProfile['email'] ?? null;

        $this->userModel->update($id, $updateData);

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $id,
            'activityType' => 'profile_updated_by_admin',
            'description' => 'Profile updated by administrator',
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'changes' => $updateData
            ])
        ]);

        $changes = [];
        foreach ($updateData as $field => $newVal) {
            $oldVal = $client[$field] ?? '';
            if ((string) $oldVal !== (string) $newVal) {
                $changes[$field] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        if (!empty($changes)) {
            $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_clients_list'));
            $opLog = new AdminOperationLogWriter();
            $opLog->logClientProfileUpdate(
                $subModule,
                $id,
                AdminOperationLogWriter::formatClientDisplayName(array_merge($client, $updateData)),
                $changes
            );
        }

        // 资料同步到 MT4/MT5：走 swoole 异步（best-effort）。$syncFields 已是"只含变化字段"的增量集。FP 不同步资料。
        if (!empty($syncFields)) {
            require_once __DIR__ . '/../services/TradingAccountProfileSyncService.php';
            TradingAccountProfileSyncService::dispatch($id, $syncFields);
        }

        Response::success(null, 'Client updated successfully');
    }

    /**
     * 删除客户
     * DELETE /api/admin-clients/{id}
     */
    public function delete($id) {
        $client = $this->userModel->findById($id);
        if (!$client) {
            Response::notFound('Client not found');
        }

        // 删除客户（级联删除相关数据）
        $this->userModel->delete($id);

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'activityType' => 'client_deleted_by_admin',
            'description' => 'Client deleted by administrator: ' . $client['email'],
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'deletedClient' => $client
            ])
        ]);

        Response::success(null, 'Client deleted successfully');
    }

    /**
     * 批量分配
     * POST /api/admin-clients/bulk-assign
     */
    public function bulkAssign() {
        $data = json_decode(file_get_contents('php://input'), true);
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_clients_list'));
        $opLog = new AdminOperationLogWriter();

        $assignValidator = new Validator($data, [
            'clientIds' => 'required|array',
            'assigneeId' => 'required|numeric'
        ]);
        if (!$assignValidator->validate()) {
            $failureMessage = OperationLogTextHelpers::validationErrorsToMessage($assignValidator->getErrors());
            $opLog->logClientAssignBulk($subModule, [], '', '', false, $failureMessage);
            Response::validationError($assignValidator->getErrors());
        }

        $clientIds = $data['clientIds'] ?? [];
        $clientIds = array_unique(array_map('intval', $clientIds));
        $clientIds = array_filter($clientIds, static function ($id) {
            return $id > 0;
        });

        if (empty($clientIds)) {
            $opLog->logClientAssignBulk($subModule, [], '', '', false, 'Please select at least one client to assign');
            Response::validationError([
                'clientIds' => ['Please select at least one client to assign']
            ]);
        }

        $assigneeId = (int)$data['assigneeId'];
        $notes = isset($data['notes']) ? trim($data['notes']) : '';

        $manager = $this->adminUserModel->findById($assigneeId);
        if (!$manager || ($manager['status'] ?? '') !== 'active' || !empty($manager['deletedAt'])) {
            $opLog->logClientAssignBulk($subModule, $clientIds, '', '', false, 'Selected manager is not available');
            Response::validationError([
                'assigneeId' => ['Selected manager is not available']
            ]);
        }

        $adminId = null;
        try {
            $token = JWT::getTokenFromHeader();
            if ($token) {
                $payload = JWT::decode($token);
                $adminId = $payload['userId'] ?? null;
            }
        } catch (Exception $e) {
            // ignore - optional
        }

        $db = Database::getInstance();
        $connection = $db->getConnection();
        $now = date('Y-m-d H:i:s');

        try {
            $db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $noteValue = $notes !== '' ? $notes : null;
        $params = array_merge([$assigneeId, $noteValue, $now, $now], $clientIds);
        $sql = "UPDATE clientUsers SET accountManagerId = ?, accountManagerNote = ?, accountManagerAssignedAt = ?, updatedAt = ? WHERE id IN ({$placeholders})";
            $db->query($sql, $params);

            // 同步 Sales-Client 绑定关系到 sales_bind 表（仅 salesId + clientId）
            $bindTable = 'sales_bind';
            foreach ($clientIds as $clientId) {
                $db->query(
                    "INSERT IGNORE INTO {$bindTable} (salesId, clientId, createdAt) VALUES (?, ?, ?)",
                    [$assigneeId, $clientId, $now]
                );
            }

            $managerDisplay = $manager['fullName'] ?? $manager['email'] ?? ('Manager #' . $assigneeId);

            foreach ($clientIds as $clientId) {
                $metadata = [
                    'accountManagerId' => $assigneeId,
                    'accountManagerName' => $manager['fullName'] ?? null,
                    'accountManagerEmail' => $manager['email'] ?? null,
                    'assignedBy' => $adminId,
                    'notes' => $notes,
                    'assignedAt' => $now
                ];

                $this->activityLogModel->logActivity([
                    'userId' => $clientId,
                    'activityType' => 'assigned_manager',
                    'description' => 'Client assigned to manager: ' . $managerDisplay,
                    'metadata' => $metadata
                ]);
            }

            $db->commit();
        } catch (Exception $e) {
            if ($connection->inTransaction()) {
                $db->rollback();
            }
//            Logger::error('Bulk client assignment failed', [
//                'error' => $e->getMessage(),
//                'clientIds' => $clientIds,
//                'assigneeId' => $assigneeId
//            ]);
            $opLog->logClientAssignBulk(
                $subModule,
                $clientIds,
                '',
                '',
                false,
                'Failed to assign clients: ' . $e->getMessage()
            );
            Response::error('Failed to assign clients: ' . $e->getMessage(), 500);
        }

        $managerName = $manager['fullName'] ?? $manager['email'] ?? ('Manager #' . $assigneeId);
        if (count($clientIds) === 1) {
            $opLog->logClientAssignSingle($subModule, $clientIds[0], $managerName, $notes);
        } else {
            $opLog->logClientAssignBulk($subModule, $clientIds, $managerName, $notes);
        }

        Response::success([
            'accountManager' => [
                'id' => $assigneeId,
                'name' => $manager['fullName'] ?? null,
                'email' => $manager['email'] ?? null
            ],
            'managerNote' => $notes !== '' ? $notes : null,
            'assignedAt' => $now
        ], 'Clients assigned successfully');
    }

    /**
     * 批量添加标签
     * POST /api/admin-clients/bulk-tags
     */
    public function bulkAddTags() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'clientIds' => 'required|array',
            'tagIds' => 'required|array'
        ]);

        $clientIds = $data['clientIds'];
        $tagIds = $data['tagIds'];

        $payload = JWT::decode(JWT::getTokenFromHeader());
        $adminUserId = $payload['userId'] ?? null;

        foreach ($clientIds as $clientId) {
            foreach ($tagIds as $tagId) {
                // 检查标签是否已存在
                $existing = $this->tagAssignmentModel->findOne([
                    'leadId' => $clientId,
                    'tagId' => $tagId
                ]);

                if (!$existing) {
                    $this->tagAssignmentModel->assignTag($clientId, $tagId, $adminUserId);
                }
            }
        }

        Response::success(null, 'Tags added successfully');
    }

    /**
     * 导出客户数据
     * GET /api/admin-clients/export/{format}
     */
    public function export($format) {
        $allowedFormats = ['csv', 'excel'];
        if (!in_array($format, $allowedFormats)) {
            Response::error('Invalid export format', 400);
        }

        // 获取所有已验证客户
        $clients = $this->userModel->query(
            "SELECT
                cu.id,
                cu.firstName,
                cu.lastName,
                cu.email,
                cu.phone,
                cu.country,
                cu.status,
                cu.createdAt,
                cu.lastLoginAt,
                (SELECT submissionStatus FROM clientKycSubmissions WHERE clientId = cu.id ORDER BY createdAt DESC LIMIT 1) as kycStatus
             FROM clientUsers cu
             WHERE 1=1 -- 暂时注释掉 emailVerified = 1 过滤条件
             ORDER BY cu.createdAt DESC"
        );

        if ($format === 'csv') {
            $this->exportCSV($clients);
        } else {
            $this->exportExcel($clients);
        }
    }

    // ========== Helper Methods ==========

    /**
     * 获取客户标签
     */
    private function getClientTags($clientId) {
        return $this->userModel->query(
            "SELECT lt.id, lt.tagName as name, lt.tagColor as color, 'default' as type
             FROM leadTags lt
             INNER JOIN leadTagAssignments lta ON lt.id = lta.tagId
             WHERE lta.leadId = :clientId
             ORDER BY lt.tagName",
            ['clientId' => $clientId]
        );
    }

    /**
     * 检查客户是否在线
     */
    private function isClientOnline($clientId) {
        // 检查最近5分钟内是否有活动
        $recentActivity = $this->userModel->queryOne(
            "SELECT COUNT(*) as count
             FROM clientActivityLog
             WHERE userId = :userId
             AND createdAt > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            ['userId' => $clientId]
        );

        return $recentActivity['count'] > 0;
    }

    /**
     * 导出CSV
     */
    private function exportCSV($clients) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV头部
        fputcsv($output, [
            'ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Country',
            'Status', 'KYC Status', 'Registered At', 'Last Login'
        ]);

        // 数据行
        foreach ($clients as $client) {
            fputcsv($output, [
                $client['id'],
                $client['firstName'],
                $client['lastName'],
                $client['email'],
                $client['phone'],
                $client['country'],
                $client['status'],
                $client['kycStatus'],
                $client['createdAt'],
                $client['lastLoginAt']
            ]);
        }

        fclose($output);
    }

    /**
     * 导出Excel（简化版，实际可使用PhpSpreadsheet）
     */
    private function exportExcel($clients) {
        // 简化版：输出为CSV但设置Excel MIME类型
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.xls"');

        $this->exportCSV($clients);
    }

    /**
     * 获取客户文档列表
     * GET /api/admin-clients/{id}/documents
     * 包括：注册时签署的法律文档 + KYC文档 + IB 确认的文档（ibPartnerDocumentAcknowledgements）
     */
    public function getDocuments($clientId) {
        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $documents = [];

        // 1. 获取注册时签署的法律文档
        $registrationDocs = $this->userModel->query(
            "SELECT
                lds.id,
                lds.documentType,
                lds.signedAt,
                ld.title,
                ld.content,
                ld.version,
                'registration' as source,
                lds.ipAddress,
                lds.userAgent
             FROM legalDocumentSignatures lds
             INNER JOIN legalDocuments ld ON lds.documentId = ld.id
             WHERE lds.leadId = :clientId
             ORDER BY lds.signedAt ASC",
            ['clientId' => $clientId]
        );

        foreach ($registrationDocs as $doc) {
            $documents[] = [
                'id' => $doc['id'],
                'documentType' => $doc['documentType'],
                'title' => $doc['title'],
                'content' => $doc['content'],
                'version' => $doc['version'],
                'signedAt' => $doc['signedAt'],
                'source' => 'registration',
                'ipAddress' => $doc['ipAddress'],
                'userAgent' => $doc['userAgent']
            ];
        }

        // 2. 获取KYC文档
        // 如果客户有 kycSubmissionId，只获取该 submission 的文档；否则获取所有 KYC 文档
        $kycDocsParams = ['clientId' => $clientId];
        $kycDocsWhere = "WHERE cks.clientId = :clientId";

        if (!empty($client['kycSubmissionId'])) {
            $kycDocsWhere .= " AND ckds.submissionId = :submissionId";
            $kycDocsParams['submissionId'] = $client['kycSubmissionId'];
        }

        $kycDocs = $this->userModel->query(
            "SELECT
                ckds.id,
                ckds.signedAt,
                ktd.documentTitle as title,
                ktd.documentContent as content,
                'kyc_document' as documentType,
                kt.templateName,
                'kyc' as source,
                ckds.ipAddress,
                ckds.userAgent
             FROM clientKycDocumentSignatures ckds
             INNER JOIN kycTemplateDocuments ktd ON ckds.templateDocumentId = ktd.id
             INNER JOIN clientKycSubmissions cks ON ckds.submissionId = cks.id
             INNER JOIN kycTemplates kt ON cks.templateId = kt.id
             {$kycDocsWhere}
             ORDER BY ckds.signedAt ASC",
            $kycDocsParams
        );

        foreach ($kycDocs as $doc) {
            $documents[] = [
                'id' => 'kyc_' . $doc['id'],
                'documentType' => $doc['documentType'],
                'title' => $doc['title'],
                'content' => $doc['content'],
                'templateName' => $doc['templateName'],
                'signedAt' => $doc['signedAt'],
                'source' => 'kyc',
                'ipAddress' => $doc['ipAddress'],
                'userAgent' => $doc['userAgent']
            ];
        }

        // 3. IB 项目确认的文档（不改法律/KYC 表，仅合并查询结果）
        $ibDocs = $this->userModel->query(
            "SELECT
                pda.id,
                pda.acknowledgedAt AS signedAt,
                dt.documentTitle AS title,
                dt.documentContent AS content,
                COALESCE(dt.version, '1.0') AS version,
                'ib_agreement' AS documentType,
                'ib' AS source,
                pda.ipAddress,
                NULL AS userAgent
             FROM ibPartners ip
             INNER JOIN ibPartnerDocumentAcknowledgements pda ON pda.ibPartnerId = ip.id AND pda.acknowledged = 1
             INNER JOIN ibDocumentTemplates dt ON pda.documentTemplateId = dt.id
             WHERE ip.userId = :clientId
             ORDER BY pda.acknowledgedAt ASC",
            ['clientId' => $clientId]
        );

        foreach ($ibDocs as $doc) {
            $documents[] = [
                'id' => 'ib_' . $doc['id'],
                'documentType' => $doc['documentType'],
                'title' => $doc['title'],
                'content' => $doc['content'],
                'version' => $doc['version'],
                'signedAt' => $doc['signedAt'],
                'source' => 'ib',
                'ipAddress' => $doc['ipAddress'],
                'userAgent' => $doc['userAgent']
            ];
        }

        // 排序：先按来源 注册 → KYC → IB，同组内再按签署时间
        $sourceOrder = ['registration' => 0, 'kyc' => 1, 'ib' => 2];
        usort($documents, function($a, $b) use ($sourceOrder) {
            $oa = $sourceOrder[$a['source']] ?? 99;
            $ob = $sourceOrder[$b['source']] ?? 99;
            if ($oa !== $ob) {
                return $oa - $ob;
            }
            $ta = isset($a['signedAt']) ? strtotime($a['signedAt']) : 0;
            $tb = isset($b['signedAt']) ? strtotime($b['signedAt']) : 0;
            return $ta - $tb;
        });

        Response::success($documents);
    }

    /**
     * 获取客户活动日志
     * GET /api/admin-clients/{id}/activities
     */
    public function getActivities($clientId) {
        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $activities = $this->userModel->query(
            "SELECT * FROM clientActivityLog
             WHERE userId = :clientId
             ORDER BY createdAt DESC
             LIMIT 100",
            ['clientId' => $clientId]
        );

        Response::success($activities);
    }

    /**
     * 获取客户出金模板 payment 部分（分页）
     * GET /api/admin-clients/{id}/withdraw-template?page=1&per_page=10
     * GET /api/admin-clients/withdraw-template?clientId={id}&page=1&per_page=10
     */
    public function getWithdrawTemplatePayments($clientId = null) {
        $clientId = $clientId !== null ? (int)$clientId : (int)($_GET['clientId'] ?? 0);
        if ($clientId <= 0) {
            Response::validationError(['clientId' => 'clientId is required']);
        }

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;
        $result = $this->withdrawalSubmissionModel->getClientTemplatePayments($clientId, $page, $perPage);

        Response::success([
            'payment' => array_map(function ($submission) {
                return $this->formatWithdrawTemplatePayment($submission);
            }, $result['items']),
            'pagination' => [
                'total' => (int)$result['total'],
                'per_page' => (int)$result['per_page'],
                'page' => (int)$result['page'],
                'total_pages' => (int)$result['total_pages'],
                'has_more' => ($result['page'] * $result['per_page']) < $result['total']
            ]
        ]);
    }

    private function formatWithdrawTemplatePayment(array $submission) {
        $payment = [
            'id' => (int)$submission['id'],
            'templateName' => $submission['templateName'] ?? null,
            'gatewayName' => $submission['gatewayName'] ?? null,
            'gatewayIconClass' => $submission['gatewayIconClass'] ?? null,
            'isCrypto' => strtolower((string)($submission['gatewayType'] ?? '')) === 'crypto',
            'paymentMethodId' => $submission['paymentMethodId'] !== null ? (int)$submission['paymentMethodId'] : null,
            'submissionStatus' => $submission['submissionStatus'],
            'submittedAt' => $submission['submittedAt'],
            'createdAt' => $submission['createdAt'],
            'updatedAt' => $submission['updatedAt'],
            'detail' => []
        ];

        if (in_array((string)($submission['submissionStatus'] ?? ''), ['draft', 'incomplete'], true)) {
            return $payment;
        }

        // payment 只展示模板里锁定且带 scope 的答案，保持和客户端 payments 接口一致。
        $answers = $this->withdrawalAnswerModel->getSubmissionAnswers((int)$submission['id']);
        foreach ($answers as $answer) {
            if ((int)($answer['isLocked'] ?? 0) !== 1) {
                continue;
            }

            $scope = trim((string)($answer['scope'] ?? ''));
            if ($scope === '') {
                continue;
            }

            $payment['detail'][$scope] = $this->withdrawalAnswerModel->getAnswerValue($answer);
        }

        return $payment;
    }

    /**
     * 获取客户KYC历史记录
     * GET /api/admin-clients/{id}/kyc-history
     */
    public function getKycHistory($clientId) {
        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $kycHistory = $this->userModel->query(
            "SELECT
                cks.*,
                kt.templateName,
                au.firstName as reviewerFirstName,
                au.lastName as reviewerLastName
             FROM clientKycSubmissions cks
             LEFT JOIN kycTemplates kt ON cks.templateId = kt.id
             LEFT JOIN adminUsers au ON cks.reviewedBy = au.id
             WHERE cks.clientId = :clientId
             ORDER BY cks.createdAt DESC",
            ['clientId' => $clientId]
        );

        Response::success($kycHistory);
    }

    /**
     * 更新客户状态
     * PUT /api/admin-clients/{id}/status
     */
    public function updateStatus($clientId) {
        $data = json_decode(file_get_contents('php://input'), true);

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        Validator::make($data, [
            'status' => 'required|in:active,inactive,suspended,pending_verification'
        ]);

        $this->userModel->update($clientId, ['status' => $data['status']]);

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $clientId,
            'activityType' => 'status_changed',
            'description' => 'Status changed to ' . $data['status'],
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'oldStatus' => $client['status'],
                'newStatus' => $data['status']
            ])
        ]);

        Response::success(null, 'Status updated successfully');
    }

    /**
     * 强制通过客户 KYC：
     * - 若无 submission，则按客户国家创建一条最合适的 submission
     * - 若有 submission，则直接强制标记为 approved
     * POST /api/admin-clients/{id}/approve-kyc
     */
    public function approveKyc($clientId) {
        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $adminPayload = JWT::getPayload();
        $adminId = isset($adminPayload['userId']) ? (int)$adminPayload['userId'] : null;
        $now = date('Y-m-d H:i:s');

        $submission = $this->kycSubmissionModel->getLatestSubmission($clientId);
        $createdSubmission = false;

        if (!$submission) {
            $template = $this->selectBestTemplateForClient($client);
            if (!$template || empty($template['id'])) {
                Response::error('No suitable active KYC template found for this client', 400);
            }

            $submissionId = $this->kycSubmissionModel->create([
                'clientId' => $clientId,
                'templateId' => (int)$template['id'],
                'submissionStatus' => 'draft',
                'ipAddress' => null,
                'userAgent' => 'admin-force-approve'
            ]);

            if (!$submissionId) {
                Response::error('Failed to create KYC submission', 500);
            }

            $submission = $this->kycSubmissionModel->findById($submissionId);
            $createdSubmission = true;

            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'application_started',
                'Application Started',
                'KYC submission created by administrator for force approval'
            );
        }

        $submissionId = (int)$submission['id'];

        if (($submission['submissionStatus'] ?? '') !== 'approved') {
            $this->kycSubmissionModel->approve($submissionId, $adminId, 'Force approved by administrator');
            $this->kycSubmissionModel->update($submissionId, [
                'submittedAt' => $submission['submittedAt'] ?? $now,
                'updatedAt' => $now
            ]);

            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'application_submitted',
                'Application Submitted',
                'KYC application submitted by administrator for force approval',
                null,
                null,
                'completed'
            );
            $this->timelineModel->addEvent(
                $submissionId,
                $clientId,
                'approved',
                'Approved',
                'KYC application force approved by administrator',
                null,
                null,
                'completed'
            );
        }

        $this->userModel->update($clientId, [
            'kycStatus' => 'approved',
            'kycSubmissionId' => $submissionId,
            'verifiedAt' => $now,
            'updatedAt' => $now
        ]);

        $this->activityLogModel->logActivity([
            'userId' => $clientId,
            'activityType' => 'kyc_force_approved',
            'description' => 'KYC force approved by administrator',
            'metadata' => json_encode([
                'adminUserId' => $adminId,
                'submissionId' => $submissionId,
                'createdSubmission' => $createdSubmission
            ])
        ]);

        $updatedClient = $this->userModel->findById($clientId);
        $updatedSubmission = $this->kycSubmissionModel->findById($submissionId);

        Response::success([
            'client' => $updatedClient,
            'submission' => $updatedSubmission,
            'createdSubmission' => $createdSubmission
        ], 'KYC approved successfully');
    }

    /**
     * 直接重置客户密码
     * POST /api/admin-clients/{id}/reset-password
     */
    public function resetPassword($clientId) {
        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        Validator::make($data, [
            'newPassword' => 'required|string|min:6'
        ]);

        $newPassword = $data['newPassword'];
        $passwordHash = $this->userModel->hashPassword($newPassword);

        $this->userModel->update($clientId, ['passwordHash' => $passwordHash]);

        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $clientId,
            'activityType' => 'password_reset_by_admin',
            'description' => 'Password directly reset by administrator',
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null
            ])
        ]);

        Response::success(null, 'Password reset successfully');
    }

    /**
     * 发送客户密码重置邮件
     * POST /api/admin-clients/{id}/send-password-reset
     */
    public function sendPasswordReset($clientId) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_clients_list'));

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            (new AdminOperationLogWriter())->logClientPasswordResetEmail(
                $subModule,
                0,
                '',
                true,
                false,
                'Client not found'
            );
            Response::notFound('Client not found');
        }
        $useHashMode = isset($data['useHashMode']) && $data['useHashMode'] === true;

        $token = $this->passwordResetModel->createToken($client['email']);

        $appConfig = require __DIR__ . '/../config/app.php';
        $clientFrontendUrl = $appConfig['client_frontend_url'] ?? 'http://localhost:5173';
        $hashPrefix = $useHashMode ? '#' : '';
        $resetLink = "{$clientFrontendUrl}{$hashPrefix}/client/reset-password?token={$token}";

        $userName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        if ($userName === '') {
            $userName = $client['email'];
        }

        $emailSent = (new EmailSender())->sendPasswordResetEmail(
            $client['email'],
            $userName,
            $resetLink,
            1
        );

        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $clientId,
            'activityType' => 'password_reset_email_sent_by_admin',
            'description' => 'Password reset email sent by administrator',
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'email' => $client['email'],
                'emailSent' => (bool)$emailSent
            ])
        ]);

        (new AdminOperationLogWriter())->logClientPasswordResetEmail(
            $subModule,
            $clientId,
            $client['email'],
            (bool) $emailSent
        );

        Response::success([
            'email' => $client['email'],
            'emailSent' => (bool)$emailSent
        ], 'Password reset email sent successfully');
    }

    /**
     * 发送邮件给客户
     * POST /api/admin-clients/{id}/send-email
     */
    public function sendEmail($clientId) {
        $data = json_decode(file_get_contents('php://input'), true);

        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        Validator::make($data, [
            'subject' => 'required',
            'message' => 'required'
        ]);

        // TODO: 实现邮件发送逻辑

        // 记录活动
        $payload = JWT::decode(JWT::getTokenFromHeader());
        $this->activityLogModel->logActivity([
            'userId' => $clientId,
            'activityType' => 'email_sent',
            'description' => 'Email sent: ' . $data['subject'],
            'metadata' => json_encode([
                'adminUserId' => $payload['userId'] ?? null,
                'subject' => $data['subject']
            ])
        ]);

        Response::success(null, 'Email sent successfully');
    }

    /**
     * 默认的货币选项
     */
    private function getCurrencyOptions(): array {
        return [
            ['code' => 'USD', 'label' => 'USD - US Dollar'],
            ['code' => 'EUR', 'label' => 'EUR - Euro'],
            ['code' => 'GBP', 'label' => 'GBP - British Pound'],
            ['code' => 'JPY', 'label' => 'JPY - Japanese Yen'],
            ['code' => 'AUD', 'label' => 'AUD - Australian Dollar'],
            ['code' => 'CAD', 'label' => 'CAD - Canadian Dollar'],
            ['code' => 'SGD', 'label' => 'SGD - Singapore Dollar'],
            ['code' => 'CNY', 'label' => 'CNY - Chinese Yuan']
        ];
    }

    /**
     * 默认国家列表
     */
    private function getDefaultCountries(): array {
        return [
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'UK', 'name' => 'United Kingdom'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'IN', 'name' => 'India']
        ];
    }

    /**
     * 生成安全密码
     */
    private function generateSecurePassword(int $length = 12): string {
        $length = max($length, 10);
        $sets = [
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'lower' => 'abcdefghjkmnpqrstuvwxyz',
            'digits' => '23456789',
            'symbols' => '!@#$%^&*()-_=+'
        ];

        $password = '';
        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }

        $allCharacters = implode('', $sets);
        while (strlen($password) < $length) {
            $password .= $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * 为后台手动创建的客户生成法律文档签署记录
     */
    private function createLegalDocumentSignatures(int $userId, array $userData = []): void {
        try {
            $legalDocModel = new LegalDocument();
            $signatureModel = new LegalDocumentSignature();

            $requiredDocTypes = ['terms_of_service', 'privacy_policy', 'risk_disclosure'];
            $fullName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
            $email = $userData['email'] ?? '';

            foreach ($requiredDocTypes as $docType) {
                $document = $legalDocModel->findOne([
                    'documentType' => $docType,
                    'isActive' => 1
                ]);

                if ($document && !$signatureModel->hasSignedDocument($userId, $docType)) {
                    $signatureModel->recordSignature(
                        $userId,
                        $document['id'],
                        $docType,
                        $document['version'] ?? null,
                        null,
                        null
                    );
                }
            }
        } catch (Exception $e) {
//            Logger::error('Failed to create legal document signatures for admin-created client', [
//                'userId' => $userId,
//                'error' => $e->getMessage()
//            ]);
        }
    }

    /**
     * 获取客户交易信息
     * GET /api/admin-clients/{id}/trading-info
     */
    /**
     * Client trading history: open positions, pending orders, or closed orders.
     * Reads the orders table, which OrderSyncService keeps in sync on a schedule; this never
     * calls a trading platform gateway directly.
     *
     * GET /api/admin-clients/{id}/trading-history
     *   status=closed|open|pending (defaults to closed)
     *   page, pageSize, keywords, side, periodFrom, periodTo, sort, sortOrder
     *
     * Totals (lots, net profit) cover the whole filtered result set, not just the page.
     */
    public function getTradingHistory($clientId) {
        $clientId = (int)$clientId;
        if ($clientId <= 0) {
            Response::error('Client id is required', 400);
        }

        // Sales admins may only read their own clients, same scope check as getTransactions
        $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
        if ($scope['scope'] === 'none') {
            Response::forbidden('You do not have permission to view this client');
        }
        $clientOptions = [];
        if ($scope['scope'] === 'own') {
            $clientOptions['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
        }

        $client = $this->userModel->getAdminClientDetailById($clientId, $clientOptions);
        if (!$client) {
            Response::notFound('Client not found');
        }

        // orders.trading_status: 0 = open position, 1 = pending order, 2 = closed order
        $statusMap = ['open' => 0, 'pending' => 1, 'closed' => 2];
        $statusKey = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'closed';
        if (!isset($statusMap[$statusKey])) {
            $statusKey = 'closed';
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $pageSize = max(1, min(200, $pageSize));
        $sort = isset($_GET['sort']) ? (string)$_GET['sort'] : ($statusKey === 'closed' ? 'CloseTime' : 'OpenTime');
        $sortOrder = (isset($_GET['sortOrder']) && strtoupper($_GET['sortOrder']) === 'ASC') ? 'ASC' : 'DESC';

        $filters = ['trading_status' => $statusMap[$statusKey]];
        // Positions and pending orders have no close time, so filter the range on open time
        if ($statusKey !== 'closed') {
            $filters['date_field'] = 'opentime';
        }
        if (!empty($_GET['keywords'])) {
            $filters['keywords'] = (string)$_GET['keywords'];
        }
        // side=buy|sell covers market orders plus their limit/stop variants; a numeric
        // cmd still matches one exact platform order type.
        $side = isset($_GET['side']) ? strtolower(trim((string)$_GET['side'])) : '';
        if ($side === 'buy' || $side === 'sell') {
            $filters['cmd_in'] = $side === 'buy' ? [0, 2, 4, 8] : [1, 3, 5, 9];
        } elseif (isset($_GET['cmd']) && $_GET['cmd'] !== '') {
            $filters['cmd'] = (int)$_GET['cmd'];
        }
        if (!empty($_GET['periodFrom'])) {
            $filters['periodFrom'] = (string)$_GET['periodFrom'];
        }
        if (!empty($_GET['periodTo'])) {
            $filters['periodTo'] = (string)$_GET['periodTo'];
        }

        $emptyResult = [
            'items' => [],
            'totals' => $this->orderModel->getOrderTotalsByLogins([], [], $filters),
            'pagination' => [
                'currentPage' => $page,
                'pageSize' => $pageSize,
                'total' => 0,
                'hasMore' => false
            ]
        ];

        try {
            // Reuse the trading account lookup for providerAccountId, platform, type and currency
            $externalAccounts = $this->externalAccountModel->findAllByUserIdWithPlatform($client['id']);

            $logins = [];
            $platformKeys = [];
            $accountMeta = []; // "platformKey|login" => platform name / account number / currency / type
            foreach ($externalAccounts as $externalAccount) {
                $login = trim((string)($externalAccount['providerAccountId'] ?? ''));
                $platformKey = strtolower(trim((string)($externalAccount['platformKey'] ?? '')));
                if ($login === '' || $platformKey === '') {
                    continue;
                }

                if (!in_array($login, $logins, true)) {
                    $logins[] = $login;
                }
                if (!in_array($platformKey, $platformKeys, true)) {
                    $platformKeys[] = $platformKey;
                }
                $accountMeta[$platformKey . '|' . $login] = [
                    'PlatformName' => $externalAccount['platformName'] ?? '',
                    'AccountNumber' => $externalAccount['accountNumber'] ?? '',
                    'Currency' => $externalAccount['accountCurrency'] ?? '',
                    'AccountType' => $externalAccount['accountType'] ?? ''
                ];
            }

            if (empty($logins)) {
                Response::success($emptyResult, 'Trading history loaded');
                return;
            }

            $dbResult = $this->orderModel->getOrderHistoryByLogins(
                $logins,
                $platformKeys,
                $filters,
                $page,
                $pageSize,
                $sort,
                $sortOrder
            );

            $items = (isset($dbResult['items']) && is_array($dbResult['items'])) ? $dbResult['items'] : [];
            foreach ($items as &$item) {
                $metaKey = strtolower((string)($item['trading_platforms_key'] ?? '')) . '|' . (string)($item['Login'] ?? '');
                $meta = $accountMeta[$metaKey] ?? [
                    'PlatformName' => '',
                    'AccountNumber' => '',
                    'Currency' => '',
                    'AccountType' => ''
                ];
                $item = array_merge($item, $meta);
                // volume is lots * 100; convert the same way the totals do
                $item['Lots'] = (float)($item['Volume'] ?? 0) / Order::VOLUME_PER_LOT;
                // Keep row net P/L consistent with the totals: gross + commission + swap + taxes
                $item['NetProfit'] = (float)($item['Profit'] ?? 0)
                    + (float)($item['Commission'] ?? 0)
                    + (float)($item['Storage'] ?? 0)
                    + (float)($item['Taxes'] ?? 0);
            }
            unset($item);

            $result = [
                'items' => $items,
                'totals' => $this->orderModel->getOrderTotalsByLogins($logins, $platformKeys, $filters),
                'pagination' => [
                    'currentPage' => $dbResult['page'] ?? $page,
                    'pageSize' => $dbResult['pageSize'] ?? $pageSize,
                    'total' => $dbResult['total'] ?? 0,
                    'hasMore' => $dbResult['hasMore'] ?? false
                ]
            ];

            Response::success($result, 'Trading history loaded');
        } catch (Exception $e) {
            Logger::error('Failed to fetch client trading history: ' . $e->getMessage());
            Response::error('Failed to fetch trading history', 500);
        }
    }

    public function getTradingInfo($clientId) {
        // 验证客户是否存在
        $client = $this->userModel->findById($clientId);
        if (!$client) {
            Response::notFound('Client not found');
        }

        $userId = $client['id'];

        $externalAccounts = $this->externalAccountModel->findAllByUserIdWithPlatform($userId);
        if (empty($externalAccounts)) {
            Response::success([], 'No trading account found');
        }

        $platformAccounts = [];
        foreach ($externalAccounts as $externalAccount) {
            $providerAccountId = trim((string)($externalAccount['providerAccountId'] ?? ''));
            if ($providerAccountId === '') {
                continue;
            }

            $platformKey = strtolower(trim((string)($externalAccount['platformKey'] ?? '')));
            if ($platformKey === '') {
                continue;
            }

            if (!isset($platformAccounts[$platformKey])) {
                $platformAccounts[$platformKey] = [];
            }
            $platformAccounts[$platformKey][] = $externalAccount;
        }

        if (empty($platformAccounts)) {
            Response::success([], 'No trading account found');
        }

        $financeProAccounts = [];
        $mt5Accounts = [];
        $mt4Accounts = [];

        // 检查用户是否有financepro账户
        $hasFinancePro = $this->accountModel->hasFinanceProAccount($userId);

        try {
            // 只有用户有financepro账户才调用第三方接口
            if ($hasFinancePro) {
                // 获取financepro账户的providerAccountId
                $financeProAccount = $this->externalAccountModel->findFinanceProByUserId($userId);
                if (!$financeProAccount || empty($financeProAccount['providerAccountId'])) {
                    // 虽然有financepro账户，但没有有效的providerAccountId
                    Response::success([], 'FinancePro account found but no provider account ID');
                    return;
                }
                foreach (($platformAccounts['financepro'] ?? []) as $financeProExternalAccount) {
                    $financeProAccountId = $financeProExternalAccount['providerAccountId'];
                    $financeProSummary = $this->buildStoredBalanceSummary($financeProExternalAccount);
                    // credit 不依赖实时接口，直接用同步任务写入的 platformCredit
                    if (isset($financeProExternalAccount['platformCredit'])) {
                        $financeProSummary['Credit'] = (float)$financeProExternalAccount['platformCredit'];
                    }
                    $financeProInfo = null;

                    // 本地拉取：不再实时查 FinancePro 网关，余额/credit 直接用同步快照
                    /*
                    if ($this->financePro['get_client_summary']) {
                        try {
                            $summaryResponse = $this->financeProClient->request(
                                $this->financePro['get_client_summary'],
                                'GET',
                                ['accountId' => $financeProAccountId],
                                [
                                    'expect_success_key' => 'Success',
                                    'success_value' => true,
                                    'error_message_key' => 'ErrMsg'
                                ]
                            );
                            $summaryData = isset($summaryResponse['ResData']) && is_array($summaryResponse['ResData'])
                                ? $summaryResponse['ResData']
                                : [];
                            $financeProSummary = array_merge($financeProSummary, $summaryData);
                        } catch (Exception $e) {
//                            Logger::error('Failed to fetch account summary for admin', [
//                                'clientId' => $clientId,
//                                'accountId' => $financeProAccountId,
//                                'exception' => $e->getMessage()
//                            ]);
                        }
                    }

                    if ($this->financePro['get_account_info']) {
                        try {
                            $infoResponse = $this->financeProClient->request(
                                $this->financePro['get_account_info'],
                                'GET',
                                ['id' => $financeProAccountId],
                                [
                                    'expect_success_key' => 'Success',
                                    'success_value' => true,
                                    'error_message_key' => 'ErrMsg'
                                ]
                            );
                            $financeProInfo = $infoResponse['ResData'] ?? null;
                        } catch (Exception $e) {
//                            Logger::error('Failed to fetch account info for admin', [
//                                'clientId' => $clientId,
//                                'accountId' => $financeProAccountId,
//                                'exception' => $e->getMessage()
//                            ]);
                        }
                    }
                    */

                    $financeProAccounts[] = [
                        'platformKey' => 'financepro',
                        'accountId' => $financeProAccountId,
                        'accountSummary' => $financeProSummary,
                        'accountInfo' => $financeProInfo,
                        'externalAccount' => $financeProExternalAccount
                    ];
                }

            }

            if (isset($platformAccounts['mt5'])) {
                // 本地拉取：不再实时查 MT5 网关，余额/credit 直接用同步快照
                /*
                $mt5Client = null;
                try {
                    // 后台账户信息 / 余额是读操作，走只读网关
                    $mt5Client = new Mt5GatewayApiClient();
                } catch (Exception $e) {
                    $mt5Client = null;
                }
                */

                foreach ($platformAccounts['mt5'] as $mt5Account) {
                    $mt5AccountId = $mt5Account['providerAccountId'];
                    $mt5Summary = array_merge([
                        'accountId' => $mt5AccountId,
                        'platformBalance' => $mt5Account['platformBalance'] ?? null,
                        'leverage' => $mt5Account['leverage'] ?? null,
                        'status' => $mt5Account['status'] ?? null,
                        'accountCurrency' => $mt5Account['accountCurrency'] ?? null,
                        'accountType' => $mt5Account['accountType'] ?? null,
                        'platformName' => $mt5Account['platformName'] ?? 'MT5'
                    ], $this->buildStoredBalanceSummary($mt5Account));
                    // credit 不依赖实时接口，直接用同步任务写入的 platformCredit
                    if (isset($mt5Account['platformCredit'])) {
                        $mt5Summary['Credit'] = (float)$mt5Account['platformCredit'];
                    }
                    $mt5Info = null;

                    /*
                    if ($mt5Client) {
                        try {
                            $mt5Info = $mt5Client->getUser($mt5AccountId);
                        } catch (Exception $e) {
//                            Logger::error('Failed to fetch MT5 trading info for admin', [
//                                'clientId' => $clientId,
//                                'accountId' => $mt5AccountId,
//                                'exception' => $e->getMessage()
//                            ]);
                        }

                        try {
                            $mt5Balance = $mt5Client->getBalance($mt5AccountId);
                            $mt5Summary = $this->mergeBalanceSummary(
                                $mt5Summary,
                                $this->buildMt5BalanceSummary($mt5Balance)
                            );
                        } catch (Exception $e) {
//                            Logger::error('Failed to fetch MT5 balance for admin', [
//                                'clientId' => $clientId,
//                                'accountId' => $mt5AccountId,
//                                'exception' => $e->getMessage()
//                            ]);
                        }
                    }
                    */

                    $mt5Accounts[] = [
                        'platformKey' => 'mt5',
                        'accountId' => $mt5AccountId,
                        'accountSummary' => $mt5Summary,
                        'accountInfo' => $mt5Info,
                        'externalAccount' => $mt5Account
                    ];
                }

            }

            // MT4 账户：余额用同步写入的快照（platformBalance/platformCredit），不依赖网关在线
            if (isset($platformAccounts['mt4'])) {
                foreach ($platformAccounts['mt4'] as $mt4Account) {
                    $mt4AccountId = $mt4Account['providerAccountId'];
                    $mt4Summary = array_merge([
                        'accountId' => $mt4AccountId,
                        'platformBalance' => $mt4Account['platformBalance'] ?? null,
                        'leverage' => $mt4Account['leverage'] ?? null,
                        'status' => $mt4Account['status'] ?? null,
                        'accountCurrency' => $mt4Account['accountCurrency'] ?? null,
                        'accountType' => $mt4Account['accountType'] ?? null,
                        'platformName' => $mt4Account['platformName'] ?? 'MT4'
                    ], $this->buildStoredBalanceSummary($mt4Account));
                    // credit 不依赖实时接口，直接用同步任务写入的 platformCredit
                    if (isset($mt4Account['platformCredit'])) {
                        $mt4Summary['Credit'] = (float)$mt4Account['platformCredit'];
                    }
                    $mt4Accounts[] = [
                        'platformKey' => 'mt4',
                        'accountId' => $mt4AccountId,
                        'accountSummary' => $mt4Summary,
                        'accountInfo' => null,
                        'externalAccount' => $mt4Account
                    ];
                }
            }

            $accounts = [];
            foreach ($financeProAccounts as $account) {
                $accounts[] = $this->buildTradingAccountListItem($account);
            }
            foreach ($mt5Accounts as $account) {
                $accounts[] = $this->buildTradingAccountListItem($account);
            }
            foreach ($mt4Accounts as $account) {
                $accounts[] = $this->buildTradingAccountListItem($account);
            }

            Response::success($accounts, 'Trading info loaded');
        } catch (Exception $e) {
//            Logger::error('Failed to fetch trading info for admin', [
//                'clientId' => $clientId,
//                'accountId' => $accountId,
//                'exception' => $e->getMessage()
//            ]);

            Response::error('Failed to fetch trading info: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取用于 IB Invitation「Select Client」下拉的客户列表
     * GET /api/admin-clients/for-ib-selection
     * 与 /clients-list 数据源一致（ClientUser::getClientListForAdmin），并排除已接受 IB 邀请的用户（ibPartners 中已有记录）
     */
    public function getClientsForIbSelection() {
        try {
            $search = $_GET['search'] ?? '';
            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
            $perPage = min($perPage, 100);
            $page = max(1, $page);

            $scope = AdminSalesPermission::getClientDataScopeForPage('page_clientslist');
            $options = [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'kyc_status' => 'approved',
                'sort_field' => 'firstName',
                'sort_direction' => 'asc',
                'excludeIbAccepted' => true
            ];
            if ($scope['scope'] === 'none') {
                Response::paginated([], 0, $page, $perPage);
                return;
            }
            if ($scope['scope'] === 'own') {
                $options['restrict_to_sales_id'] = $scope['restrict_to_sales_id'];
            }

            $result = $this->userModel->getClientListForAdmin($options);

            $items = $result['items'];
            $total = $result['total'];

            $formattedClients = [];
            foreach ($items as $client) {
                $firstName = $client['firstName'] ?? '';
                $lastName = $client['lastName'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if ($fullName === '') {
                    $fullName = $client['email'] ?? '';
                }
                $formattedClients[] = [
                    'id' => (int) $client['id'],
                    'name' => $fullName,
                    'email' => $client['email'] ?? '',
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'initials' => strtoupper(
                        mb_substr($firstName, 0, 1, 'UTF-8') . mb_substr($lastName, 0, 1, 'UTF-8')
                    )
                ];
            }

            Response::success([
                'items' => $formattedClients,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to fetch clients for IB selection: ' . $e->getMessage(), 500);
        }
    }

    private function logClientCreateFailure(array $input, string $message) {
        $subModule = OperationLogPages::resolveLogClient($input, OperationLogPages::subModuleKeyByAlias('page_leads'));
        (new AdminOperationLogWriter())->logClientCreated($subModule, [], 0, false, $message);
    }

    private function logClientProfileUpdateFailure(array $data, $clientId, string $message) {
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_clients_list'));
        (new AdminOperationLogWriter())->logClientProfileUpdate($subModule, (int) $clientId, '', [], false, $message);
    }

}
