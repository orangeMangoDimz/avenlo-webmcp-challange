<?php
/**
 * 简单测试端点
 * 用于验证API路由是否工作
 */

header('Content-Type: application/json; charset=utf-8');
//header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Test endpoint is working!',
    'path' => $_GET['path'] ?? 'No path',
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => time()
], JSON_PRETTY_PRINT);
