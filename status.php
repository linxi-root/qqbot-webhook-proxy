<?php
/**
 * 增强版健康检查状态查看页面
 */

// 加载配置
$config = include 'config.php';

// 设置时区
date_default_timezone_set($config['system']['timezone'] ?? 'Asia/Shanghai');

// 定义文件路径
$healthFile = $config['health_check_file'] ?? __DIR__ . '/data/health_check.json';
$metricsFile = $config['monitoring']['metrics_file'] ?? __DIR__ . '/data/metrics.json';
$logFile = $config['log_file'] ?? __DIR__ . '/logs/proxy.log';
$logDir = dirname($logFile);

// ==================== 登录校验 ====================
session_start();

// 从配置文件获取管理员信息
$valid_username = $config['admin']['username'] ?? 'admin';
$valid_password = $config['admin']['password'] ?? 'admin123';

// 密码修改功能
$password_change_enabled = $config['admin']['enable_password_change'] ?? true;
$password_message = '';

// 处理密码修改请求
if ($password_change_enabled && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 验证原密码
        if ($old_password !== $valid_password) {
            $password_message = '原密码错误';
        } elseif (strlen($new_password) < 6) {
            $password_message = '新密码长度不能少于6位';
        } elseif ($new_password !== $confirm_password) {
            $password_message = '两次输入的新密码不一致';
        } else {
            // 更新配置文件中的密码
            $config_content = file_get_contents(__DIR__ . '/config.php');
            
            $admin_pattern = "/(['\"])admin(['\"])\s*=>\s*\[\s*['\"]username['\"]\s*=>\s*['\"][^'\"]*['\"]\s*,\s*['\"]password['\"]\s*=>\s*)(['\"])([^'\"]*)(['\"])/";
            $admin_replacement = "$1$2$3$4" . $new_password . "$5";
            $new_config_content = preg_replace($admin_pattern, $admin_replacement, $config_content, 1);
            
            if ($new_config_content && file_put_contents(__DIR__ . '/config.php', $new_config_content)) {
                $password_message = '密码修改成功，请重新登录';
                $_SESSION = array();
                session_destroy();
                header('Location: status.php');
                exit;
            } else {
                $password_message = '密码修改失败，请检查文件权限';
            }
        }
    }
}

// 处理登出
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: status.php');
    exit;
}

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['username'] = $username;
        $_SESSION['lifetime'] = $config['admin']['session_lifetime'] ?? 3600;
        header('Location: status.php');
        exit;
    } else {
        $login_error = '用户名或密码错误';
    }
}

