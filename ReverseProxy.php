<?php
/**
 * 反向代理主类 - 更新版本
 */

require_once 'Mailer.php';

class ReverseProxy
{
    /**
     * 配置信息
     * @var array
     */
    private $config;
    
    /**
     * 请求头信息
     * @var array
     */
    private $headers;
    
    /**
     * 请求方法
     * @var string
     */
    private $method;
    
    /**
     * 请求路径
     * @var string
     */
    private $path;
    
    /**
     * 请求参数
     * @var array
     */
    private $params;
    
    /**
     * 请求体
     * @var string
     */
    private $body;
    
    /**
     * 邮件发送器
     * @var Mailer
     */
    private $mailer;
    
    /**
     * 开始时间
     * @var float
     */
    private $startTime;
    
    /**
     * 无效ID记录缓存文件路径
     */
    private $invalidIdCacheFile;
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->config = include 'config.php';
        
        // 设置时区
        date_default_timezone_set($this->config['system']['timezone']);
        
        $this->headers = $this->getAllHeaders();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = $_SERVER['REQUEST_URI'];
        $this->params = $_REQUEST;
        $this->body = file_get_contents('php://input');
        
        // 创建必要的目录
        $this->ensureDirectories();
        
        // 初始化邮件发送器
        $this->mailer = new Mailer($this->config['email'], $this->config['log_file']);
        
