<?php
/**
 * 品牌配置API
 * 返回应用品牌配置信息（公开接口，无需认证）
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../utils/Response.php';

$appConfig = require __DIR__ . '/../config/app.php';

// 生成配置版本号（基于配置内容的hash，确保配置变化时版本号也会变化）
$brandingConfig = [
    'logoText' => $appConfig['branding']['logoText'] ?? $appConfig['logoname'] ?? 'CRM',
    'companyName' => $appConfig['branding']['companyName'] ?? 'Trading Platform',
    'companyShortName' => $appConfig['branding']['companyShortName'] ?? 'Platform',
    'teamName' => $appConfig['branding']['teamName'] ?? 'The Team',
    'copyrightText' => $appConfig['branding']['copyrightText'] ?? 'Trading Platform',
    'supportEmail' => $appConfig['branding']['supportEmail'] ?? 'support.demo@gmail.com',
    'supportPhone' => $appConfig['branding']['supportPhone'] ?? '+1 (555) 123-4567'
];

// 生成版本号（使用配置内容的hash）
$version = md5(json_encode($brandingConfig));

// 返回品牌配置和版本号
// 注意：不能使用 ...$brandingConfig，因为 PHP 的 spread operator 不支持关联数组
$responseData = array_merge($brandingConfig, [
    '_version' => $version  // 版本号，用于前端缓存验证
]);

Response::success($responseData);
