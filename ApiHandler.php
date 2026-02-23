<?php
/**
 * API处理器 - 增强版
 */

class ApiHandler
{
    private $config;
    private $healthFile;
    private $metricsFile;
    private $logFile;
    private $logDir;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->healthFile = $config['health_check_file'] ?? __DIR__ . '/data/health_check.json';
        $this->metricsFile = $config['monitoring']['metrics_file'] ?? __DIR__ . '/data/metrics.json';
        $this->logFile = $config['log_file'] ?? __DIR__ . '/logs/proxy.log';
        $this->logDir = dirname($this->logFile);
    }
    
    /**
     * 验证API Token - 支持GET参数和请求头
     */
    private function validateToken()
    {
        $token = $this->config['admin']['api_token'] ?? '';
        
        // 如果token为空，禁用API
        if (empty($token)) {
            $this->sendError('API已禁用', 403);
            return false;
        }
        
        $providedToken = null;
        
        // 1. 从GET参数获取token
        if (isset($_GET['token'])) {
            $providedToken = $_GET['token'];
        }
        
        // 2. 从POST参数获取token
        if ($providedToken === null && isset($_POST['token'])) {
            $providedToken = $_POST['token'];
        }
        
        // 3. 从请求头获取token
        if ($providedToken === null) {
            $headers = $this->getAllHeaders();
            $authHeader = $headers['Authorization'] ?? $headers['AUTHORIZATION'] ?? '';
            
            if (!empty($authHeader)) {
                // 支持 Bearer Token 格式
                if (preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $providedToken = $matches[1];
                } else {
                    $providedToken = $authHeader;
                }
            }
        }
        
        // 4. 从JSON请求体获取token（POST请求）
        if ($providedToken === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $data = json_decode($input, true);
                if (is_array($data) && isset($data['token'])) {
                    $providedToken = $data['token'];
                }
            }
        }
        
        if (empty($providedToken)) {
            $this->sendError('缺少API Token，请在请求头、GET参数或POST参数中提供', 401);
            return false;
        }
        
        if ($providedToken !== $token) {
            $this->sendError('无效的API Token', 401);
            return false;
        }
        
        return true;
    }
    
    /**
     * 获取请求参数 - 支持GET、POST、JSON
     */
    private function getParam($key, $default = null)
    {
        // 1. 从GET参数获取
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        
        // 2. 从POST参数获取
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        
        // 3. 从JSON请求体获取
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            static $jsonData = null;
            if ($jsonData === null) {
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    $jsonData = json_decode($input, true);
                }
            }
            if (is_array($jsonData) && isset($jsonData[$key])) {
                return $jsonData[$key];
            }
        }
        
        return $default;
    }
    
    /**
     * 获取所有请求头
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
     * 发送JSON响应
     */
    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * 发送错误响应
     */
    private function sendError($message, $statusCode = 400)
    {
        $this->sendResponse([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], $statusCode);
    }
    
    /**
     * 获取服务列表
     */
    public function getServices()
    {
        if (!$this->validateToken()) return;
        
        $targets = $this->config['targets'] ?? [];
        $healthData = $this->loadJsonFile($this->healthFile);
        $metricsData = $this->loadJsonFile($this->metricsFile);
        
        $services = [];
        foreach ($targets as $id => $target) {
            $health = $healthData[$id] ?? ['fails' => 0, 'last_check_time' => null];
            $metrics = $metricsData[$id] ?? null;
            $threshold = $this->config['fail_threshold'] ?? 10;
            $fails = $health['fails'] ?? 0;
            
            if ($fails == 0) {
                $status = 'healthy';
                $statusText = '健康';
            } elseif ($fails < $threshold) {
                $status = 'warning';
                $statusText = '警告';
            } else {
                $status = 'unhealthy';
                $statusText = '失效';
            }
            
            $services[] = [
                'id' => $id,
                'name' => $target['name'] ?? $id,
                'description' => $target['description'] ?? '',
                'url' => $target['url'] ?? '',
                'status' => $status,
                'status_text' => $statusText,
                'fails' => $fails,
                'threshold' => $threshold,
                'last_check' => $health['last_check_time'] ?? null,
                'last_fail' => $health['last_fail_time'] ?? null,
                'notified' => !empty($health['notified']),
                'metrics' => $metrics ? [
                    'total_requests' => $metrics['total_requests'] ?? 0,
                    'success_count' => $metrics['success_count'] ?? 0,
                    'fail_count' => $metrics['fail_count'] ?? 0,
                    'avg_response_time' => isset($metrics['avg_response_time']) ? round($metrics['avg_response_time'] * 1000, 2) : 0
                ] : null
            ];
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $services,
            'total' => count($services),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 获取单个服务状态
     */
    public function getService($id)
    {
        if (!$this->validateToken()) return;
        
        $targets = $this->config['targets'] ?? [];
        
        if (!isset($targets[$id])) {
            $this->sendError('服务不存在', 404);
            return;
        }
        
        $healthData = $this->loadJsonFile($this->healthFile);
        $metricsData = $this->loadJsonFile($this->metricsFile);
        
        $target = $targets[$id];
        $health = $healthData[$id] ?? ['fails' => 0, 'last_check_time' => null];
        $metrics = $metricsData[$id] ?? null;
        $threshold = $this->config['fail_threshold'] ?? 10;
        $fails = $health['fails'] ?? 0;
        
        if ($fails == 0) {
            $status = 'healthy';
            $statusText = '健康';
        } elseif ($fails < $threshold) {
            $status = 'warning';
            $statusText = '警告';
        } else {
            $status = 'unhealthy';
            $statusText = '失效';
        }
        
        $service = [
            'id' => $id,
            'name' => $target['name'] ?? $id,
            'description' => $target['description'] ?? '',
            'url' => $target['url'] ?? '',
            'timeout' => $target['timeout'] ?? 30,
            'health_check' => $target['health_check'] ?? '/',
            'health_check_interval' => $target['health_check_interval'] ?? 60,
            'status' => $status,
            'status_text' => $statusText,
            'fails' => $fails,
            'threshold' => $threshold,
            'last_check' => $health['last_check_time'] ?? null,
            'last_success' => $health['last_success_time'] ?? null,
            'last_fail' => $health['last_fail_time'] ?? null,
            'notified' => !empty($health['notified']),
            'recovery_notified' => !empty($health['recovery_notified']),
            'history' => array_slice($health['history'] ?? [], -20), // 最近20条历史
            'metrics' => $metrics ? [
                'total_requests' => $metrics['total_requests'] ?? 0,
                'success_count' => $metrics['success_count'] ?? 0,
                'fail_count' => $metrics['fail_count'] ?? 0,
                'success_rate' => $metrics['total_requests'] > 0 
                    ? round(($metrics['success_count'] ?? 0) / $metrics['total_requests'] * 100, 2)
                    : 0,
                'avg_response_time' => isset($metrics['avg_response_time']) 
                    ? round($metrics['avg_response_time'] * 1000, 2) 
                    : 0,
                'last_time' => $metrics['last_time'] ?? null
            ] : null
        ];
        
        $this->sendResponse([
            'success' => true,
            'data' => $service,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 获取系统健康状态概览
     */
    public function getHealth()
    {
        if (!$this->validateToken()) return;
        
        $targets = $this->config['targets'] ?? [];
        $healthData = $this->loadJsonFile($this->healthFile);
        $metricsData = $this->loadJsonFile($this->metricsFile);
        
        $totalServices = count($targets);
        $healthyCount = 0;
        $warningCount = 0;
        $failedCount = 0;
        $totalFails = 0;
        $totalRequests = 0;
        $successCount = 0;
        
        foreach ($targets as $id => $target) {
            $health = $healthData[$id] ?? ['fails' => 0];
            $fails = $health['fails'] ?? 0;
            $totalFails += $fails;
            $threshold = $this->config['fail_threshold'] ?? 10;
            
            if ($fails == 0) {
                $healthyCount++;
            } elseif ($fails < $threshold) {
                $warningCount++;
            } else {
                $failedCount++;
            }
        }
        
        foreach ($metricsData as $metrics) {
            $totalRequests += $metrics['total_requests'] ?? 0;
            $successCount += $metrics['success_count'] ?? 0;
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => [
                'total_services' => $totalServices,
                'healthy' => $healthyCount,
                'warning' => $warningCount,
                'failed' => $failedCount,
                'total_fails' => $totalFails,
                'total_requests' => $totalRequests,
                'success_rate' => $totalRequests > 0 ? round($successCount / $totalRequests * 100, 2) : 0,
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * 获取日志
     */
    public function getLogs()
    {
        if (!$this->validateToken()) return;
        
        $lines = intval($this->getParam('lines', 100));
        $lines = min(500, max(10, $lines)); // 限制在10-500之间
        
        $level = $this->getParam('level', '');
        $date = $this->getParam('date', date('Y-m-d'));
        
        // 验证日期格式
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->sendError('日期格式错误，应为 YYYY-MM-DD', 400);
            return;
        }
        
        $logFile = $this->logDir . '/' . $date . '.log';
        
        if (!file_exists($logFile)) {
            $this->sendResponse([
                'success' => true,
                'data' => [],
                'total' => 0,
                'date' => $date,
                'message' => '日志文件不存在',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            return;
        }
        
        $logs = $this->getLogContent($logFile, $lines);
        
        // 按级别过滤
        if (!empty($level)) {
            $logs = array_filter($logs, function($log) use ($level) {
                return strtoupper($log['level']) === strtoupper($level);
            });
            $logs = array_values($logs);
        }
        
        $this->sendResponse([
            'success' => true,
            'data' => $logs,
            'total' => count($logs),
            'date' => $date,
            'log_file' => basename($logFile),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * 加载JSON文件
     */
    private function loadJsonFile($file)
    {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true) ?: [];
        }
        return [];
    }
    
    /**
     * 获取日志内容
     */
    private function getLogContent($logFile, $lines = 100)
    {
        if (!file_exists($logFile)) {
            return [];
        }
        
        $content = $this->tailFile($logFile, $lines);
        $lines = explode("\n", trim($content));
        $logs = [];
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            if (preg_match('/\[(.*?)\] \[(.*?)\] (.*)/', $line, $matches)) {
                $logs[] = [
                    'time' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3]
                ];
            } else {
                $logs[] = [
                    'time' => '',
                    'level' => 'INFO',
                    'message' => $line
                ];
            }
        }
        
        return array_reverse($logs);
    }
    
    /**
     * 优化的文件末尾读取函数
     */
    private function tailFile($filepath, $lines = 100)
    {
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
     * 路由处理
     */
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];
        $path = parse_url($path, PHP_URL_PATH);
        
        // 移除 /api 前缀
        $path = preg_replace('/^\/api/', '', $path);
        
        if ($method === 'GET' || $method === 'POST') {
            if ($path === '/services' || $path === '/services/') {
                $this->getServices();
            } elseif (preg_match('/^\/services\/(.+)$/', $path, $matches)) {
                $this->getService($matches[1]);
            } elseif ($path === '/health' || $path === '/health/') {
                $this->getHealth();
            } elseif ($path === '/logs' || $path === '/logs/') {
                $this->getLogs();
            } else {
                $this->sendError('API端点不存在', 404);
            }
        } else {
            $this->sendError('不支持的请求方法', 405);
        }
    }
}