        // 设置无效ID缓存文件路径
        $this->invalidIdCacheFile = __DIR__ . '/data/invalid_id_cache.json';
    }
    
    /**
     * 加载无效ID缓存
     */
    private function loadInvalidIdCache()
    {
        if (file_exists($this->invalidIdCacheFile)) {
            $content = file_get_contents($this->invalidIdCacheFile);
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }
    
    /**
     * 保存无效ID缓存
     */
    private function saveInvalidIdCache($cache)
    {
        $dir = dirname($this->invalidIdCacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->invalidIdCacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }
    
    /**
     * 记录无效ID错误（每个ID最多记录2次）
     * @param string $id
     */
    private function logInvalidId($id)
    {
        $cacheKey = $id ?: 'missing';
        $now = time();
        
        // 加载缓存
        $invalidIdCache = $this->loadInvalidIdCache();
        
        // 清理过期缓存（超过24小时）
        foreach ($invalidIdCache as $key => $data) {
            if ($now - $data['last_time'] > 86400) { // 24小时
                unset($invalidIdCache[$key]);
            }
        }
        
        // 初始化或获取当前记录
        if (!isset($invalidIdCache[$cacheKey])) {
            $invalidIdCache[$cacheKey] = [
                'count' => 0,
                'last_time' => $now
            ];
        }
        
        $record = &$invalidIdCache[$cacheKey];
        
        // 检查是否已经记录过2次
        if ($record['count'] >= 2) {
            return; // 已达到最大记录次数，不再记录
        }
        
        // 增加记录次数
        $record['count']++;
        $record['last_time'] = $now;
        
        // 保存缓存
        $this->saveInvalidIdCache($invalidIdCache);
        
        // 记录日志
        $message = $id ? "无效的ID请求: {$id}" : "无效的ID请求: ID为空";
        if ($record['count'] == 1) {
            $message .= " (首次记录)";
        } else {
            $message .= " (第二次记录，后续24小时内将不再记录)";
        }
        
        $this->log($message, 'WARN');
    }
    
    /**
     * 确保必要的目录存在
     */
    private function ensureDirectories()
    {
        $directories = [
            dirname($this->config['log_file']),
            dirname($this->config['health_check_file']),
            dirname($this->config['monitoring']['metrics_file'])
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * 获取所有请求头
     * @return array
     */
    private function getAllHeaders()
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
    
    /**
     * 从多个可能的请求头中获取ID
     * @return string|null
     */
    private function getIdFromHeader()
    {
        $idHeaders = $this->config['id_headers'] ?? ['x-bot-appid'];
        
        foreach ($idHeaders as $headerName) {
            $headerNameLower = strtolower($headerName);
            foreach ($this->headers as $key => $value) {
                if (strtolower($key) === $headerNameLower) {
                    return $value;
                }
            }
        }
        return null;
    }
    
    
    
    /**
     * 记录日志
     * @param string $message
     * @param string $level
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->config['log_file'], $logMessage, FILE_APPEND);
    }
    
    /**
     * 记录指标
     * @param string $targetId
     * @param int $statusCode
     * @param float $responseTime
     */
    private function recordMetrics($targetId, $statusCode, $responseTime)
    {
        if (!$this->config['monitoring']['enable_metrics']) {
            return;
        }
        
        $metricsFile = $this->config['monitoring']['metrics_file'];
        $metrics = [];
        
        if (file_exists($metricsFile)) {
            $content = file_get_contents($metricsFile);
            $metrics = json_decode($content, true) ?: [];
        }
        
        // 确保目录存在
        $metricsDir = dirname($metricsFile);
        if (!is_dir($metricsDir)) {
            mkdir($metricsDir, 0755, true);
        }
        
        // 初始化指标
        if (!isset($metrics[$targetId])) {
            $metrics[$targetId] = [
                'total_requests' => 0,
                'success_count' => 0,
                'fail_count' => 0,
                'total_response_time' => 0,
                'avg_response_time' => 0,
                'last_time' => time()
            ];
        }
        
        // 更新指标
        $metrics[$targetId]['total_requests']++;
        $metrics[$targetId]['total_response_time'] += $responseTime;
        $metrics[$targetId]['avg_response_time'] = 
            $metrics[$targetId]['total_response_time'] / $metrics[$targetId]['total_requests'];
        
        // 判断成功失败（2xx和3xx都算成功）
        if ($statusCode >= 200 && $statusCode < 400) {
            $metrics[$targetId]['success_count']++;
        } else {
            $metrics[$targetId]['fail_count']++;
        }
        
        $metrics[$targetId]['last_time'] = time();
        
        // 清理过期数据（保留30天）
        $retentionDays = $this->config['monitoring']['retention_days'] ?? 30;
        $cutoffTime = time() - ($retentionDays * 86400);
        
        foreach ($metrics as $id => $data) {
            if (isset($data['last_time']) && $data['last_time'] < $cutoffTime) {
                unset($metrics[$id]);
            }
        }
        
        file_put_contents($metricsFile, json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    
    /**
     * 更新健康检查状态
     */
    private function updateHealthCheck($targetId, $success, $statusCode = null)
    {
        $healthFile = $this->config['health_check_file'];
        $healthDir = dirname($healthFile);
        
        if (!is_dir($healthDir)) {
            mkdir($healthDir, 0755, true);
        }
        
        $healthData = [];
        if (file_exists($healthFile)) {
            $healthData = json_decode(file_get_contents($healthFile), true) ?: [];
        }
        
        $now = time();
        
        if (!isset($healthData[$targetId])) {
            $healthData[$targetId] = [
                'fails' => 0,
                'last_fail_time' => null,
                'last_success_time' => null,
                'last_check_time' => $now,
                'notified' => false,
                'recovery_notified' => false,
                'history' => []
            ];
        }
        
        // 更新最后检查时间
        $healthData[$targetId]['last_check_time'] = $now;
        
        if ($success) {
            // 成功时重置失败计数
            $wasFailing = ($healthData[$targetId]['fails'] ?? 0) >= $this->config['fail_threshold'];
            
            $healthData[$targetId]['fails'] = 0;
            $healthData[$targetId]['last_success_time'] = $now;
            
            // 如果之前是失败状态，发送恢复通知
            if ($wasFailing && ($healthData[$targetId]['notified'] ?? false)) {
                $targetInfo = $this->config['targets'][$targetId];
                $this->mailer->sendRecovery($targetId, $targetInfo, $healthData[$targetId]);
                $healthData[$targetId]['recovery_notified'] = true;
                $healthData[$targetId]['notified'] = false;
            }
        } else {
            // 失败时增加计数
            $healthData[$targetId]['fails'] = ($healthData[$targetId]['fails'] ?? 0) + 1;
            $healthData[$targetId]['last_fail_time'] = $now;
            
            // 记录历史
            if (!isset($healthData[$targetId]['history'])) {
                $healthData[$targetId]['history'] = [];
            }
            
            $healthData[$targetId]['history'][] = [
                'time' => $now,
                'path' => $this->path,
                'method' => $this->method,
                'status_code' => $statusCode
            ];
            
            // 限制历史记录数量
            if (count($healthData[$targetId]['history']) > 100) {
                array_shift($healthData[$targetId]['history']);
            }
            
            // 检查是否需要发送告警
            $threshold = $this->config['fail_threshold'];
            if (($healthData[$targetId]['fails'] >= $threshold) && !($healthData[$targetId]['notified'] ?? false)) {
                $targetInfo = $this->config['targets'][$targetId];
                
                if ($this->mailer->sendAlert($targetId, $targetInfo, $healthData[$targetId])) {
                    $healthData[$targetId]['notified'] = true;
                    $healthData[$targetId]['recovery_notified'] = false;
                    $healthData[$targetId]['last_notify_time'] = $now;
                }
            }
        }
        
        file_put_contents($healthFile, json_encode($healthData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }    
    
    /**
     * 加载健康检查数据
     * @return array
     */
    private function loadHealthCheckData()
    {
        $file = $this->config['health_check_file'];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }
    
    /**
     * 保存健康检查数据
     * @param array $data
     */
    private function saveHealthCheckData($data)
    {
        file_put_contents(
            $this->config['health_check_file'],
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * 执行健康检查
     * @param string $url
     * @param string $targetId
     * @return bool
     */
    private function performHealthCheck($url, $targetId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false); // 不需要返回头信息
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // 检查是否有连接错误
        if ($error) {
            $this->log("健康检查连接失败 - ID: {$targetId}, URL: {$url}, 错误: {$error}", 'WARN');
            return false;
        }
        
        // 检查HTTP状态码
        if ($httpCode !== 200) {
            $this->log("健康检查返回非200状态码 - ID: {$targetId}, URL: {$url}, HTTP状态码: {$httpCode}", 'WARN');
            return false;
        }
        
        
        // 所有检查通过
        $this->log("健康检查成功 - ID: {$targetId}, URL: {$url}", 'INFO');
        return true;
    }
    
    /**
     * 检查目标地址是否有效
     * @param string $url
     * @param string $targetId
     * @return bool
     */
    private function checkTargetHealth($url, $targetId)
    {
        $targetInfo = $this->config['targets'][$targetId] ?? null;
        if (!$targetInfo) {
            return false;
        }
        
        // 加载健康检查数据
        $healthData = $this->loadHealthCheckData();
        
        // 获取上次检查时间
        $lastCheck = $healthData[$targetId]['last_check_time'] ?? 0;
        $interval = $targetInfo['health_check_interval'] ?? 60;
        
        // 如果最近检查过，直接返回缓存的状态
        if (time() - $lastCheck < $interval) {
            $lastSuccess = $healthData[$targetId]['last_success_time'] ?? 0;
            $fails = $healthData[$targetId]['fails'] ?? 0;
            
            // 如果有最近的成功记录，认为是健康的
            if ($lastSuccess > $lastCheck - $interval) {
                return true;
            }
            
            // 否则根据失败计数判断
            return $fails < $this->config['fail_threshold'];
        }
        
        // 需要执行健康检查
        $healthUrl = rtrim($url, '/') . $targetInfo['health_check'];
        $success = $this->performHealthCheck($healthUrl, $targetId);
        $now = time();
        
        // 更新健康检查数据
        if (!isset($healthData[$targetId])) {
            $healthData[$targetId] = [
                'fails' => 0,
                'last_check_time' => $now,
                'last_success_time' => $success ? $now : null,
                'last_fail_time' => $success ? null : $now,
                'notified' => false,
                'recovery_notified' => false,
                'history' => []
            ];
        } else {
            $healthData[$targetId]['last_check_time'] = $now;
            
            if ($success) {
                // 健康检查成功
                $healthData[$targetId]['last_success_time'] = $now;
                
                // 只有在之前有失败记录时才重置失败计数
                if (($healthData[$targetId]['fails'] ?? 0) > 0) {
                    $wasFailing = ($healthData[$targetId]['fails'] ?? 0) >= $this->config['fail_threshold'];
                    
                    $healthData[$targetId]['fails'] = 0;
                    
                    // 如果之前是失败状态，记录恢复
                    if ($wasFailing && ($healthData[$targetId]['notified'] ?? false)) {
                        $healthData[$targetId]['recovery_notified'] = true;
                        $healthData[$targetId]['notified'] = false;
                        
                        // 可以在这里发送恢复通知
                        // $this->mailer->sendRecovery($targetId, $targetInfo, $healthData[$targetId]);
                    }
                }
            } else {
                // 健康检查失败
                $healthData[$targetId]['last_fail_time'] = $now;
                $healthData[$targetId]['fails'] = ($healthData[$targetId]['fails'] ?? 0) + 1;
                
                // 记录失败历史
                if (!isset($healthData[$targetId]['history'])) {
                    $healthData[$targetId]['history'] = [];
                }
                
                $healthData[$targetId]['history'][] = [
                    'time' => $now,
                    'type' => 'health_check',
                    'url' => $healthUrl,
                    'status' => 'failed'
                ];
                
                // 限制历史记录数量
                if (count($healthData[$targetId]['history']) > 100) {
                    array_shift($healthData[$targetId]['history']);
                }
                
                // 检查是否需要发送告警
                $threshold = $this->config['fail_threshold'];
                if (($healthData[$targetId]['fails'] >= $threshold) && !($healthData[$targetId]['notified'] ?? false)) {
                    if ($this->mailer->sendAlert($targetId, $targetInfo, $healthData[$targetId])) {
                        $healthData[$targetId]['notified'] = true;
                        $healthData[$targetId]['recovery_notified'] = false;
                        $healthData[$targetId]['last_notify_time'] = $now;
                    }
                }
            }
        }
        
        // 保存健康检查数据
        $this->saveHealthCheckData($healthData);
        
        // 返回实际的健康状态
        return $success;
    }
    
    /**
     * 转发请求到目标服务器
     */
    private function forwardRequest($targetUrl, $targetId)
    {
        $targetInfo = $this->config['targets'][$targetId];
        $requestStart = microtime(true);
        
        // 检查维护模式
        if ($this->config['system']['maintenance_mode']) {
            $this->handleMaintenance();
            return false;
        }
        
        // 构建完整的目标URL
        $fullUrl = rtrim($targetUrl, '/') . $this->path;
        
        // 如果有查询参数，添加到URL
        if (!empty($_GET)) {
            $queryString = http_build_query($_GET);
            $fullUrl .= (strpos($fullUrl, '?') === false ? '?' : '&') . $queryString;
        }
        
        $this->log("转发请求 - ID: {$targetId}, URL: {$fullUrl}, 方法: {$this->method}");
        
        // 初始化cURL
        $ch = curl_init();
        
        // 设置cURL选项
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $targetInfo['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'ReverseProxy/2.0');
        
        // 设置请求方法
        switch ($this->method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
                break;
            case 'OPTIONS':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
                break;
            case 'HEAD':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
                curl_setopt($ch, CURLOPT_NOBODY, true);
                break;
        }
        
        // 设置请求头
        $forwardHeaders = [];
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) !== 'host' && strtolower($name) !== 'connection') {
                $forwardHeaders[] = "$name: $value";
            }
        }
        
        // 添加X-Forwarded-*头
        $forwardHeaders[] = 'X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
        $forwardHeaders[] = 'X-Forwarded-Proto: ' . ($_SERVER['HTTPS'] ?? 'http');
        $forwardHeaders[] = 'X-Forwarded-Host: ' . $_SERVER['HTTP_HOST'];
        $forwardHeaders[] = 'X-Forwarded-Port: ' . $_SERVER['SERVER_PORT'];
        $forwardHeaders[] = 'X-Request-ID: ' . uniqid('req_', true);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $forwardHeaders);
        
        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        $responseTime = microtime(true) - $requestStart;
        
        curl_close($ch);
        
        // 记录指标
        $this->recordMetrics($targetId, $httpCode, $responseTime);
        
        // 判断是否为连接错误
        $isConnectionError = ($response === false);
        
        if ($isConnectionError) {
            $this->log("转发失败 - ID: {$targetId}, 错误: {$error}, 响应时间: " . round($responseTime * 1000, 2) . "ms", 'ERROR');
            // 只有连接错误才更新健康状态
            $this->updateHealthCheck($targetId, false, 0);
            return false;
        }
        
        // 分离响应头和响应体
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        // 转发响应头
        $this->forwardResponseHeaders($responseHeaders);
        
        // 添加代理头
        header('X-Proxy-Server: ReverseProxy/2.0');
        header('X-Proxy-Response-Time: ' . round($responseTime * 1000, 2) . 'ms');
        
        // 输出响应体
        echo $responseBody;
        
        // 记录请求结果，但不立即更新健康状态（避免单次失败触发告警）
        if ($httpCode >= 500) {
            $this->log("转发返回服务器错误 - ID: {$targetId}, 状态码: {$httpCode}", 'WARN');
        } else {
            $this->log("转发成功 - ID: {$targetId}, 状态码: {$httpCode}", 'INFO');
        }
        
        // 成功返回
        return true;
    }
    
    /**
     * 处理维护模式
     */
    private function handleMaintenance()
    {
        http_response_code(503);
        header('Content-Type: application/json');
        header('Retry-After: 3600');
        
        $response = [
            'error' => 'Service Unavailable',
            'message' => $this->config['system']['maintenance_message'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response);
        $this->log("系统处于维护模式，返回503", 'WARN');
    }
    
    /**
     * 转发响应头
     * @param string $responseHeaders
     */
    private function forwardResponseHeaders($responseHeaders)
    {
        $headers = explode("\r\n", $responseHeaders);
        $excludedHeaders = ['Transfer-Encoding', 'Connection', 'Keep-Alive'];
        
        foreach ($headers as $header) {
            if (empty($header)) {
                continue;
            }
            
            $shouldExclude = false;
            foreach ($excludedHeaders as $excluded) {
                if (stripos($header, $excluded . ':') === 0) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if (!$shouldExclude) {
                header($header);
            }
        }
    }
    
    /**
     * 处理错误响应
     * @param string $message
     * @param int $statusCode
     */
    private function handleError($message, $statusCode = 502)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $errorResponse = [
            'error' => '反向代理错误',
            'message' => $message,
            'status_code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => uniqid('err_', true)
        ];
        
        echo json_encode($errorResponse);
        
        $this->log("返回错误响应: {$message}, 状态码: {$statusCode}", 'ERROR');
    }
    
    /**
     * 运行代理
     */
    public function run()
    {
        try {
            // 检查维护模式
            if ($this->config['system']['maintenance_mode']) {
                $this->handleMaintenance();
                return;
            }
            
            // 获取ID
            $id = $this->getIdFromHeader();
            
            if (!$id) {
                $this->logInvalidId(null);
                $this->handleError('无法从请求头中获取ID，请设置以下任一请求头：' . implode(', ', $this->config['id_headers'] ?? ['x-bot-appid']), 400);
                return;
            }
            
            $this->log("收到请求 - ID: {$id}, 路径: {$this->path}, 方法: {$this->method}, IP: {$_SERVER['REMOTE_ADDR']}");
            
            // 检查ID是否存在
            if (!isset($this->config['targets'][$id])) {
                $this->logInvalidId($id);
                $this->handleError("无效的ID: {$id}", 404);
                return;
            }
            
            $targetInfo = $this->config['targets'][$id];
            
            // 异步健康检查（不影响当前请求）
            $this->checkTargetHealthAsync($targetInfo['url'], $id);
            
            // 转发请求
            if (!$this->forwardRequest($targetInfo['url'], $id)) {
                $this->handleError("转发请求失败: {$targetInfo['name']}", 502);
                return;
            }
            
            // 记录总处理时间
            $totalTime = microtime(true) - $this->startTime;
            $this->log("请求处理完成 - 总时间: " . round($totalTime * 1000, 2) . "ms", 'INFO');
            
        } catch (Exception $e) {
            $this->handleError("系统错误: " . $e->getMessage(), 500);
        }
    }
    
    
    /**
     * 异步健康检查（不影响当前请求）
     */
    private function checkTargetHealthAsync($url, $targetId)
    {
        // 先检查是否需要执行健康检查，避免不必要的操作
        $healthData = $this->loadHealthCheckData();
        $lastCheck = $healthData[$targetId]['last_check_time'] ?? 0;
        $interval = $this->config['targets'][$targetId]['health_check_interval'] ?? 60;
        
        // 如果还没到检查间隔，直接返回
        if (time() - $lastCheck < $interval) {
            return;
        }
        
        // 使用fastcgi_finish_request()如果可用，确保健康检查在后台执行
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // 执行健康检查但不阻塞
        $this->checkTargetHealth($url, $targetId);
    }
}