// 检查会话是否过期
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $lifetime = $_SESSION['lifetime'] ?? 3600;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $lifetime)) {
        $_SESSION = array();
        session_destroy();
        header('Location: status.php');
        exit;
    }
}

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_data') {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => '未登录', 'login_required' => true]);
        exit;
    }
    
    // 显示登录页面（代码保持不变）
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>登录 - 反向代理监控系统</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Microsoft YaHei', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header h1 {
                color: #333;
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .login-header h1:before {
                content: '🛡️';
                margin-right: 10px;
            }
            
            .login-header p {
                color: #666;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #555;
                font-size: 14px;
                font-weight: bold;
            }
            
            .form-group input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 5px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .login-btn {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: transform 0.3s;
            }
            
            .login-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }
            
            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 14px;
            }
            
            .footer {
                margin-top: 20px;
                text-align: center;
                color: #999;
                font-size: 12px;
            }
            
            .footer a {
                color: #667eea;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1>反向代理监控系统</h1>
                <p>请输入登录信息</p>
            </div>
            
            <?php if (isset($login_error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="login-btn">登录</button>
            </form>
            
            <div class="footer">
                <p>监控系统 <?php echo $config['system']['version'] ?? '2.0.0'; ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 处理维护模式切换
if (isset($_GET['action']) && $_GET['action'] === 'toggle_maintenance') {
    // 检查文件是否可写
    $configFile = __DIR__ . '/config.php';
    
    if (!is_writable($configFile)) {
        $message = '维护模式切换失败：配置文件不可写，请检查文件权限';
        header('Location: status.php?message=' . urlencode($message));
        exit;
    }
    
    $config_content = file_get_contents($configFile);
    
    // 查找并替换 maintenance_mode 的值
    $new_value = $config['system']['maintenance_mode'] ? 'false' : 'true';
    
    // 使用更精确的正则表达式
    $pattern = "/(['\"]system['\"]\s*=>\s*\[\s*.*?['\"]maintenance_mode['\"]\s*=>\s*)(true|false)/s";
    $replacement = "$1" . $new_value;
    
    $new_config_content = preg_replace($pattern, $replacement, $config_content, 1);
    
    if ($new_config_content && $new_config_content !== $config_content) {
        if (file_put_contents($configFile, $new_config_content)) {
            $message = '维护模式已' . ($new_value === 'true' ? '开启' : '关闭');
        } else {
            $message = '维护模式切换失败：无法写入配置文件';
        }
    } else {
        $message = '维护模式切换失败：配置文件未更新';
    }
    
    header('Location: status.php?message=' . urlencode($message));
    exit;
}

// 检查是否是AJAX请求
$ajax = $_GET['ajax'] ?? '';
if ($ajax === 'get_data') {
    header('Content-Type: application/json');
    
    $logDate = $_GET['log_date'] ?? date('Y-m-d');
    $logType = $_GET['log_type'] ?? 'current';
    $errorDays = isset($_GET['error_days']) ? min(7, max(1, intval($_GET['error_days']))) : 7;
    
    // 加载最新数据
    $healthData = file_exists($healthFile) ? json_decode(file_get_contents($healthFile), true) ?: [] : [];
    $metricsData = file_exists($metricsFile) ? json_decode(file_get_contents($metricsFile), true) ?: [] : [];
    
    // 计算统计信息
    $totalFails = 0;
    $healthyCount = 0;
    $warningCount = 0;
    $failedCount = 0;
    $totalServices = count($config['targets'] ?? []);
    
    foreach ($healthData as $id => $health) {
        $fails = $health['fails'] ?? 0;
        $totalFails += $fails;
        $threshold = $config['fail_threshold'] ?? 10;
        
        if ($fails == 0) {
            $healthyCount++;
        } elseif ($fails < $threshold) {
            $warningCount++;
        } else {
            $failedCount++;
        }
    }
    
    // 构建服务列表数据
    $services = [];
    foreach (($config['targets'] ?? []) as $id => $target) {
        $health = $healthData[$id] ?? ['fails' => 0, 'last_check_time' => null, 'last_fail_time' => null, 'notified' => false];
        $fails = $health['fails'] ?? 0;
        $threshold = $config['fail_threshold'] ?? 10;
        $percentage = min(100, round($fails / $threshold * 100));
        
        if ($fails == 0) {
            $statusClass = 'status-healthy';
            $statusText = '健康';
            $progressClass = '';
        } elseif ($fails < $threshold) {
            $statusClass = 'status-warning';
            $statusText = '警告';
            $progressClass = 'warning';
        } else {
            $statusClass = 'status-unhealthy';
            $statusText = '失效';
            $progressClass = 'danger';
        }
        
        $metrics = $metricsData[$id] ?? null;
        
        $services[] = [
            'id' => $id,
            'name' => $target['name'] ?? $id,
            'description' => $target['description'] ?? '-',
            'url' => $target['url'] ?? '-',
            'fails' => $fails,
            'threshold' => $threshold,
            'percentage' => $percentage,
            'status_class' => $statusClass,
            'status_text' => $statusText,
            'progress_class' => $progressClass,
            'last_check' => isset($health['last_check_time']) ? date('H:i:s', $health['last_check_time']) : '-',
            'last_fail' => isset($health['last_fail_time']) ? date('H:i:s', $health['last_fail_time']) : '-',
            'notified' => !empty($health['notified']),
            'avg_response_time' => $metrics ? round(($metrics['avg_response_time'] ?? 0) * 1000, 2) . 'ms' : '-'
        ];
    }
    
    // 构建性能指标数据
    $metricsList = [];
    foreach ($metricsData as $id => $m) {
        $total = $m['total_requests'] ?? 0;
        $success = $m['success_count'] ?? 0;
        $fail = $m['fail_count'] ?? 0;
        $successRate = $total > 0 ? round($success / $total * 100, 2) : 0;
        
        $metricsList[] = [
            'id' => $id,
            'total' => number_format($total),
            'success' => number_format($success),
            'fail' => number_format($fail),
            'success_rate' => $successRate,
            'success_rate_class' => $successRate > 95 ? 'status-healthy' : ($successRate > 80 ? 'status-warning' : 'status-unhealthy'),
            'avg_time' => isset($m['avg_response_time']) ? round($m['avg_response_time'] * 1000, 2) . 'ms' : '-',
            'last_time' => isset($m['last_time']) ? date('H:i:s', $m['last_time']) : '-'
        ];
    }
    
    // 获取日志文件列表
    $logFiles = getLogFiles($logDir);
    
    // 获取日志内容
    if ($logType === 'error') {
        // 错误日志：从指定天数的日志文件中获取
        $recentErrors = getRecentErrorsFromAllLogs($logDir, $errorDays, 200);
        $fullLogs = $recentErrors;
        $errorCount = count($recentErrors);
    } else {
        // 当前日志：根据选择的日期获取
        $targetLogFile = $logDir . '/' . $logDate . '.log';
        $fullLogs = getLogContent($targetLogFile, 200);
        $errorCount = 0;
    }
    
    // 返回JSON数据
    echo json_encode([
        'stats' => [
            'healthy' => $healthyCount,
            'warning' => $warningCount,
            'failed' => $failedCount,
            'total_fails' => $totalFails,
            'total_services' => $totalServices
        ],
        'services' => $services,
        'metrics' => $metricsList,
        'logs' => $fullLogs,
        'log_files' => $logFiles,
        'current_log_date' => $logDate,
        'maintenance_mode' => $config['system']['maintenance_mode'] ?? false,
        'chart_data' => [$healthyCount, $warningCount, $failedCount],
        'last_update' => date('H:i:s')
    ]);
    exit;
}

// 开始输出缓冲
ob_start();

// 加载数据
$healthData = [];
if (file_exists($healthFile)) {
    $content = file_get_contents($healthFile);
    $healthData = json_decode($content, true) ?: [];
}

$metricsData = [];
if (file_exists($metricsFile)) {
    $content = file_get_contents($metricsFile);
    $metricsData = json_decode($content, true) ?: [];
}

// 处理操作请求
$action = $_GET['action'] ?? '';
$targetId = $_GET['id'] ?? '';
$message = $_GET['message'] ?? '';

try {
    if ($action === 'reset_fails' && $targetId && isset($healthData[$targetId])) {
        $healthData[$targetId]['fails'] = 0;
        $healthData[$targetId]['notified'] = false;
        $healthData[$targetId]['recovery_notified'] = false;
        file_put_contents($healthFile, json_encode($healthData, JSON_PRETTY_PRINT));
        $message = '重置成功';
        header('Location: status.php?message=' . urlencode($message));
        exit;
    }

    if ($action === 'send_report') {
        try {
            require_once __DIR__ . '/Mailer.php';
            
            if (empty($config['email']['enabled']) || !$config['email']['enabled']) {
                $message = '邮件功能未启用';
            } else {
                $reportData = generateReportData($config, $healthData, $metricsData, $logDir);
                $mailer = new Mailer($config['email'], $logFile);
                error_log("[" . date('Y-m-d H:i:s') . "] [INFO] 尝试发送报告邮件" . PHP_EOL, 3, $logFile);
                
                if ($mailer->sendReport($reportData)) {
                    $message = '✅ 报告已成功发送';
                    error_log("[" . date('Y-m-d H:i:s') . "] [INFO] 报告邮件发送成功" . PHP_EOL, 3, $logFile);
                } else {
                    $message = '❌ 报告发送失败，请检查邮件配置';
                    error_log("[" . date('Y-m-d H:i:s') . "] [ERROR] 报告邮件发送失败" . PHP_EOL, 3, $logFile);
                }
            }
        } catch (Exception $e) {
            $message = '❌ 发送报告时发生错误: ' . $e->getMessage();
            error_log("[" . date('Y-m-d H:i:s') . "] [ERROR] 发送报告异常: " . $e->getMessage() . PHP_EOL, 3, $logFile);
        }
        
        header('Location: status.php?message=' . urlencode($message));
        exit;
    }
    
    if ($action === 'download_logs') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $logFile = $logDir . '/' . $date . '.log';
        $lines = isset($_GET['lines']) ? min(1000, intval($_GET['lines'])) : 200;
        $content = file_exists($logFile) ? tailFile($logFile, $lines) : "日志文件不存在";
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="proxy_log_' . $date . '.txt"');
        echo $content;
        exit;
    }
} catch (Exception $e) {
    $message = '操作失败: ' . $e->getMessage();
}

/**
 * 获取日志文件列表
 */
function getLogFiles($logDir, $days = 30) {
    $files = [];
    if (is_dir($logDir)) {
        $handle = opendir($logDir);
        while (false !== ($file = readdir($handle))) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $files[] = $matches[1];
            }
        }
        closedir($handle);
        rsort($files);
    }
    return array_slice($files, 0, $days);
}

