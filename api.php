<?php
/**
 * API入口文件
 */

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 设置时区
$config = include 'config.php';
date_default_timezone_set($config['system']['timezone'] ?? 'Asia/Shanghai');

// 加载必要的类
require_once __DIR__ . '/ApiHandler.php';

try {
    $apiHandler = new ApiHandler($config);
    $apiHandler->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'API内部错误',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}