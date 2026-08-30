<?php
/**
 * 邮件配置文件示例
 * 复制此文件为 email.php 并填写您的实际配置
 */

require_once __DIR__ . '/env.php';

return [
    // 邮件发送方式: smtp, mail, sendgrid, aws_ses, mailgun, azure
    'driver' => config_env('MAIL_DRIVER', 'smtp'),

    // SMTP 服务器配置
    'smtp' => [
        'host' => config_env('SMTP_HOST', ''),
        'port' => config_env_int('SMTP_PORT', 587),
        'username' => config_env('SMTP_USER', ''),
        'password' => config_env('SMTP_PASS', ''),
        'encryption' => config_env('SMTP_ENCRYPTION', 'tls'),
        'timeout' => config_env_int('SMTP_TIMEOUT', 30),
    ],

    // 发件人信息（Gmail会自动使用SMTP username作为发件人）
    'from' => [
        'address' => config_env('SMTP_FROM_ADDRESS', ''),
        'name' => 'BDX Trading Platform'
    ],

    // 邮件模板路径
    'template_path' => __DIR__ . '/../templates/emails/',

    // 调试模式
    // true = 不实际发送邮件，只记录日志（用于测试）
    // false = 实际发送邮件
    'debug_mode' => false,

    // 第三方邮件服务配置
    'sendgrid' => [
        'api_key' => ''
    ],

    'aws_ses' => [
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1'
    ],

    'mailgun' => [
        'domain' => '',
        'api_key' => ''
    ],

    'azure' => [
        'tenant_id' => '',
        'client_id' => '',
        'client_secret' => '',
        'sender' => '', // 留空时默认使用 from.address
        'scope' => 'https://graph.microsoft.com/.default',
        'base_url' => 'https://graph.microsoft.com/v1.0',
        'timeout' => 30
    ]
];