/**
 * 从多个日志文件中获取最近错误日志
 */
function getRecentErrorsFromAllLogs($logDir, $days = 7, $limit = 200) {
    $allErrors = [];
    $dates = [];
    
    for ($i = 0; $i < $days; $i++) {
        $dates[] = date('Y-m-d', strtotime("-$i days"));
    }
    
    foreach ($dates as $date) {
        $logFile = $logDir . '/' . $date . '.log';
        if (!file_exists($logFile)) continue;
        
        $logs = tailFile($logFile, 200);
        $lines = explode("\n", $logs);
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches)) {
                $level = $matches[2];
                if ($level === 'ERROR' || $level === 'WARN') {
                    $allErrors[] = [
                        'time' => $matches[1],
                        'level' => $level,
                        'message' => $matches[3],
                        'date' => $date,
                        'level_class' => $level === 'ERROR' ? 'status-unhealthy' : 'status-warning'
                    ];
                }
            }
        }
    }
    
    usort($allErrors, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    return array_slice($allErrors, 0, $limit);
}

/**
 * 优化的文件末尾读取函数
 */
function tailFile($filepath, $lines = 100) {
    if (!file_exists($filepath)) {
        return '';
    }
    
    if (filesize($filepath) < 1024 * 1024) {
        $content = file_get_contents($filepath);
        $allLines = explode("\n", trim($content));
        $totalLines = count($allLines);
        $startLine = max(0, $totalLines - $lines);
        return implode("\n", array_slice($allLines, $startLine)) . "\n";
    }
    
    $handle = fopen($filepath, "r");
    if (!$handle) {
        return '';
    }
    
    $linecounter = $lines;
    $pos = -2;
    $beginning = false;
    $text = [];
    
    while ($linecounter > 0) {
        $t = " ";
        while ($t != "\n") {
            if (fseek($handle, $pos, SEEK_END) == -1) {
                $beginning = true;
                break;
            }
            $t = fgetc($handle);
            $pos--;
        }
        $linecounter--;
        if ($beginning) {
            rewind($handle);
        }
        $text[$lines - $linecounter - 1] = fgets($handle);
        if ($beginning) break;
    }
    fclose($handle);
    
    return implode("", array_reverse($text));
}

