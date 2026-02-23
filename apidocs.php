<?php
/**
 * API文档页面
 */

// 加载配置
$config = include 'config.php';

// 设置时区
date_default_timezone_set($config['system']['timezone'] ?? 'Asia/Shanghai');

// 获取当前域名和协议
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;

// 获取API Token状态
$apiToken = $config['admin']['api_token'] ?? '';
$apiEnabled = !empty($apiToken);

// 示例数据（使用虚构数据）
$exampleServices = [
    [
        'id' => 'service_001',
        'name' => '示例服务A',
        'description' => '这是一个示例服务',
        'url' => 'http://backend-server-1.example.com',
        'status' => 'healthy',
        'status_text' => '健康',
        'fails' => 0,
        'threshold' => 10,
        'last_check' => time() - 300,
        'last_fail' => null,
        'notified' => false,
        'metrics' => [
            'total_requests' => 12345,
            'success_count' => 12300,
            'fail_count' => 45,
            'avg_response_time' => 123.45
        ]
    ],
    [
        'id' => 'service_002',
        'name' => '示例服务B',
        'description' => '这是另一个示例服务',
        'url' => 'http://backend-server-2.example.com',
        'status' => 'warning',
        'status_text' => '警告',
        'fails' => 5,
        'threshold' => 10,
        'last_check' => time() - 600,
        'last_fail' => time() - 3600,
        'notified' => true,
        'metrics' => [
            'total_requests' => 6789,
            'success_count' => 6700,
            'fail_count' => 89,
            'avg_response_time' => 234.56
        ]
    ]
];

$exampleServiceDetail = [
    'id' => 'service_001',
    'name' => '示例服务A',
    'description' => '这是一个示例服务',
    'url' => 'http://backend-server-1.example.com',
    'timeout' => 30,
    'health_check' => '/health',
    'health_check_interval' => 10,
    'status' => 'healthy',
    'status_text' => '健康',
    'fails' => 0,
    'threshold' => 10,
    'last_check' => time() - 300,
    'last_success' => time() - 300,
    'last_fail' => null,
    'notified' => false,
    'recovery_notified' => false,
    'history' => [
        ['time' => time() - 3600, 'type' => 'health_check', 'status' => 'success'],
        ['time' => time() - 7200, 'type' => 'health_check', 'status' => 'success']
    ],
    'metrics' => [
        'total_requests' => 12345,
        'success_count' => 12300,
        'fail_count' => 45,
        'success_rate' => 99.64,
        'avg_response_time' => 123.45,
        'last_time' => time() - 60
    ]
];

$exampleHealthData = [
    'total_services' => 2,
    'healthy' => 1,
    'warning' => 1,
    'failed' => 0,
    'total_fails' => 5,
    'total_requests' => 19134,
    'success_rate' => 99.3,
    'timestamp' => time(),
    'datetime' => date('Y-m-d H:i:s')
];

$exampleLogs = [
    [
        'time' => date('Y-m-d H:i:s', time() - 120),
        'level' => 'ERROR',
        'message' => '转发失败 - ID: service_001, 错误: Connection refused'
    ],
    [
        'time' => date('Y-m-d H:i:s', time() - 300),
        'level' => 'WARN',
        'message' => '健康检查返回非200状态码 - ID: service_002, HTTP状态码: 503'
    ],
    [
        'time' => date('Y-m-d H:i:s', time() - 600),
        'level' => 'INFO',
        'message' => '转发成功 - ID: service_001, 状态码: 200'
    ]
];

// 字段说明表格
$serviceFields = [
    ['id', 'string', '服务唯一标识符'],
    ['name', 'string', '服务名称'],
    ['description', 'string', '服务描述'],
    ['url', 'string', '服务后端地址'],
    ['status', 'string', '服务状态（healthy/warning/unhealthy）'],
    ['status_text', 'string', '状态中文描述'],
    ['fails', 'int', '当前失败次数'],
    ['threshold', 'int', '失败阈值'],
    ['last_check', 'int', '最后一次检查时间戳'],
    ['last_fail', 'int|null', '最后一次失败时间戳'],
    ['notified', 'bool', '是否已发送告警'],
    ['metrics', 'object', '性能指标（见下表）']
];

$metricsFields = [
    ['total_requests', 'int', '总请求数'],
    ['success_count', 'int', '成功请求数'],
    ['fail_count', 'int', '失败请求数'],
    ['avg_response_time', 'float', '平均响应时间（毫秒）']
];

$serviceDetailFields = [
    ['timeout', 'int', '请求超时时间（秒）'],
    ['health_check', 'string', '健康检查路径'],
    ['health_check_interval', 'int', '健康检查间隔（秒）'],
    ['last_success', 'int|null', '最后一次成功时间戳'],
    ['recovery_notified', 'bool', '是否已发送恢复通知'],
    ['history', 'array', '最近20条历史记录']
];

