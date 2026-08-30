<?php
/**
 * 应用配置文件
 * ENVIRONMENT must be dev, staging, or production.
 */

require_once __DIR__ . '/env.php';

$environment = config_env('ENVIRONMENT', 'dev');

return [
    // 应用信息
    'logoname' => 'Avenlo',
    'name' => 'Avenlo API',
    'version' => '1.0.0',
    'env' => $environment,

    // 品牌配置（用于替换所有用户可见的品牌文字）
    'branding' => [
        'logoText' => 'Avenlo',           // Logo文字（短名称）
        'companyName' => 'Avenlo',  // 公司全名
        'companyShortName' => 'Avenlo',       // 公司简称
        'teamName' => 'The Avenlo Team',      // 团队名称（用于邮件签名等）
        'copyrightText' => 'Avenlo',  // 版权信息中的公司名
        'supportEmail' => 'crm.support@finance-pro.com.au',  // 支持邮箱（用于前端显示联系支持）
        'supportPhone' => '+1 (555) 123-4567'  // 支持电话（用于前端显示联系支持）
    ],

    // 前端URL配置（用于生成邮件验证链接等）
    // 部署时请根据实际域名修改以下配置
    'client_frontend_url' => config_env('CLIENT_FRONTEND_URL', ''),
    'admin_frontend_url' => config_env('ADMIN_FRONTEND_URL', ''),

    // 通用交易结果回跳配置
    'transaction_callbacks' => [
        'deposit' => [
            'success' => '/#/client/transactions/success?type={type}&id={id}&amount={amount}&fee={fee}&total={total}&currency={currency}&exchangeRate={exchangeRate}&method={method}',
            'fail' => '/#/client/transactions/fail?type={type}&id={id}&amount={amount}&fee={fee}&total={total}&currency={currency}&exchangeRate={exchangeRate}&method={method}',
            'pending' => '/#/client/transactions/pending?type={type}&id={id}&amount={amount}&fee={fee}&total={total}&currency={currency}&exchangeRate={exchangeRate}&method={method}',
        ],
    ],


    // 文件下载基础URL（用于生成文件下载链接）
    'file_base_url' => config_env('FILE_BASE_URL', ''),

    // JWT 配置
    'jwt' => [
        'secret' => config_env('JWT_SECRET', ''),
        'algorithm' => 'HS256',
        'expire' => 7200,  // 2小时（秒）
        'refresh_expire' => 604800,  // 7天（秒）
        'issuer' => 'utrada-crm',
        'audience' => 'utrada-crm-api'
    ],

    // 跨域配置
    'cors' => [
        'allow_origin' => config_env('CORS_ALLOW_ORIGIN', ''),
        'allow_methods' => config_env('CORS_ALLOW_METHODS', ''),
        'allow_headers' => config_env('CORS_ALLOW_HEADERS', ''),
        'allow_credentials' => config_env_bool('CORS_ALLOW_CREDENTIALS', false)
    ],

    // 安全配置
    'security' => [
        'encryption_iv_seed' => 'CRM', // Stable legacy seed; branding changes must not invalidate ciphertext.
        'password_min_length' => 6,
        'max_login_attempts' => 5,   // 每日允许的最大错误登录次数，超限当日不可再试（次日 0 点重置）
        'lockout_duration' => 30,  // 分钟（仅用于后台手动锁定说明，自动锁定已改为每日限制）
        'session_timeout' => 120,  // 分钟
        'password_reset_expiry' => 60,  // 分钟
        'check_duplicate_phone' => false
    ],

    'exchange_rate_sync' => [
        'platform' => 'mt5',
    ],

    // 分页配置
    'pagination' => [
        'default_per_page' => 10,
        'max_per_page' => 100
    ],

    // 时区
//    'timezone' => 'Asia/Shanghai',

    // 在 return 数组中添加
    'aws' => [
        's3' => [
            'key' => config_env('AWS_S3_KEY', ''),
            'secret' => config_env('AWS_S3_SECRET', ''),
            'region' => config_env('AWS_S3_REGION', ''),
            'bucket' => config_env('AWS_S3_BUCKET', ''),
        ],
        'filepath' => config_env('AWS_S3_FILEPATH', '')
    ],

    // 交易平台配置
    'trading_platforms' => [
        'mt4' => true,      // MT4平台是否已对接
        'mt5' => true,      // MT5平台是否已对接
        'financepro' => false // FinancePro平台是否已对接
    ],

    // 每个平台允许的活跃交易账户数量上限
    'trading_account_limits' => [
        'mt4' => 1,
        'mt5' => 3,
        'financepro' => 1
    ],

    // 平台开户时是否要求前端显式传入密码
    'trading_account_password_required' => [
        'mt4' => false,
        'mt5' => true,
        'financepro' => false
    ],

    // 第三方接口
    'integrations' => [
        'mt4' => [
            // MT4 WebApi Gateway 配置（HMAC 签名，详见 utils/Mt4ApiClient.php）
            'base_url' => config_env('MT4_BASE_URL', ''),
            'hmac_secret' => config_env('MT4_HMAC_SECRET', ''),
            'server_name' => config_env('MT4_SERVER_NAME', ''),
            'connect_timeout' => 10,
            'timeout' => 240,
            'default_leverage' => 100,
            // MT4 历史同步时间窗口修正（分钟），含义参考下面 mt5
            'server_time_offset_minutes' => 400,
            'history_overlap_minutes' => 180,
            'download' => false,
            'snapshot_batch_size' => 100,
            'commission_cutoff_ts' => config_env_int('MT4_COMMISSION_CUTOFF_TS', 0),
            'url' => config_env('MT4_URL', ''),
        ],
        'mt5' => [
            // MT5 Manager WebAPI 连接配置（示例占位，按实际环境填写）
            'host' => config_env('MT5_HOST', ''),
            'port' => config_env_int('MT5_PORT', 443),
            'timeout' => 10,
            'manager_login' => config_env('MT5_MANAGER_LOGIN', ''),
            'manager_password' => config_env('MT5_MANAGER_PASSWORD', ''),
            'agent' => config_env('MT5_AGENT', 'CRM-MT5'),
            'is_crypt' => config_env_bool('MT5_IS_CRYPT', true),
            'log_path' => config_env('MT5_LOG_PATH', '/tmp/'),
            // MT5 WebApi Gateway 配置（只读：订单 / 余额 / 赠金 / group / symbol 同步走这里，HMAC 签名，详见 utils/Mt5GatewayApiClient.php）
            'base_url' => config_env('MT5_BASE_URL', ''),
            'hmac_secret' => config_env('MT5_HMAC_SECRET', ''),
            'connect_timeout' => 10,
            'gateway_timeout' => 60,    // 网关 HTTP 读超时（独立于上面 WebAPI 的 timeout）
            // group / symbol 同步是否继续走 WebAPI（二进制 Mt5ApiClient）。默认 false 走网关；
            // 置 true 时这两类同步回退到 WebAPI（订单 / 余额读仍始终走网关， 不受此开关影响）。
            'group_symbol_sync_use_webapi' => config_env_bool('MT5_GROUP_SYMBOL_SYNC_USE_WEBAPI', false),
            // MT5 历史同步时间窗口修正（分钟）
            // server_time_offset_minutes: MT5 服务器相对当前应用机器的时间偏 移（快为正，慢为负）
            // history_overlap_minutes: 每次历史拉取向前重叠回看，避免边界漏单（依赖 upsert 幂等）
            'server_time_offset_minutes' => 400,
            'snapshot_batch_size' => 100,
            'history_overlap_minutes' => 180,
            'download' => config_env_bool('MT5_DOWNLOAD', true),
            'commission_cutoff_ts' => config_env_int('MT5_COMMISSION_CUTOFF_TS', 0),
            'url' => config_env('MT5_URL', ''),
        ],
        'finance_pro' => [
            'trading_pwd' => config_env('FINANCEPRO_TRADING_PASSWORD', ''),
            'app_id' => config_env('FINANCEPRO_APP_ID', ''),
            'app_secret' => config_env('FINANCEPRO_APP_SECRET', ''),
            'platform' => config_env('FINANCEPRO_PLATFORM', 'pcweb'),
            'token_url' => config_env('FINANCEPRO_TOKEN_URL', ''),
            'base_url' => config_env('FINANCEPRO_BASE_URL', ''),
            // Token 获取凭据
            'token_credentials' => [
                'grant_type' => config_env('FINANCEPRO_GRANT_TYPE', 'password'),
                'client_id' => config_env('FINANCEPRO_CLIENT_ID', ''),
                'client_secret' => config_env('FINANCEPRO_CLIENT_SECRET', ''),
                'username' => config_env('FINANCEPRO_USERNAME', ''),
                'password' => config_env('FINANCEPRO_PASSWORD', '')
            ],
            // API 接口端点
            'openaccount' => '/CRMManagerService/api/Account/OpenAccount',
            'get_client_summary' => '/CRMManagerService/api/Finance/GetClientSummary',
            'history_order' => '/CRMManagerService/api/Order/HistoryOrder',
            'portfolio_positions' => '/CRMManagerService/api/Order/PortfolioPositionsInvalidate',
            'pending_orders' => '/CRMManagerService/api/Order/PendingOrders',
            'get_account_info' => '/CRMManagerService/api/Account/GetAccountInfo',
            'balance' => '/CRMManagerService/api/Finance/Balance',
            'client_balance_and_trade' => '/CRMManagerService/api/Order/GetClientBalanceAndTrade',
            'get_all_groups' => '/CRMManagerService/api/Group/GetAllGroups',
            'edit_account' => '/CRMManagerService/api/Account/EditAccount',
            'get_security_symbol' => '/CRMManagerService/api/Symbol/SyncSecuritiesAndSymbols',
            'get_credit_history' => '/CRMManagerService/api/Finance/GetCreditHistory',
           // RelTrix 迁移交接时间线 T：MT4 平仓时间早于该时间戳的订单不生成返佣（对方已结算）。
            // 0 = 不启用。切换上线时直接填 unix 时间戳，与 MT4 CloseTime 同口径，不做时区换算。
            'commission_cutoff_ts' => config_env_int('FINANCEPRO_COMMISSION_CUTOFF_TS', 0),
            'download' => config_env_bool('FINANCEPRO_DOWNLOAD', false),
            'url' => config_env('FINANCEPRO_URL', ''),
        ]
    ],

    // Token存储配置
    'token_storage' => [
        'path' => __DIR__ . '/../storage/tokens/',
        'filename' => 'crm_token.json'
    ],

    // 对称加密配置（用于存储可还原的敏感信息）
    'encryption' => [
        'key' => config_env('ENCRYPTION_KEY', '')
    ],

    // 特殊角色 ID（Sales Manager / Sales）：不可删除，仅可编辑权限；Client List、Leads 等按 Sales 角色做数据范围过滤时使用
    'special_roles' => [
        'sales_manager_role_id' => 5,
        'sales_role_id' => 6,
    ],

    // 外部 App 签名验证配置（用于 App 端接口鉴权）
    'external_app' => [
        'app_id' => 'system',
        'app_secret' => config_env('EXTERNAL_APP_SECRET', ''),
        'timestamp_tolerance' => 300,  // 签名时间戳容差（秒），默认5分钟
    ],

    // 外部API IP白名单配置
    'external_api' => [
        'ip_whitelist' => config_env_list('EXTERNAL_API_IP_WHITELIST'),
        'ibeepay_whitelist_ip_array' => config_env_list('IBEEPAY_WHITELIST_IPS'),
        'paymentasia_whitelist_ip_array' => config_env_list('PAYMENTASIA_WHITELIST_IPS'),
        'coinsbuy_whitelist_ip_array' => config_env_list('COINSBUY_WHITELIST_IPS'),
        'vexora_whitelist_ip_array' => config_env_list('VEXORA_WHITELIST_IPS'),
        'flashpay_whitelist_ip_array' => config_env_list('FLASHPAY_WHITELIST_IPS'),
        'fivepay_whitelist_ip_array' => config_env_list('FIVEPAY_WHITELIST_IPS'),
        'cvpay_whitelist_ip_array' => config_env_list('CVPAY_WHITELIST_IPS'),
    ]
];