/**
 * 获取日志内容
 */
function getLogContent($logFile, $lines = 200) {
    if (!file_exists($logFile)) {
        return [];
    }
    
    $content = tailFile($logFile, $lines);
    $lines = explode("\n", trim($content));
    $logs = [];
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches)) {
            $logs[] = [
                'time' => $matches[1],
                'level' => $matches[2],
                'message' => $matches[3],
                'level_class' => $matches[2] === 'ERROR' ? 'status-unhealthy' : 
                                ($matches[2] === 'WARN' ? 'status-warning' : 'status-healthy')
            ];
        } else {
            $logs[] = [
                'time' => '',
                'level' => 'INFO',
                'message' => $line,
                'level_class' => 'status-healthy'
            ];
        }
    }
    
    return array_reverse($logs);
}

/**
 * 生成报告数据
 */
function generateReportData($config, $healthData, $metricsData, $logDir) {
    $totalServices = count($config['targets'] ?? []);
    $healthyServices = 0;
    $warningServices = 0;
    $unhealthyServices = 0;
    
    $services = [];
    foreach (($config['targets'] ?? []) as $id => $target) {
        $health = $healthData[$id] ?? ['fails' => 0, 'last_check_time' => null];
        $fails = $health['fails'] ?? 0;
        $threshold = $config['fail_threshold'] ?? 10;
        
        if ($fails == 0) {
            $statusText = '健康';
            $statusClass = 'healthy';
            $healthyServices++;
        } elseif ($fails < $threshold) {
            $statusText = '警告 (' . $fails . '/' . $threshold . ')';
            $statusClass = 'warning';
            $warningServices++;
        } else {
            $statusText = '失效';
            $statusClass = 'unhealthy';
            $unhealthyServices++;
        }
        
        $services[] = [
            'id' => $id,
            'name' => $target['name'] ?? $id,
            'description' => $target['description'] ?? '-',
            'url' => $target['url'] ?? '-',
            'fails' => $fails,
            'status_text' => $statusText,
            'status_class' => $statusClass,
            'last_check' => isset($health['last_check_time']) ? date('Y-m-d H:i:s', $health['last_check_time']) : '-'
        ];
    }
    
    $totalRequests = 0;
    $successCount = 0;
    foreach ($metricsData as $metrics) {
        $totalRequests += $metrics['total_requests'] ?? 0;
        $successCount += $metrics['success_count'] ?? 0;
    }
    $successRate = $totalRequests > 0 ? round($successCount / $totalRequests * 100, 2) : 0;
    
    $recentErrors = getRecentErrorsFromAllLogs($logDir, 7, 20);
    
    return [
        'generated_at' => date('Y-m-d H:i:s'),
        'total_services' => $totalServices,
        'healthy_services' => $healthyServices,
        'warning_services' => $warningServices,
        'unhealthy_services' => $unhealthyServices,
        'total_requests' => $totalRequests,
        'success_rate' => $successRate,
        'services' => $services,
        'recent_errors' => $recentErrors,
        'status_url' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/status.php'
    ];
}

// 获取系统信息
$systemInfo = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
    'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
    'max_execution_time' => ini_get('max_execution_time') . '秒',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'date' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get()
];

// 计算统计信息
$totalFails = 0;
$totalServices = count($config['targets'] ?? []);
$healthyCount = 0;
$warningCount = 0;
$failedCount = 0;

foreach ($healthData as $id => $health) {
    $fails = $health['fails'] ?? 0;
    $totalFails += $fails;
    $threshold = $config['fail_threshold'] ?? 10;
    
    if ($fails == 0) {
        $healthyCount++;
    } elseif ($fails < $threshold) {
        $warningCount++;
    } else {
        $failedCount++;
    }
}

// 获取日志文件列表
$logFiles = getLogFiles($logDir);

