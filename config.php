<?php
/**
 * 反向代理配置文件
 */

return [
    // 邮件通知配置
    'email' => [
        'enabled' => true,
        'smtp_host' => 'smtp.126.com',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl', // tls 或 ssl
        'smtp_auth' => true,
        'username' => '你的邮箱@126.com',
        'password' => '你的邮箱授权码',
        'from' => '你的邮箱@126.com',
        'from_name' => '反向代理系统',
        'to' => '你的接收邮箱@126.com', // 管理员邮箱
        'to_name' => '管理员',
        'cc' => [], // 抄送
        'bcc' => [] // 密送
    ],
    
    // 日志文件路径（按天分割）
    'log_file' => __DIR__ . '/logs/' . date('Y-m-d') . '.log',
    
    // 健康检查失败记录文件
    'health_check_file' => __DIR__ . '/data/health_check.json',
    
    // 目标服务器配置
    'targets' => [
        // 根据ID分发到不同后端
        '123456789' => [
            'name' => 'bot',
            'url' => 'http://127.0.0.1:8081',
            'timeout' => 30, // 超时时间（秒）
            'health_check' => '/', // 健康检查路径
            'health_check_interval' => 10, // 健康检查间隔（秒）
            'description' => '机器人webhook分发服务'
        ],
        '987654321' => [
            'name' => 'bot2',
            'url' => 'http://127.0.0.1:8082',
            'timeout' => 30,
            'health_check' => '/',
            'health_check_interval' => 10,
            'description' => '机器人webhook分发服务'
        ]
    ],
    
    // 失败阈值，超过此值发送通知
    'fail_threshold' => 10,
    
    // 系统配置
    'system' => [
        'name' => '反向代理系统',
        'version' => '2.0.0',
        'timezone' => 'Asia/Shanghai',
        'maintenance_mode' => false,
        'maintenance_message' => '系统维护中，请稍后访问'
    ],
    
    // 监控配置
    'monitoring' => [
        'enable_metrics' => true,
        'metrics_file' => __DIR__ . '/data/metrics.json',
        'retention_days' => 30 // 指标保留天数
    ],
    
    // ====== 新增：管理员配置 ======
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123', // 明文密码（生产环境建议使用更复杂的密码）
        'session_lifetime' => 3600, // 会话生命周期（秒）
        'enable_password_change' => true, // 是否允许在界面修改密码
        'api_token' => 'cs123' //api服务需要token，当token为空时禁用api服务
    ]
];