$healthFields = [
    ['total_services', 'int', '总服务数'],
    ['healthy', 'int', '健康服务数'],
    ['warning', 'int', '警告服务数'],
    ['failed', 'int', '失效服务数'],
    ['total_fails', 'int', '总失败次数'],
    ['total_requests', 'int', '总请求数'],
    ['success_rate', 'float', '成功率（%）'],
    ['timestamp', 'int', '时间戳'],
    ['datetime', 'string', '日期时间']
];

$logFields = [
    ['time', 'string', '日志时间'],
    ['level', 'string', '日志级别（INFO/WARN/ERROR）'],
    ['message', 'string', '日志内容']
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API文档 - 反向代理监控系统</title>
    <style>
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
            max-width: 1200px;
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
            content: '📚';
            font-size: 28px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
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
        }
        
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
        }
        
        .card-body {
            padding: 20px;
        }
        
        .api-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .endpoint {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .endpoint h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .endpoint-method {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .method-get {
            background: #61affe;
            color: white;
        }
        
        .method-post {
            background: #49cc90;
            color: white;
        }
        
        .endpoint-url {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 5px;
            margin: 10px 0;
            word-break: break-all;
        }
        
        .param-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 13px;
        }
        
        .param-table th {
            background: #e9ecef;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        
        .param-table td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .param-table tr:hover {
            background: #f5f5f5;
        }
        
        .field-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 13px;
            background: #f8f9fa;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .field-table th {
            background: #667eea;
            color: white;
            padding: 8px;
            text-align: left;
        }
        
        .field-table td {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .field-table tr:last-child td {
            border-bottom: none;
        }
        
        .field-table code {
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        
        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin: 10px 0;
        }
        
        .code-block pre {
            margin: 0;
        }
        
        .response-example {
            background: #f0f7fb;
            border-left: 4px solid #667eea;
            padding: 10px 15px;
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .response-example pre {
            margin: 0;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            background: #6c757d;
            color: white;
            margin-left: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #999;
            font-size: 12px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .token-note {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #28a745;  /* 深绿色背景 */
            color: white;          /* 白色文字 */
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 14px;
            font-weight: bold;     /* 加粗让文字更清晰 */
        }
        
        .note-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 10px 15px;
            margin: 10px 0;
            font-size: 13px;
        }
        
        .section-title {
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #667eea;
            color: #667eea;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                margin-top: 10px;
            }
            
            .param-table,
            .field-table {
                font-size: 12px;
            }
            
            .param-table th,
            .param-table td,
            .field-table th,
            .field-table td {
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>API接口文档</h1>
            <div class="header-actions">
                <a href="status.php" class="btn btn-primary">⬅ 返回监控面板</a>
            </div>
        </div>
        
        <?php if (!$apiEnabled): ?>
        <div class="warning-box">
            <strong>⚠️ API服务当前已禁用</strong>
            <p style="margin-top: 5px;">请在 <code>config.php</code> 中设置 <code>admin.api_token</code> 以启用API服务。</p>
        </div>
        <?php else: ?>
        <div class="info-box">
            <strong>✅ API服务已启用</strong>
            <p style="margin-top: 5px; display: flex; align-items: center; flex-wrap: wrap;">
                <span>API Token: </span>
                <span class="token-note">已配置</span>
            </p>
            <div class="note-box">
                <strong>🔐 安全提示：</strong> API Token 已配置在 <code>config.php</code> 文件中。使用 API 时请在请求头或参数中提供正确的 Token。
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <span>🔐 认证方式</span>
                <span class="api-status <?php echo $apiEnabled ? 'status-enabled' : 'status-disabled'; ?>">
                    <?php echo $apiEnabled ? 'API已启用' : 'API已禁用'; ?>
                </span>
            </div>
            <div class="card-body">
                <p>所有API请求都需要提供API Token，支持以下四种方式：</p>
                
                <h4 style="margin-top: 15px;">1. 请求头方式（推荐）</h4>
                <div class="code-block">
                    <pre>Authorization: your-api-token-here
# 或
Authorization: Bearer your-api-token-here</pre>
                </div>
                
                <h4>2. GET参数方式</h4>
                <div class="code-block">
                    <pre><?php echo $baseUrl; ?>/api/services?token=your-api-token-here</pre>
                </div>
                
                <h4>3. POST参数方式</h4>
                <div class="code-block">
                    <pre>curl -X POST -d "token=your-api-token-here" <?php echo $baseUrl; ?>/api/logs</pre>
                </div>
                
                <h4>4. JSON请求体方式</h4>
                <div class="code-block">
                    <pre>{
    "token": "your-api-token-here",
    "lines": 100,
    "level": "ERROR"
}</pre>
                </div>
                
                <div class="note-box">
                    <strong>📌 注意：</strong> 所有API接口均支持 GET 和 POST 两种请求方式。POST请求可以同时支持表单参数和JSON格式。
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>📋 获取服务列表</span>
            </div>
            <div class="card-body">
                <div class="endpoint">
                    <span class="endpoint-method method-get">GET</span>
                    <span class="endpoint-method method-post">POST</span>
                    <strong>/api/services</strong>
                </div>
                
                <div class="endpoint-url">
                    <?php echo $baseUrl; ?>/api/services
                </div>
                
                <h4>请求参数</h4>
                <p>无</p>
                
                <h4>请求示例</h4>
                <div class="code-block">
                    <pre># GET 请求
curl -H "Authorization: your-token" <?php echo $baseUrl; ?>/api/services

# POST 请求
curl -X POST -H "Authorization: your-token" <?php echo $baseUrl; ?>/api/services</pre>
                </div>
                
                <h4>响应字段说明</h4>
                <table class="field-table">
                    <tr>
                        <th>字段</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                    <?php foreach ($serviceFields as $field): ?>
                    <tr>
                        <td><code><?php echo $field[0]; ?></code></td>
                        <td><?php echo $field[1]; ?></td>
                        <td><?php echo $field[2]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <h4>metrics 字段说明</h4>
                <table class="field-table">
                    <tr>
                        <th>字段</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                    <?php foreach ($metricsFields as $field): ?>
                    <tr>
                        <td><code><?php echo $field[0]; ?></code></td>
                        <td><?php echo $field[1]; ?></td>
                        <td><?php echo $field[2]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <h4>响应示例</h4>
                <div class="response-example">
                    <pre>{
    "success": true,
    "data": <?php echo json_encode($exampleServices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    "total": 2,
    "timestamp": "<?php echo date('Y-m-d H:i:s'); ?>"
}</pre>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>🔍 获取单个服务</span>
            </div>
            <div class="card-body">
                <div class="endpoint">
                    <span class="endpoint-method method-get">GET</span>
                    <span class="endpoint-method method-post">POST</span>
                    <strong>/api/services/{id}</strong>
                </div>
                
                <div class="endpoint-url">
                    <?php echo $baseUrl; ?>/api/services/service_001
                </div>
                
                <h4>路径参数</h4>
                <table class="param-table">
                    <tr>
                        <th>参数</th>
                        <th>类型</th>
                        <th>描述</th>
                    </tr>
                    <tr>
                        <td><code>id</code></td>
                        <td>string</td>
                        <td>服务ID（必填）</td>
                    </tr>
                </table>
                
                <h4>请求示例</h4>
                <div class="code-block">
                    <pre># GET 请求
curl -H "Authorization: your-token" <?php echo $baseUrl; ?>/api/services/service_001

# POST 请求
curl -X POST -H "Authorization: your-token" <?php echo $baseUrl; ?>/api/services/service_001</pre>
                </div>
                
                <h4>响应字段说明</h4>
                <table class="field-table">
                    <tr>
                        <th>字段</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                    <?php foreach ($serviceFields as $field): ?>
                    <tr>
                        <td><code><?php echo $field[0]; ?></code></td>
                        <td><?php echo $field[1]; ?></td>
                        <td><?php echo $field[2]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($serviceDetailFields as $field): ?>
                    <tr>
                        <td><code><?php echo $field[0]; ?></code></td>
                        <td><?php echo $field[1]; ?></td>
                        <td><?php echo $field[2]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <h4>响应示例</h4>
                <div class="response-example">
                    <pre>{
    "success": true,
    "data": <?php echo json_encode($exampleServiceDetail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    "timestamp": "<?php echo date('Y-m-d H:i:s'); ?>"
}</pre>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>❤️ 获取系统健康状态</span>
            </div>
            <div class="card-body">
                <div class="endpoint">
                    <span class="endpoint-method method-get">GET</span>
                    <span class="endpoint-method method-post">POST</span>
                    <strong>/api/health</strong>
                </div>
                
                <div class="endpoint-url">
                    <?php echo $baseUrl; ?>/api/health
                </div>
                
                <h4>请求参数</h4>
                <p>无</p>
                
                <h4>请求示例</h4>
                <div class="code-block">
                    <pre># GET 请求
curl -H "Authorization: your-token" <?php echo $baseUrl; ?>/api/health

# POST 请求
curl -X POST -H "Authorization: your-token" <?php echo $baseUrl; ?>/api/health</pre>
                </div>
                
                <h4>响应字段说明</h4>
                <table class="field-table">
                    <tr>
                        <th>字段</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                    <?php foreach ($healthFields as $field): ?>
                    <tr>
                        <td><code><?php echo $field[0]; ?></code></td>
                        <td><?php echo $field[1]; ?></td>
                        <td><?php echo $field[2]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <h4>响应示例</h4>
                <div class="response-example">
                    <pre>{
    "success": true,
    "data": <?php echo json_encode($exampleHealthData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
}</pre>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>📊 获取日志</span>
            </div>
            <div class="card-body">
                <div class="endpoint">
                    <span class="endpoint-method method-get">GET</span>
                    <span class="endpoint-method method-post">POST</span>
                    <strong>/api/logs</strong>
                </div>
                
                <div class="endpoint-url">
                    <?php echo $baseUrl; ?>/api/logs
                </div>
                
                <h4>请求参数</h4>
                <table class="param-table">
                    <tr>
                        <th>参数</th>
                        <th>类型</th>
                        <th>默认值</th>
                        <th>描述</th>
                    </tr>
                    <tr>
                        <td><code>lines</code></td>
                        <td>int</td>
                        <td>100</td>
                        <td>返回行数（10-500之间）</td>
                    </tr>
                    <tr>
                        <td><code>level</code></td>
                        <td>string</td>
                        <td>''</td>
                        <td>日志级别过滤（INFO/WARN/ERROR）</td>
                    </tr>
                    <tr>
                        <td><code>date</code></td>
                        <td>string</td>
                        <td>当天日期</td>
                        <td>日志日期，格式：YYYY-MM-DD</td>
                    </tr>
                </table>
                
                <h4>请求示例</h4>
                <div class="code-block">
                    <pre># GET 请求 - 获取当天错误日志
curl -H "Authorization: your-token" "<?php echo $baseUrl; ?>/api/logs?level=ERROR&lines=50"

# GET 请求 - 获取指定日期日志
curl -H "Authorization: your-token" "<?php echo $baseUrl; ?>/api/logs?date=<?php echo date('Y-m-d'); ?>&lines=200"

# POST 请求 - JSON格式
curl -X POST \
  -H "Authorization: your-token" \
  -H "Content-Type: application/json" \
  -d '{"lines":200,"level":"ERROR","date":"<?php echo date('Y-m-d'); ?>"}' \
  <?php echo $baseUrl; ?>/api/logs

# POST 请求 - 表单格式
curl -X POST \
  -H "Authorization: your-token" \
  -d "lines=200&level=ERROR&date=<?php echo date('Y-m-d'); ?>" \
  <?php echo $baseUrl; ?>/api/logs</pre>
                </div>
                
                <h4>响应字段说明</h4>
                <table class="field-table">
                    <tr>
                        <th>字段</th>
                        <th>类型</th>
                        <th>说明</th>
                    </tr>
                    <tr>
                        <td><code>time</code></td>
                        <td>string</td>
                        <td>日志时间</td>
                    </tr>
                    <tr>
                        <td><code>level</code></td>
                        <td>string</td>
                        <td>日志级别（INFO/WARN/ERROR）</td>
                    </tr>
                    <tr>
                        <td><code>message</code></td>
                        <td>string</td>
                        <td>日志内容</td>
                    </tr>
                </table>
                
                <h4>响应示例</h4>
                <div class="response-example">
                    <pre>{
    "success": true,
    "data": <?php echo json_encode($exampleLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    "total": 3,
    "date": "<?php echo date('Y-m-d'); ?>",
    "log_file": "<?php echo date('Y-m-d'); ?>.log",
    "timestamp": "<?php echo date('Y-m-d H:i:s'); ?>"
}</pre>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>⚠️ 错误响应格式</span>
            </div>
            <div class="card-body">
                <div class="response-example">
                    <pre>{
    "success": false,
    "error": "错误信息",
    "timestamp": "<?php echo date('Y-m-d H:i:s'); ?>"
}</pre>
                </div>
                
                <table class="param-table" style="margin-top: 10px;">
                    <tr>
                        <th>HTTP状态码</th>
                        <th>说明</th>
                    </tr>
                    <tr>
                        <td>400</td>
                        <td>请求参数错误</td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>未授权或Token无效</td>
                    </tr>
                    <tr>
                        <td>403</td>
                        <td>API已禁用</td>
                    </tr>
                    <tr>
                        <td>404</td>
                        <td>资源不存在</td>
                    </tr>
                    <tr>
                        <td>405</td>
                        <td>不支持的请求方法</td>
                    </tr>
                    <tr>
                        <td>500</td>
                        <td>服务器内部错误</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="footer">
            <p>反向代理系统 v<?php echo $config['system']['version'] ?? '2.0.0'; ?> | API文档生成时间: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p style="margin-top: 5px;">📧 如有问题，请联系管理员</p>
        </div>
    </div>
</body>
</html>