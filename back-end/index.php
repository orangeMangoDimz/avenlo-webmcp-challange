<?php
/**
 * CRM Backend API
 * 主入口文件
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
// date_default_timezone_set('Asia/Shanghai');  // 注释掉，使用服务器默认时区，避免硬编码

// 加载 Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// 加载配置
$appConfig = require __DIR__ . '/config/app.php';

// 设置CORS
require_once __DIR__ . '/middleware/CorsMiddleware.php';
CorsMiddleware::handle();

require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/RequestLogContext.php';
require_once __DIR__ . '/services/ApplicationErrorHandler.php';

// Request correlation for application logging (before auth / routing)
$requestId = RequestLogContext::initForHttpRequest();
header('X-Request-ID: ' . $requestId);

set_exception_handler([ApplicationErrorHandler::class, 'uncaughtExceptionHandler']);
register_shutdown_function([ApplicationErrorHandler::class, 'shutdownHandler']);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 获取请求信息
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// 邮件加密链接入口：仅 ?t= 密文，不暴露接口路径与参数，仅后端可解码
if (isset($_GET['t']) && trim((string) $_GET['t']) !== '') {
    require_once __DIR__ . '/api/mail-links.php';
    exit;
}

// 解析请求路径
// 支持两种访问方式：
// 1. 通过 .htaccess 重写: /back-end/api/auth/login
// 2. 直接访问: /back-end/index.php?path=api/auth/login
if (isset($_GET['path'])) {
    $requestPath = trim($_GET['path'], '/');
} else {
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    $requestPath = str_replace($scriptName, '', $requestUri);
    $requestPath = parse_url($requestPath, PHP_URL_PATH);
    $requestPath = trim($requestPath, '/');
}

RequestLogContext::setRequestPath($requestPath);

// 处理静态文件访问（uploads目录）
if (strpos($requestPath, 'uploads/') === 0) {
    $filePath = __DIR__ . '/' . $requestPath;

    if (file_exists($filePath) && is_file($filePath)) {
        // 获取文件MIME类型
        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf'
        ];

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // 设置响应头
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=31536000'); // 缓存1年

        // 输出文件
        readfile($filePath);
        exit;
    } else {
        // 文件不存在
        Response::error('File not found', 404, ['path' => $requestPath]);
    }
}

// API路由映射
$routes = [
    // 管理端API
    'api/auth' => 'api/auth.php',
    'api/users' => 'api/users.php',
    'api/roles' => 'api/roles.php',
    'api/permissions' => 'api/permissions.php',
    'api/settings' => 'api/settings.php',
    'api/developer-settings' => 'api/developer-settings.php',
    'api/logs' => 'api/logs.php',
    'api/application-logs' => 'api/application-logs.php',
    'api/operation-log' => 'api/operation-log.php',
    'api/otp' => 'api/otp.php',

    // Leads Management API
    'api/leads' => 'api/leads.php',
    'api/lead-tags' => 'api/lead-tags.php',
    'api/kyc' => 'api/kyc.php',
    'api/search-tags' => 'api/search-tags.php',

    // KYC Template Management API
    'api/kyc-templates' => 'api/kyc-templates.php',
    'api/kyc-questions' => 'api/kyc-questions.php',
    'api/kyc-categories' => 'api/kyc-categories.php',
    'api/kyc-rules' => 'api/kyc-rules.php',
    'api/kyc-template-documents' => 'api/kyc-template-documents.php',
    'api/kyc-submissions' => 'api/kyc-submissions.php',
    'api/kyc-reviewers' => 'api/kyc-reviewers.php',
    'api/kyc-settings' => 'api/kyc-settings.php',
    'api/external-kyc-gateways' => 'api/external-kyc-gateways.php',
    'api/kyc-webhook' => 'api/kyc-webhook.php',
    'api/withdrawal-templates' => 'api/withdrawal-templates.php',
    'api/withdrawal-submissions' => 'api/withdrawal-submissions.php',
    'api/withdrawal-questions' => 'api/withdrawal-questions.php',
    'api/withdrawal-categories' => 'api/withdrawal-categories.php',
    'api/withdrawal-rules' => 'api/withdrawal-rules.php',
    'api/withdrawal-template-documents' => 'api/withdrawal-template-documents.php',

    // Admin Client Management API
    'api/admin-clients' => 'api/admin-clients.php',
    'api/webmcp' => 'api/webmcp.php',
    'api/admin/trading/leverages' => 'api/admin-trading-platform-leverages.php',
    'api/admin/balance-adjustments' => 'api/admin-balance-adjustments.php',
    'api/admin/trading-account-adjustments' => 'api/admin-trading-account-adjustments.php',
    'api/client-notifications' => 'api/client-notifications.php',
    'api/client-email-templates' => 'api/client-email-templates.php',
    'api/admin/notifications' => 'api/admin-notifications.php',
    'api/client-tickets' => 'api/client-tickets.php',
    'api/email-templates' => 'api/email-templates.php',
    'api/email-template-section-settings' => 'api/email-template-section-settings.php',

    // 客户端门户API
    'api/client/auth' => 'api/client-auth.php',
    'api/client/preview-session' => 'api/client-preview-session.php',
    'api/client/preview-revoke' => 'api/client-preview-revoke.php',
    'api/client/users' => 'api/client-users.php',
    'api/client/ib/dashboard' => 'api/client-ib-dashboard.php',
    'api/client/commission-report' => 'api/client-commission-report.php',
    'api/client/deposit-withdraw-report' => 'api/client-deposit-withdraw-report.php',
    'api/client/ib-statement-report' => 'api/client-ib-statement-report.php',
    'api/client/ib-client-position' => 'api/client-ib-client-position.php',
    'api/client/payment-accounts' => 'api/client-payment-accounts.php',
    'api/trading/platforms' => 'api/trading-platforms.php',
    'api/trading/platform-downloads' => 'api/trading-platform-downloads.php',
    'api/trading/accounts' => 'api/trading-accounts.php',
    'api/login-settings' => 'api/login-settings.php',
    'api/countries' => 'api/countries.php',

    // Funding Management API (存款和提款)
    'api/deposits' => 'api/deposits.php',
    'api/withdrawals' => 'api/withdrawals.php',
    'api/internal-transfers' => 'api/internal-transfers.php',
    'api/deposit-tags' => 'api/deposit-tags.php',
    'api/withdrawal-tags' => 'api/withdrawal-tags.php',
    'api/payment-methods' => 'api/payment-methods.php',
    'api/transaction-settings' => 'api/transaction-settings.php',
    'api/transaction-search-tags' => 'api/transaction-search-tags.php',
    'api/funding-reports' => 'api/funding-reports.php',
    'api/daily-reports' => 'api/daily-reports.php',
    'api/custom-reports' => 'api/custom-reports.php',
    'api/client-wallets' => 'api/client-wallets.php',
    'api/account-verification' => 'api/account-verification.php',
    'api/psp-callback-lookup' => 'api/psp-callback-lookup.php',

    // IB Management API
    'api/ib-partners' => 'api/ib-partners.php',
    'api/ib-applications' => 'api/ib-applications.php',
    'api/ib-rules' => 'api/ib-rules.php',
    'api/ib-tier-levels' => 'api/ib-tier-levels.php',
    'api/ib-settings' => 'api/ib-settings.php',
    'api/ib-invitations' => 'api/ib-invitations.php',
    'api/ib-commission-report' => 'api/ib-commission-report.php',
    'api/ib-statement-report' => 'api/ib-statement-report.php',
    'api/commission-orders' => 'api/commission-orders.php',

    // Menu Settings API (通用菜单设置接口)
    'api/menu-settings' => 'api/menu-settings.php',

    // 品牌配置API（公开接口）
    'api/branding' => 'api/branding.php',

    // Sales 列表与统计（含 special-roles 配置）
    'api/sales' => 'api/sales.php',

    // IB 推荐链接访问记录（公开，不鉴权）
    'api/ib-referral-visit' => 'api/ib-referral-visit.php',

    // PSP callback 接口
    'api/callback' => 'api/payments-processor-interface.php',

    // Vexora public notify/redirect aliases (merchant portal form URLs)
    'success/callback' => 'api/vexora-deposit-public.php',
    'decline/callback' => 'api/vexora-deposit-public.php',
    'success/redirect' => 'api/vexora-deposit-public.php',
    'decline/redirect' => 'api/vexora-deposit-public.php',

    // 测试端点
    'api/test' => 'api/test-endpoint.php'
];

// 路由分发
$matched = false;

foreach ($routes as $prefix => $file) {
    // 仅当前缀匹配且边界正确（完全相等或下一个字符为 '/'）才认为命中，避免 'api/kyc' 误匹配 'api/kyc-templates'
    if (strpos($requestPath, $prefix) === 0) {
        $nextCharIndex = strlen($prefix);
        $isBoundary = strlen($requestPath) === $nextCharIndex || ($requestPath[$nextCharIndex] ?? '') === '/';
        if (!$isBoundary) {
            continue;
        }
        // 提取子路径
        $subPath = substr($requestPath, strlen($prefix));
        $subPath = trim($subPath, '/');
        // Keep full matched path for public PSP routes (e.g. success/callback)
        $_SERVER['CRM_FULL_REQUEST_PATH'] = $requestPath;
        $_GET['path'] = $subPath;
        RequestLogContext::setRoute($prefix);

        // 加载对应的路由文件
        require_once __DIR__ . '/' . $file;
        $matched = true;
        break;
    }
}

// 如果没有匹配到路由
if (!$matched) {
    // API根路径 - 返回API信息
    if ($requestPath === 'api' || $requestPath === 'api/') {
        $logoText = $appConfig['branding']['logoText'] ?? 'CRM';
        Response::success([
            'message' => $logoText . ' API',
            'version' => $appConfig['version'],
            'endpoints' => [
                'admin' => [
                    'auth' => '/api/auth',
                    'users' => '/api/users',
                    'roles' => '/api/roles',
                    'permissions' => '/api/permissions',
                    'settings' => '/api/settings',
                    'logs' => '/api/logs'
                ],
                'leads' => [
                    'leads' => '/api/leads',
                    'lead-tags' => '/api/lead-tags',
                    'kyc' => '/api/kyc',
                    'search-tags' => '/api/search-tags'
                ],
                'kyc_management' => [
                    'templates' => '/api/kyc-templates',
                    'questions' => '/api/kyc-questions',
                    'categories' => '/api/kyc-categories',
                    'rules' => '/api/kyc-rules',
                    'submissions' => '/api/kyc-submissions'
                ],
                'admin_clients' => [
                    'clients' => '/api/admin-clients'
                ],
                'funding' => [
                    'deposits' => '/api/deposits',
                    'withdrawals' => '/api/withdrawals',
                    'payment-methods' => '/api/payment-methods',
                    'transaction-settings' => '/api/transaction-settings',
                    'funding-reports' => '/api/funding-reports',
                    'custom-reports' => '/api/custom-reports',
                    'deposit-tags' => '/api/deposit-tags',
                    'withdrawal-tags' => '/api/withdrawal-tags',
                    'client-wallets' => '/api/client-wallets',
                    'account-verification' => '/api/account-verification'
                ],
                'client' => [
                    'auth' => '/api/client/auth',
                    'users' => '/api/client/users'
                ],
                'ib' => [
                    'partners' => '/api/ib-partners',
                    'applications' => '/api/ib-applications',
                    'rules' => '/api/ib-rules',
                    'tier-levels' => '/api/ib-tier-levels',
                    'settings' => '/api/ib-settings',
                    'invitations' => '/api/ib-invitations'
                ],
                'login_settings' => '/api/login-settings'
            ],
            'documentation' => '/api/docs'
        ]);
    } else {
        require_once __DIR__ . '/utils/Logger.php';
        require_once __DIR__ . '/utils/ApplicationLogDomainResolver.php';
        $postKeys = array_keys($_POST ?? []);
        $getKeys = array_keys($_GET ?? []);
        $getPathValue = trim((string)($_GET['path'] ?? ''));
        $postPathValue = trim((string)($_POST['path'] ?? ''));
        $requestKeys = array_merge($postKeys, $getKeys);
        $looksLikeFlashPay = !empty(array_intersect(
            ['payOrderId', 'mchOrderNo', 'wayCode', 'ifCode', 'mchNo'],
            $requestKeys
        ));
        $looksLikeFivePay = !empty(array_intersect(
            ['MerchantOrderNo', 'DepositMethod', 'WithdrawalId'],
            array_merge($postKeys, $getKeys)
        ));
        Logger::warning('API endpoint not found', [
            'domain' => ApplicationLogDomainResolver::resolveUnmatchedRequest($requestPath, $requestKeys),
            'requestMethod' => $requestMethod,
            'requestUri' => $requestUri,
            'scriptName' => (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            'phpSelf' => (string)($_SERVER['PHP_SELF'] ?? ''),
            'requestPath' => $requestPath,
            'queryString' => (string)($_SERVER['QUERY_STRING'] ?? ''),
            'getPath' => $getPathValue,
            'postPath' => $postPathValue,
            'resolvedRoutePath' => $getPathValue !== '' ? $getPathValue : $postPathValue,
            'getKeys' => $getKeys,
            'postKeys' => $postKeys,
            'contentType' => (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''),
            'userAgent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'remoteAddr' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'looksLikeFlashPay' => $looksLikeFlashPay,
            'looksLikeFivePay' => $looksLikeFivePay,
        ]);
        Response::error('API endpoint not found', 404, [
            'path' => $requestPath,
            'getPath' => $getPathValue,
            'postPath' => $postPathValue,
        ]);
    }
}
