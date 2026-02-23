<?php
/**
 * 反向代理入口文件
 * 所有请求都通过此文件进入
 */

// 错误报告设置（生产环境请关闭显示）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 定义项目根目录
define('ROOT_PATH', __DIR__);

// 自动加载（如果使用Composer）
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// 加载必要的类文件
require_once ROOT_PATH . '/ReverseProxy.php';
require_once ROOT_PATH . '/Mailer.php';
require_once ROOT_PATH . '/ApiHandler.php'; // 新增API处理类

/**
 * 简单的路由处理
 * 可以根据请求路径决定是否显示状态页面或API
 */
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// 判断是否访问API
if (strpos($path, '/api/') === 0) {
    require_once ROOT_PATH . '/api.php';
    exit;
}

// 判断是否访问状态页面
if ($path === '/status' || $path === '/status.php') {
    require_once ROOT_PATH . '/status.php';
    exit;
}

// 判断是否访问状态页面
if ($path === '/docs' || $path === '/docs.php') {
    require_once ROOT_PATH . '/apidocs.php';
    exit;
}

/**
 * 主代理逻辑
 */
try {
    // 记录请求开始
    $startTime = microtime(true);
    
    // 创建代理实例
    $proxy = new ReverseProxy();
    
    // 运行代理
    $proxy->run();
    
    // 记录请求结束
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);
    
    // 确保日志目录存在
    $logDir = ROOT_PATH . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/access.log';
    
    // 格式化日志行 - 添加换行符 \n
    $logLine = sprintf("[ACCESS] %s %s %s %s %.2fms\n", 
        date('Y-m-d H:i:s'),
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'] ?: '/',
        $_SERVER['REMOTE_ADDR'],
        $duration
    );
    
    // 使用 file_put_contents 确保换行
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    // 捕获所有未处理的异常
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    
    $errorResponse = [
        'error' => 'Internal Server Error',
        'message' => '系统内部错误，请稍后重试',
        'request_id' => uniqid('err_', true),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // 生产环境不要暴露具体错误信息
    if (ini_get('display_errors')) {
        $errorResponse['debug'] = $e->getMessage();
    }
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // 确保错误日志目录存在
    $errorLogDir = ROOT_PATH . '/logs';
    if (!is_dir($errorLogDir)) {
        mkdir($errorLogDir, 0755, true);
    }
    
    // 记录错误日志（带换行）
    error_log("[" . date('Y-m-d H:i:s') . "] [FATAL] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n", 3, $errorLogDir . '/error.log');
}