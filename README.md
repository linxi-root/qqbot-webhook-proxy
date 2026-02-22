# QQ官方机器人 Webhook 反向代理分发系统

<div align="center">

![version](https://img.shields.io/badge/版本-1.0.0-blue)
![php](https://img.shields.io/badge/PHP-7.4+-green)
![MIT](https://img.shields.io/badge/许可证-MIT-orange)

让多个QQ机器人共用一个服务器端口的神奇工具！

</div>

### 📖 这是什么？

想象一下：你有一个服务器，但只有一个公网IP和一个端口（比如80或443）。如果你开发了多个QQ机器人，它们都需要接收腾讯的Webhook消息，但只能有一个程序占用这个端口...

这个工具就是来解决这个问题的！

它就像一个智能的快递分拣员：

- 所有QQ机器人的消息都先发到这个工具
- 工具根据消息里的"机器人ID"（X-Bot-Appid）
- 自动把消息转发给对应的机器人程序

### 🎯 适用场景

- ✅ 你在同一台服务器上运行多个QQ官方机器人
- ✅ 只有一个公网IP和端口可用
- ✅ 需要监控机器人是否正常运行
- ✅ 想要一个漂亮的管理界面查看状态
- ✅ 机器人挂了希望能收到邮件通知

### ✨ 核心功能
|功能 | 说明|
|-----|-----|
|智能分发 | 根据请求头中的X-Bot-Appid，自动转发到对应的机器人|
|健康检查 | 每隔10秒检查机器人是否还活着|
|邮件告警 | 机器人连续失败10次，自动发邮件提醒你|
|状态面板 | 漂亮的网页界面，一眼看清所有机器人状态|
|失败计数 | 记录每个机器人的失败次数，可视化显示|
|性能监控 | 统计请求量、响应时间、成功率|
|日志查看 | 直接在网页上看实时日志|
|密码保护 | 状态页面需要登录才能查看

### 🚀 5分钟快速上手

##### 第一步：环境要求

- PHP 7.4 或更高版本
- 支持CURL扩展
- 服务器能发邮件（可选，用于告警）

##### 第二步：下载安装

```bash
git clone https://github.com/linxi-root/qqbot-webhook-proxy.git
# 下载代码到你的服务器
```

```bash
cd qqbot-webhook-proxy
# 进入目录
```

```bash
composer require phpmailer/phpmailer
# 安装依赖（需要Composer）
```

##### 第三步：配置机器人

打开 config.php 文件，找到 targets 部分：

```php
'targets' => [
    // 在这里添加你的QQ机器人
    '123456789' => [  // ← 你的机器人AppID
        'name' => 'bot',  // 机器人名字（随便写）
        'url' => 'http://127.0.0.1:8081',  // 机器人实际运行的地址
        'timeout' => 30,  // 超时时间
        'health_check' => '/',  // 健康检查路径(默认)
        'description' => '机器人webhook分发服务'  // 描述
    ],
    '987654321' => [
        'name' => 'bot2',
        'url' => 'http://127.0.0.1:8082',  // 第二个机器人运行在8082端口
        'timeout' => 30,
        'health_check' => '/',
        'description' => '机器人webhook分发服务'
    ]
],
```

##### 第四步：配置邮件通知（可选）

还是 config.php 文件，修改邮件部分：

```php
'email' => [
    'enabled' => true,  // 改为true启用
    'smtp_host' => 'smtp.126.com',  // 你的邮箱SMTP服务器
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',
    'username' => '你的邮箱@126.com',
    'password' => '你的邮箱密码',  // 注意：126/163邮箱要用授权码
    'from' => '你的邮箱@126.com',
    'to' => '你的接收邮箱@126.com',  // 接收告警的邮箱
],
```
> from与username的值一样

##### 第五步：设置登录密码

还是 config.php 文件，找到 admin 部分：

```php
'admin' => [
    'username' => 'admin',
    'password' => 'admin123', // 明文密码（生产环境建议使用更复杂的密码）
    'session_lifetime' => 3600, // 会话生命周期（秒）
    'enable_password_change' => true // 是否允许在界面修改密码
]
```

##### 第六步：配置伪静态

```php
# 简单的规则，将所有请求指向 index.php
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

### 📝 如何在QQ机器人后台配置？

在QQ开放平台配置机器人时：

1. 找到你的机器人应用
2. 进入"开发设置"
3. 在"事件回调配置"中填写：
   ```
   https://你的域名
   ```
4. 保存即可！

原理：腾讯会把消息发到这个地址，我们的程序会自动根据AppID转发给正确的机器人。

### 📊 如何查看状态？

访问你的域名后加 /status.php(也可以不加.php)：

```
http://你的域名/status
```

输入你在 config.php 中设置的用户名密码，就能看到：

- 🟢 健康状态概览卡片
- 📋 所有机器人列表（带失败进度条）
- 📈 请求统计和响应时间
- ⚠️ 最近错误日志
- 📝 实时运行日志

### 📸 界面预览

#### 登录页面
[<img src="https://github.com/linxi-root/photo/blob/main/Screenshot_2026_0222_234200.png" width="300" alt="缩略图">](https://github.com/linxi-root/photo/blob/main/Screenshot_2026_0222_234200.png)
![photo](https://github.com/linxi-root/photo/blob/main/Screenshot_2026_0222_235549.png)
输入管理员账号密码进入监控面板

#### 监控主面板

![photo](https://github.com/linxi-root/photo/blob/main/Screenshot_2026_0222_234441.png)
整体概览：健康状态、失败计数、实时日志

#### 实时日志与错误监控

![photo](https://github.com/linxi-root/photo/blob/main/Screenshot_2026_0222_234200.png)
左侧实时日志，右侧错误记录，方便快速定位问题


### 🔧 常见问题

Q：机器人收不到消息怎么办？

1. 检查机器人程序是否正常运行
2. 查看状态面板，看是否显示"失效"
3. 查看"最近错误日志"有什么提示
4. 检查防火墙是否放行了端口

Q：如何重置某个机器人的失败计数？

在状态面板的机器人列表，点击对应行的"重置"按钮即可。

Q：邮件收不到告警？

1. 确认 email.enabled 设为 true
2. 检查SMTP配置是否正确
3. 126/163邮箱要用"授权码"而不是登录密码
4. 查看日志文件是否有发送失败的记录

Q：如何修改登录密码？

登录状态页面后，点击右上角的"🔑 修改密码"按钮即可在线修改。

### 📁 文件说明

```
项目目录/
├── index.php          # 入口文件（接收腾讯消息）
├── status.php         # 状态面板（查看监控）
├── config.php         # 配置文件（设置机器人）
├── ReverseProxy.php   # 核心代理类
├── Mailer.php         # 邮件发送类
├── logs/              # 日志目录
│   ├── 2024-01-01.log # 按天分割的日志
│   └── access.log     # 访问日志
└── data/              # 数据目录
    ├── health_check.json  # 健康检查数据
    └── metrics.json       # 性能指标数据
```

### 🔒 安全建议

1. 修改默认密码（admin/admin123）
2. 建议用HTTPS协议
3. 定期查看错误日志
4. 配置文件中的密码不要太简单
5. 邮件密码使用授权码而不是登录密码

### 🆘 获取帮助

遇到问题可以：

1. 查看 logs/ 目录下的错误日志
2. 在状态面板查看"最近错误日志"
3. 检查PHP错误日志：tail -f /var/log/php-fpm/error.log

### 📜 更新日志

v1.0.0

- ✨ 首次发布
- 🚀 支持多机器人分发
- 📊 添加监控状态面板
- 📧 支持邮件告警
- 🔐 添加登录认证

---

<div align="center">

如果这个工具帮到了你，请给个Star吧！

</div>