// 获取初始错误日志
$initialErrors = getRecentErrorsFromAllLogs($logDir, 7, 50);
// 获取初始完整日志
$initialLogs = getLogContent($logFile, 200);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>反向代理监控系统 v<?php echo $config['system']['version'] ?? '2.0.0'; ?></title>
    <style>
        /* 保持原有样式，添加维护模式相关样式 */
        .maintenance-badge {
            background: #ff9800;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .maintenance-badge.active {
            background: #f44336;
        }
        
        .maintenance-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 30px;
            color: white;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #f44336;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .error-days-selector {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        
        .error-days-selector input {
            width: 60px;
            padding: 4px;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-align: center;
        }
        
        /* 其他样式保持不变 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .header h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header h1:before {
            content: '🛡️';
            font-size: 28px;
        }
        
        .update-time {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
            font-weight: normal;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e68900;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }
        
        .message {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: <?php echo $message ? 'block' : 'none'; ?>;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
        }
        
        .stat-card.healthy .number { color: #4CAF50; }
        .stat-card.warning .number { color: #ff9800; }
        .stat-card.unhealthy .number { color: #f44336; }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #666;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-healthy {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-unhealthy {
            background: #f8d7da;
            color: #721c24;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #8BC34A);
            transition: width 0.3s;
        }
        
        .progress-bar-fill.warning {
            background: linear-gradient(90deg, #ff9800, #ffc107);
        }
        
        .progress-bar-fill.danger {
            background: linear-gradient(90deg, #f44336, #ff5722);
        }
        
        .action-link {
            color: #667eea;
            text-decoration: none;
            margin: 0 5px;
            font-size: 12px;
        }
        
        .action-link:hover {
            text-decoration: underline;
        }
        
        .action-link.delete {
            color: #f44336;
        }
        
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-item .label {
            font-weight: bold;
            color: #666;
            display: block;
            font-size: 12px;
        }
        
        .info-item .value {
            font-size: 14px;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        
        .error-count-badge {
            background: #f44336;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            line-height: 1.5;
            padding: 10px;
            border-radius: 5px;
        }
        
        .log-line {
            padding: 2px 5px;
            border-bottom: 1px solid #333;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .log-line:hover {
            background: #2d2d2d;
        }
        
        .log-time {
            color: #569cd6;
            margin-right: 10px;
        }
        
        .log-level-INFO {
            color: #4ec9b0;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .log-level-WARN {
            color: #d7ba7d;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .log-level-ERROR {
            color: #f48771;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .log-message {
            color: #d4d4d4;
        }
        
        .log-controls {
            margin-bottom: 10px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .log-filter {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .log-type-selector {
            display: flex;
            gap: 5px;
            margin-right: 10px;
        }
        
        .log-type-btn {
            padding: 4px 8px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .log-type-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 14px;
        }
        
        .user-info span {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .log-date-selector {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                margin-top: 10px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                反向代理监控系统 v<?php echo $config['system']['version'] ?? '2.0.0'; ?>
                <span class="update-time" id="updateTime">最后更新: <?php echo date('H:i:s'); ?></span>
            </h1>
            <div class="header-actions">
                <div class="maintenance-toggle">
                    <span class="maintenance-badge <?php echo $config['system']['maintenance_mode'] ? 'active' : ''; ?>">
                        <?php echo $config['system']['maintenance_mode'] ? '🛠️ 维护中' : '✅ 运行中'; ?>
                    </span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="maintenanceToggle" <?php echo $config['system']['maintenance_mode'] ? 'checked' : ''; ?> onchange="toggleMaintenance()">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                
                <div class="user-info">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?></span>
                </div>
                
                <?php if ($password_change_enabled): ?>
                <a href="#" onclick="showPasswordModal(); return false;" class="btn btn-primary">🔑 修改密码</a>
                <?php endif; ?>
                
                <a href="apidocs.php" target="_blank" class="btn btn-primary">📚 API文档</a>
                <a href="?action=send_report" class="btn btn-primary" onclick="return confirm('确定发送状态报告邮件吗？')">📧 发送报告</a>
                <a href="?action=download_logs&date=<?php echo date('Y-m-d'); ?>&lines=200" class="btn btn-primary">📥 下载日志</a>
                <button class="btn btn-primary" onclick="refreshData()" id="refreshBtn">🔄 手动刷新</button>
                <a href="?logout=1" class="btn btn-danger" onclick="return confirm('确定要退出登录吗？')">🚪 退出</a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="message" id="messageBox">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid" id="statsGrid">
            <!-- 动态更新 -->
        </div>
        
        <div class="card" id="chartCard">
            <div class="card-header">
                <span>📊 服务健康状态概览</span>
                <span class="status-badge status-healthy" id="chartUpdateTime">最后更新: <?php echo date('H:i:s'); ?></span>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="healthChart"></canvas>
                </div>
            </div>
        </div>
        
        <div id="servicesTable">
            <!-- 动态更新 -->
        </div>
        
        <div class="stats-grid" id="metricsSection">
            <!-- 动态更新 -->
        </div>
        
        <div class="card" id="systemInfoCard">
            <div class="card-header">
                <span>⚙️ 系统信息</span>
            </div>
            <div class="card-body">
                <div class="system-info">
                    <?php foreach ($systemInfo as $key => $value): ?>
                    <div class="info-item">
                        <span class="label"><?php echo str_replace('_', ' ', ucfirst($key)); ?></span>
                        <span class="value"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- 日志显示模块 -->
        <div class="card" id="logViewerCard">
            <div class="card-header">
                <span>📋 系统日志</span>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <div class="log-type-selector">
                        <button class="log-type-btn active" onclick="switchLogType('current')" id="logTypeCurrent">📅 当天日志</button>
                        <button class="log-type-btn" onclick="switchLogType('error')" id="logTypeError">⚠️ 错误日志</button>
                    </div>
                    
                    <div id="currentLogControls" style="display: flex; gap: 5px; align-items: center;">
                        <select class="log-date-selector" id="logDateSelector" onchange="changeLogDate()">
                            <?php foreach ($logFiles as $date): ?>
                            <option value="<?php echo $date; ?>" <?php echo $date === date('Y-m-d') ? 'selected' : ''; ?>>
                                <?php echo $date; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="errorLogControls" style="display: none; gap: 5px; align-items: center;">
                        <span style="font-size: 12px; color: #666;">查看最近</span>
                        <input type="number" id="errorDays" value="7" min="1" max="7" onchange="changeErrorDays()" style="width: 50px; padding: 4px; border: 1px solid #ddd; border-radius: 3px;">
                        <span style="font-size: 12px; color: #666;">天的错误日志</span>
                    </div>
                    
                    <select class="log-filter" id="logLevelFilter" onchange="filterLogs()">
                        <option value="all">全部级别</option>
                        <option value="INFO">INFO</option>
                        <option value="WARN">WARN</option>
                        <option value="ERROR">ERROR</option>
                    </select>
                    
                    <!-- 下载按钮 - 只在当天日志模式显示 -->
                    <a href="#" onclick="downloadCurrentLog()" class="btn btn-primary" id="downloadLogBtn">📥 下载</a>
                </div>
            </div>
            <div class="card-body">
                <div class="log-controls">
                    <button class="btn btn-primary" onclick="toggleLogAutoRefresh()" id="logAutoRefreshBtn">⏸️ 暂停自动刷新</button>
                    <button class="btn btn-primary" onclick="clearLogFilter()">清除筛选</button>
                    <span id="logStats" style="color: #666; font-size: 12px;"></span>
                </div>
                <div class="log-container" id="logContainer">
                    <!-- 日志内容会通过JavaScript动态加载 -->
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; color: #999; font-size: 12px;">
            <p>反向代理系统 v<?php echo $config['system']['version'] ?? '2.0.0'; ?> | 页面生成时间: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>

    <!-- 密码修改模态框 -->
    <div id="passwordModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #333;">修改密码</h3>
                <span onclick="closePasswordModal()" style="cursor: pointer; font-size: 24px; color: #999;">&times;</span>
            </div>
            
            <?php if ($password_message): ?>
            <div class="message" style="background: <?php echo strpos($password_message, '成功') !== false ? '#4CAF50' : '#f44336'; ?>; color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php echo htmlspecialchars($password_message); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="old_password">原密码</label>
                    <input type="password" id="old_password" name="old_password" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                </div>
                
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                    <small style="color: #999;">密码长度不能少于6位</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="change_password" class="btn btn-success" style="flex: 1; padding: 10px;">确认修改</button>
                    <button type="button" onclick="closePasswordModal()" class="btn btn-primary" style="flex: 1; padding: 10px;">取消</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 初始化图表
        let healthChart;
        let logAutoRefresh = true;
        let currentLogFilter = 'all';
        let currentLogType = 'current';
        let currentLogDate = '<?php echo date('Y-m-d'); ?>';
        let currentErrorDays = 7;
        let logFiles = <?php echo json_encode($logFiles); ?>;
        
        function initChart(healthy, warning, failed) {
            const ctx = document.getElementById('healthChart').getContext('2d');
            if (healthChart) {
                healthChart.destroy();
            }
            healthChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['健康', '警告', '失效'],
                    datasets: [{
                        data: [healthy, warning, failed],
                        backgroundColor: ['#4CAF50', '#ff9800', '#f44336'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    
        // 初始化图表
        initChart(<?php echo $healthyCount; ?>, <?php echo $warningCount; ?>, <?php echo $failedCount; ?>);
    
        // 切换维护模式
        function toggleMaintenance() {
            if (confirm('确定要' + (document.getElementById('maintenanceToggle').checked ? '开启' : '关闭') + '维护模式吗？')) {
                window.location.href = '?action=toggle_maintenance';
            } else {
                // 如果用户取消，恢复复选框状态
                document.getElementById('maintenanceToggle').checked = !document.getElementById('maintenanceToggle').checked;
            }
        }
    
        // 刷新数据函数
        async function refreshData() {
            const refreshBtn = document.getElementById('refreshBtn');
            const originalText = refreshBtn.textContent;
            
            refreshBtn.textContent = '⏳ 更新中...';
            refreshBtn.disabled = true;
            document.body.classList.add('loading');
            
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('ajax', 'get_data');
                url.searchParams.set('log_date', currentLogDate);
                url.searchParams.set('log_type', currentLogType);
                url.searchParams.set('error_days', currentErrorDays);
                url.searchParams.set('nocache', Date.now());
                
                const response = await fetch(url.toString());
                
                // 检查是否未授权
                if (response.status === 401) {
                    window.location.reload();
                    return;
                }
                
                const data = await response.json();
                
                // 更新统计卡片
                updateStats(data.stats);
                
                // 更新服务列表
                updateServices(data.services);
                
                // 更新性能指标
                updateMetrics(data.metrics);
                
                // 更新日志
                updateLogViewer(data.logs);
                
                // 更新图表
                initChart(data.chart_data[0], data.chart_data[1], data.chart_data[2]);
                
                // 更新时间显示
                document.getElementById('updateTime').textContent = '最后更新: ' + data.last_update;
                document.getElementById('chartUpdateTime').textContent = '最后更新: ' + data.last_update;
                
                // 更新日志统计
                updateLogStats(data.logs);
                
            } catch (error) {
                console.error('刷新失败:', error);
            } finally {
                refreshBtn.textContent = originalText;
                refreshBtn.disabled = false;
                document.body.classList.remove('loading');
            }
        }
    
        function updateLogStats(logs) {
            const statsEl = document.getElementById('logStats');
            if (logs && logs.length) {
                const infoCount = logs.filter(l => l.level === 'INFO').length;
                const warnCount = logs.filter(l => l.level === 'WARN').length;
                const errorCount = logs.filter(l => l.level === 'ERROR').length;
                statsEl.textContent = `📊 共 ${logs.length} 条 (INFO: ${infoCount}, WARN: ${warnCount}, ERROR: ${errorCount})`;
            } else {
                statsEl.textContent = '暂无日志';
            }
        }
    
        function updateStats(stats) {
            const statsGrid = document.getElementById('statsGrid');
            const totalServices = stats.total_services;
            
            statsGrid.innerHTML = `
                <div class="stat-card healthy">
                    <h3>健康服务</h3>
                    <div class="number">${stats.healthy}</div>
                    <small>${totalServices > 0 ? Math.round(stats.healthy / totalServices * 100) : 0}%</small>
                </div>
                <div class="stat-card warning">
                    <h3>警告服务</h3>
                    <div class="number">${stats.warning}</div>
                    <small>失败次数低于阈值</small>
                </div>
                <div class="stat-card unhealthy">
                    <h3>失效服务</h3>
                    <div class="number">${stats.failed}</div>
                    <small>超过失败阈值</small>
                </div>
                <div class="stat-card">
                    <h3>总失败次数</h3>
                    <div class="number">${stats.total_fails}</div>
                    <small>累计失败计数</small>
                </div>
            `;
        }
    
        function updateServices(services) {
            let html = `
                <div class="card">
                    <div class="card-header">
                        <span>🎯 目标服务器列表</span>
                        <span class="status-badge status-healthy">共 ${services.length} 个服务</span>
                    </div>
                    <div class="card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>服务名称</th>
                                    <th>描述</th>
                                    <th>URL</th>
                                    <th>状态</th>
                                    <th>失败次数</th>
                                    <th>进度</th>
                                    <th>最后检查</th>
                                    <th>最后失败</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            services.forEach(s => {
                html += `
                    <tr>
                        <td><strong>${escapeHtml(s.id)}</strong></td>
                        <td>${escapeHtml(s.name)}</td>
                        <td>${escapeHtml(s.description)}</td>
                        <td><small>${escapeHtml(s.url)}</small></td>
                        <td><span class="status-badge ${s.status_class}">${s.status_text}</span></td>
                        <td>${s.fails} / ${s.threshold}</td>
                        <td style="width: 150px;">
                            <div class="progress-bar">
                                <div class="progress-bar-fill ${s.progress_class}" style="width: ${s.percentage}%;"></div>
                            </div>
                            <small>${s.percentage}%</small>
                        </td>
                        <td>${s.last_check}</td>
                        <td>${s.last_fail}</td>
                        <td>
                            <a href="?action=reset_fails&id=${encodeURIComponent(s.id)}" class="action-link" onclick="return confirm('确定重置该服务的失败计数吗？')">重置</a>
                            ${s.notified ? '<span class="action-link" title="已发送通知">📧</span>' : ''}
                        </td>
                    </tr>
                `;
            });
            
            if (services.length === 0) {
                html += '<tr><td colspan="10" style="text-align: center; color: #999;">暂无目标服务器配置</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('servicesTable').innerHTML = html;
        }
    
        function updateMetrics(metrics) {
            let html = `
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <span>📈 性能指标</span>
                    </div>
                    <div class="card-body">
                        <table>
                            <thead>
                                <tr>
                                    <th>服务ID</th>
                                    <th>总请求数</th>
                                    <th>成功数</th>
                                    <th>失败数</th>
                                    <th>成功率</th>
                                    <th>平均响应时间</th>
                                    <th>最后活动</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            metrics.forEach(m => {
                html += `
                    <tr>
                        <td><strong>${escapeHtml(m.id)}</strong></td>
                        <td>${m.total}</td>
                        <td>${m.success}</td>
                        <td>${m.fail}</td>
                        <td>
                            <span class="status-badge ${m.success_rate_class}">
                                ${m.success_rate}%
                            </span>
                        </td>
                        <td>${m.avg_time}</td>
                        <td>${m.last_time}</td>
                    </tr>
                `;
            });
            
            if (metrics.length === 0) {
                html += '<tr><td colspan="7" style="text-align: center; color: #999;">暂无性能数据</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            document.getElementById('metricsSection').innerHTML = html;
        }
    
        function updateLogViewer(logs) {
            const logContainer = document.getElementById('logContainer');
            
            if (logs && logs.length > 0) {
                let html = '';
                logs.forEach(log => {
                    // 根据筛选条件过滤
                    if (currentLogFilter !== 'all' && log.level !== currentLogFilter) {
                        return;
                    }
                    
                    html += `<div class="log-line">`;
                    if (log.time) {
                        html += `<span class="log-time">[${escapeHtml(log.time)}]</span>`;
                    }
                    if (log.date && currentLogType === 'error') {
                        html += `<span class="log-time">[${escapeHtml(log.date)}]</span>`;
                    }
                    html += `<span class="log-level-${log.level}">[${escapeHtml(log.level)}]</span>`;
                    html += `<span class="log-message">${escapeHtml(log.message)}</span>`;
                    html += `</div>`;
                });
                
                if (html === '') {
                    html = '<div class="log-line" style="color: #999; text-align: center;">没有匹配的日志</div>';
                }
                
                logContainer.innerHTML = html;
                logContainer.scrollTop = 0;
            } else {
                logContainer.innerHTML = '<div class="log-line" style="color: #999; text-align: center;">暂无日志</div>';
            }
        }
    
        function filterLogs() {
            const filter = document.getElementById('logLevelFilter');
            currentLogFilter = filter.value;
            refreshData();
        }
    
        function switchLogType(type) {
            currentLogType = type;
            
            // 更新按钮样式
            document.getElementById('logTypeCurrent').classList.remove('active');
            document.getElementById('logTypeError').classList.remove('active');
            document.getElementById(`logType${type === 'current' ? 'Current' : 'Error'}`).classList.add('active');
            
            // 显示/隐藏相关控件
            const currentControls = document.getElementById('currentLogControls');
            const errorControls = document.getElementById('errorLogControls');
            const downloadBtn = document.getElementById('downloadLogBtn');
            
            if (type === 'error') {
                currentControls.style.display = 'none';
                errorControls.style.display = 'flex';
                downloadBtn.style.display = 'none'; // 错误日志模式隐藏下载按钮
            } else {
                currentControls.style.display = 'flex';
                errorControls.style.display = 'none';
                downloadBtn.style.display = 'inline-block'; // 当天日志模式显示下载按钮
            }
            
            refreshData();
        }
    
        function changeLogDate() {
            const selector = document.getElementById('logDateSelector');
            currentLogDate = selector.value;
            refreshData();
        }
    
        function changeErrorDays() {
            const input = document.getElementById('errorDays');
            let days = parseInt(input.value);
            days = Math.min(7, Math.max(1, days));
            input.value = days;
            currentErrorDays = days;
            refreshData();
        }
    
        function toggleLogAutoRefresh() {
            logAutoRefresh = !logAutoRefresh;
            const btn = document.getElementById('logAutoRefreshBtn');
            btn.textContent = logAutoRefresh ? '⏸️ 暂停自动刷新' : '▶️ 恢复自动刷新';
            
            if (logAutoRefresh) {
                refreshData();
            }
        }
    
        function clearLogFilter() {
            const filter = document.getElementById('logLevelFilter');
            filter.value = 'all';
            currentLogFilter = 'all';
            filterLogs();
        }
    
        function downloadCurrentLog() {
            // 只在当天日志模式可以下载
            if (currentLogType !== 'current') {
                return;
            }
            const url = `?action=download_logs&date=${currentLogDate}&lines=500`;
            window.location.href = url;
        }
    
        // HTML转义函数
        function escapeHtml(text) {
            if (text === undefined || text === null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    
        // 自动刷新（每10秒）
        setInterval(() => {
            if (logAutoRefresh) {
                refreshData();
            }
        }, 10000);
    
        // 页面加载完成后立即刷新一次数据
        document.addEventListener('DOMContentLoaded', function() {
            refreshData();
        });
    
        // 5秒后自动隐藏消息框
        setTimeout(function() {
            const messageBox = document.getElementById('messageBox');
            if (messageBox) {
                messageBox.style.display = 'none';
            }
        }, 5000);
    
        // 密码修改模态框函数
        function showPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const passwordModal = document.getElementById('passwordModal');
            if (event.target == passwordModal) {
                passwordModal.style.display = 'none';
            }
        }
        
        // 密码强度实时检查
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                function checkPasswordMatch() {
                    if (confirmPassword.value) {
                        if (newPassword.value !== confirmPassword.value) {
                            confirmPassword.style.borderColor = '#f44336';
                        } else {
                            confirmPassword.style.borderColor = '#4CAF50';
                        }
                    }
                }
                
                newPassword.addEventListener('keyup', checkPasswordMatch);
                confirmPassword.addEventListener('keyup', checkPasswordMatch);
            }
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>