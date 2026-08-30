<?php
/**
 * 邮件配置文件
 * Email Configuration
 */

require_once __DIR__ . '/env.php';

// 加载应用配置以获取品牌信息
$appConfig = require __DIR__ . '/app.php';

return [
    // 邮件发送方式: smtp, mail, sendgrid, aws_ses, mailgun, azure
    'driver' => config_env('MAIL_DRIVER', 'azure'),

    // SMTP 服务器配置
    'smtp' => [
        'host' => config_env('SMTP_HOST', ''),
        'port' => config_env_int('SMTP_PORT', 587),
        'username' => config_env('SMTP_USER', ''),
        'password' => config_env('SMTP_PASS', ''),
        'encryption' => config_env('SMTP_ENCRYPTION', 'tls'),
        'timeout' => config_env_int('SMTP_TIMEOUT', 30),
    ],

    // 发件人信息（Gmail会自动使用SMTP username）
    'from' => [
        'address' => config_env('SMTP_FROM_ADDRESS', ''),
        'name' => ($appConfig['branding']['companyName'] ?? $appConfig['logoname'] ?? 'Trading Platform')
    ],

    // 邮件模板路径
    'template_path' => __DIR__ . '/../templates/emails/',

    // 调试模式（true=只记录日志不实际发送，false=实际发送邮件）
    // 如果需要测试但不想发送真实邮件，可手动改为 true
    'debug_mode' => false,

    /**
     * 第三方邮件服务配置（可选）
     * 如果不想使用SMTP，可以选择以下服务之一
     */

    // SendGrid配置
    'sendgrid' => [
        'api_key' => ''  // SendGrid API Key
    ],

    // AWS SES配置
    'aws_ses' => [
        'key' => '',      // AWS Access Key
        'secret' => '',   // AWS Secret Key
        'region' => 'us-east-1'
    ],

    // Mailgun配置
    'mailgun' => [
        'domain' => '',   // Mailgun域名
        'api_key' => ''   // Mailgun API Key
    ],

    // Azure Graph 配置
    'azure' => [
        'tenant_id' => config_env('AZURE_TENANT_ID', ''),
        'client_id' => config_env('AZURE_CLIENT_ID', ''),
        'client_secret' => config_env('AZURE_CLIENT_SECRET', ''),
        'sender' => config_env('AZURE_SENDER', ''),
        'scope' => config_env('AZURE_SCOPE', 'https://graph.microsoft.com/.default'),
        'base_url' => config_env('AZURE_BASE_URL', 'https://graph.microsoft.com/v1.0'),
        'timeout' => config_env_int('AZURE_TIMEOUT', 30)
    ],
];
