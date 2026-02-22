<?php
/**
 * 邮件发送类 - 使用PHPMailer
 */

// 如果使用Composer自动加载
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * 配置信息
     * @var array
     */
    private $config;
    
    /**
     * 日志文件路径
     * @var string
     */
    private $logFile;
    
    /**
     * 构造函数
     * @param array $config 邮件配置
     * @param string $logFile 日志文件路径
     */
    public function __construct($config, $logFile)
    {
        $this->config = $config;
        $this->logFile = $logFile;
    }
    
    /**
     * 记录日志
     * @param string $message
     * @param string $level
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [MAILER] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * 发送邮件
     * @param string $to 收件人
     * @param string $toName 收件人名称
     * @param string $subject 主题
     * @param string $body 正文（HTML）
     * @param string $altBody 纯文本正文（可选）
     * @param array $attachments 附件列表
     * @return bool
     */
    public function send($to, $toName, $subject, $body, $altBody = '', $attachments = [])
    {
        if (!$this->config['enabled']) {
            $this->log('邮件功能已禁用，跳过发送', 'WARN');
            return false;
        }
        
        $mail = new PHPMailer(true);
        
        try {
            // 服务器配置
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host       = $this->config['smtp_host'];
            $mail->SMTPAuth   = $this->config['smtp_auth'];
            $mail->Username   = $this->config['username'];
            $mail->Password   = $this->config['password'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port       = $this->config['smtp_port'];
            
            // 设置超时
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;
            
            // 设置字符集
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // 发件人
            $mail->setFrom($this->config['from'], $this->config['from_name']);
            
            // 收件人
            $mail->addAddress($to, $toName);
            
            // 抄送
            if (!empty($this->config['cc'])) {
                foreach ($this->config['cc'] as $cc) {
                    if (is_array($cc)) {
                        $mail->addCC($cc[0], $cc[1] ?? '');
                    } else {
                        $mail->addCC($cc);
                    }
                }
            }
            
            // 密送
            if (!empty($this->config['bcc'])) {
                foreach ($this->config['bcc'] as $bcc) {
                    if (is_array($bcc)) {
                        $mail->addBCC($bcc[0], $bcc[1] ?? '');
                    } else {
                        $mail->addBCC($bcc);
                    }
                }
            }
            
            // 附件
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                } elseif (isset($attachment['content'])) {
                    $mail->addStringAttachment(
                        $attachment['content'],
                        $attachment['name'],
                        $attachment['encoding'] ?? 'base64',
                        $attachment['type'] ?? 'application/octet-stream'
                    );
                }
            }
            
            // 内容
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            
            // 发送
            $mail->send();
            
            $this->log("邮件发送成功 - 收件人: {$to}, 主题: {$subject}");
            return true;
            
        } catch (Exception $e) {
            $this->log("邮件发送失败 - 错误: {$mail->ErrorInfo}", 'ERROR');
            return false;
        }
    }
    
    /**
     * 发送告警邮件
     * @param string $targetId
     * @param array $targetInfo
     * @param array $healthData
     * @return bool
     */
    public function sendAlert($targetId, $targetInfo, $healthData)
    {
        $subject = "【{$this->config['from_name']}】告警通知：{$targetInfo['name']} 服务异常";
        
        // 构建HTML正文
        $body = $this->buildAlertHtml($targetId, $targetInfo, $healthData);
        
        // 构建纯文本正文
        $altBody = $this->buildAlertText($targetId, $targetInfo, $healthData);
        
        return $this->send(
            $this->config['to'],
            $this->config['to_name'],
            $subject,
            $body,
            $altBody
        );
    }
    
    /**
     * 发送恢复邮件
     * @param string $targetId
     * @param array $targetInfo
     * @param array $healthData
     * @return bool
     */
    public function sendRecovery($targetId, $targetInfo, $healthData)
    {
        $subject = "【{$this->config['from_name']}】恢复通知：{$targetInfo['name']} 服务已恢复";
        
        // 构建HTML正文
        $body = $this->buildRecoveryHtml($targetId, $targetInfo, $healthData);
        
        // 构建纯文本正文
        $altBody = $this->buildRecoveryText($targetId, $targetInfo, $healthData);
        
        return $this->send(
            $this->config['to'],
            $this->config['to_name'],
            $subject,
            $body,
            $altBody
        );
    }
    
    /**
     * 发送报告邮件
     * @param array $reportData
     * @return bool
     */
    public function sendReport($reportData)
    {
        // 检查邮件功能是否启用
        if (!$this->config['enabled']) {
            $this->log('邮件功能已禁用，跳过发送报告', 'WARN');
            return false;
        }
        
        // 验证报告数据
        if (empty($reportData) || !is_array($reportData)) {
            $this->log('报告数据无效', 'ERROR');
            return false;
        }
        
        $subject = "【{$this->config['from_name']}】服务状态报告 - " . date('Y-m-d H:i:s');
        
        // 构建HTML正文
        $body = $this->buildReportHtml($reportData);
        
        // 构建纯文本正文
        $altBody = $this->buildReportText($reportData);
        
        // 发送给配置的管理员邮箱
        return $this->send(
            $this->config['to'],
            $this->config['to_name'],
            $subject,
            $body,
            $altBody
        );
    }
    
    /**
     * 构建告警HTML
     */
    private function buildAlertHtml($targetId, $targetInfo, $healthData)
    {
        $failTime = date('Y-m-d H:i:s', $healthData['last_fail_time']);
        $threshold = $GLOBALS['config']['fail_threshold'] ?? 10;
        
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body { font-family: "Microsoft YaHei", Arial, sans-serif; line-height: 1.6; color: #333; }';
        $html .= '.container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }';
        $html .= '.header { background: #f44336; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0; margin: -20px -20px 20px -20px; }';
        $html .= '.content { padding: 20px; }';
        $html .= '.info-item { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }';
        $html .= '.label { font-weight: bold; display: inline-block; width: 120px; }';
        $html .= '.value { display: inline-block; color: #f44336; }';
        $html .= '.footer { margin-top: 20px; font-size: 12px; color: #999; text-align: center; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="container">';
        $html .= '<div class="header">';
        $html .= '<h2>⚠️ 服务异常告警</h2>';
        $html .= '</div>';
        $html .= '<div class="content">';
        $html .= '<h3>服务 ' . htmlspecialchars($targetInfo['name']) . ' 检测到异常</h3>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务ID：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetId) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务名称：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetInfo['name']) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务地址：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetInfo['url']) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务描述：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetInfo['description'] ?? '') . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">失败次数：</span>';
        $html .= '<span class="value">' . intval($healthData['fails']) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">失败阈值：</span>';
        $html .= '<span class="value">' . intval($threshold) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">最后失败时间：</span>';
        $html .= '<span class="value">' . $failTime . '</span>';
        $html .= '</div>';
        
        $html .= '<p style="margin-top: 20px; padding: 10px; background: #f5f5f5; border-radius: 3px;">';
        $html .= '<strong>建议操作：</strong><br>';
        $html .= '1. 检查服务 ' . htmlspecialchars($targetInfo['name']) . ' 是否正常运行<br>';
        $html .= '2. 检查网络连接是否正常<br>';
        $html .= '3. 查看服务日志排查错误<br>';
        $html .= '4. 如问题持续，请联系运维团队';
        $html .= '</p>';
        
        $html .= '</div>';
        $html .= '<div class="footer">';
        $html .= '<p>此邮件由反向代理系统自动发送，请勿回复</p>';
        $html .= '<p>发送时间：' . date('Y-m-d H:i:s') . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }
    
    /**
     * 构建告警文本
     */
    private function buildAlertText($targetId, $targetInfo, $healthData)
    {
        $failTime = date('Y-m-d H:i:s', $healthData['last_fail_time']);
        $threshold = $GLOBALS['config']['fail_threshold'] ?? 10;
        
        return "服务异常告警\n" .
               "============\n\n" .
               "服务名称：{$targetInfo['name']}\n" .
               "服务ID：{$targetId}\n" .
               "服务地址：{$targetInfo['url']}\n" .
               "服务描述：{$targetInfo['description']}\n" .
               "失败次数：{$healthData['fails']}\n" .
               "失败阈值：{$threshold}\n" .
               "最后失败时间：{$failTime}\n\n" .
               "建议操作：\n" .
               "1. 检查服务 {$targetInfo['name']} 是否正常运行\n" .
               "2. 检查网络连接是否正常\n" .
               "3. 查看服务日志排查错误\n" .
               "4. 如问题持续，请联系运维团队\n\n" .
               "此邮件由反向代理系统自动发送，请勿回复\n" .
               "发送时间：" . date('Y-m-d H:i:s');
    }
    
    /**
     * 构建恢复HTML
     */
    private function buildRecoveryHtml($targetId, $targetInfo, $healthData)
    {
        $lastFailTime = date('Y-m-d H:i:s', $healthData['last_fail_time']);
        $recoverTime = date('Y-m-d H:i:s');
        $duration = $this->formatDuration(strtotime($recoverTime) - $healthData['last_fail_time']);
        
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body { font-family: "Microsoft YaHei", Arial, sans-serif; line-height: 1.6; color: #333; }';
        $html .= '.container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }';
        $html .= '.header { background: #4CAF50; color: white; padding: 10px 20px; border-radius: 5px 5px 0 0; margin: -20px -20px 20px -20px; }';
        $html .= '.content { padding: 20px; }';
        $html .= '.info-item { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }';
        $html .= '.label { font-weight: bold; display: inline-block; width: 120px; }';
        $html .= '.value { display: inline-block; color: #4CAF50; }';
        $html .= '.footer { margin-top: 20px; font-size: 12px; color: #999; text-align: center; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="container">';
        $html .= '<div class="header">';
        $html .= '<h2>✅ 服务恢复通知</h2>';
        $html .= '</div>';
        $html .= '<div class="content">';
        $html .= '<h3>服务 ' . htmlspecialchars($targetInfo['name']) . ' 已恢复正常</h3>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务ID：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetId) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务名称：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetInfo['name']) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">服务地址：</span>';
        $html .= '<span class="value">' . htmlspecialchars($targetInfo['url']) . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">故障时间：</span>';
        $html .= '<span class="value">' . $lastFailTime . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">恢复时间：</span>';
        $html .= '<span class="value">' . $recoverTime . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="info-item">';
        $html .= '<span class="label">故障时长：</span>';
        $html .= '<span class="value">' . $duration . '</span>';
        $html .= '</div>';
        
        $html .= '<p style="margin-top: 20px; padding: 10px; background: #f5f5f5; border-radius: 3px;">';
        $html .= '<strong>事件总结：</strong><br>';
        $html .= '服务已自动恢复，建议关注服务稳定性，如有需要可查看详细日志。';
        $html .= '</p>';
        
        $html .= '</div>';
        $html .= '<div class="footer">';
        $html .= '<p>此邮件由反向代理系统自动发送，请勿回复</p>';
        $html .= '<p>发送时间：' . $recoverTime . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }
    
    /**
     * 构建恢复文本
     */
    private function buildRecoveryText($targetId, $targetInfo, $healthData)
    {
        $lastFailTime = date('Y-m-d H:i:s', $healthData['last_fail_time']);
        $recoverTime = date('Y-m-d H:i:s');
        $duration = $this->formatDuration(strtotime($recoverTime) - $healthData['last_fail_time']);
        
        return "服务恢复通知\n" .
               "=============\n\n" .
               "服务名称：{$targetInfo['name']}\n" .
               "服务ID：{$targetId}\n" .
               "服务地址：{$targetInfo['url']}\n" .
               "故障时间：{$lastFailTime}\n" .
               "恢复时间：{$recoverTime}\n" .
               "故障时长：{$duration}\n\n" .
               "事件总结：\n" .
               "服务已自动恢复，建议关注服务稳定性，如有需要可查看详细日志。\n\n" .
               "此邮件由反向代理系统自动发送，请勿回复\n" .
               "发送时间：{$recoverTime}";
    }
    
    /**
     * 构建报告HTML（唯一版本）
     */
    private function buildReportHtml($reportData)
    {
        // 确保必要的数据存在
        $generatedAt = $reportData['generated_at'] ?? date('Y-m-d H:i:s');
        $totalServices = $reportData['total_services'] ?? 0;
        $healthyServices = $reportData['healthy_services'] ?? 0;
        $warningServices = $reportData['warning_services'] ?? 0;
        $unhealthyServices = $reportData['unhealthy_services'] ?? 0;
        $totalRequests = $reportData['total_requests'] ?? 0;
        $successRate = $reportData['success_rate'] ?? 0;
        $services = $reportData['services'] ?? [];
        $recentErrors = $reportData['recent_errors'] ?? [];
        $statusUrl = $reportData['status_url'] ?? '#';
        
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body { font-family: "Microsoft YaHei", Arial, sans-serif; line-height: 1.6; color: #333; }';
        $html .= '.container { max-width: 800px; margin: 0 auto; padding: 20px; }';
        $html .= 'h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }';
        $html .= 'h2 { color: #666; margin-top: 30px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        $html .= 'th { background: #4CAF50; color: white; padding: 10px; text-align: left; }';
        $html .= 'td { padding: 10px; border-bottom: 1px solid #ddd; }';
        $html .= 'tr:hover { background: #f5f5f5; }';
        $html .= '.healthy { color: #4CAF50; font-weight: bold; }';
        $html .= '.warning { color: #FF9800; font-weight: bold; }';
        $html .= '.unhealthy { color: #f44336; font-weight: bold; }';
        $html .= '.summary { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }';
        $html .= '.footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }';
        $html .= '.error-item { padding: 5px 0; border-bottom: 1px dashed #eee; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<div class="container">';
        $html .= '<h1>反向代理系统服务状态报告</h1>';
        $html .= '<p>报告生成时间：' . $generatedAt . '</p>';
        
        $html .= '<div class="summary">';
        $html .= '<h3>📊 系统概要</h3>';
        $html .= '<p>总服务数：' . $totalServices . '</p>';
        $html .= '<p>✅ 健康服务：' . $healthyServices . '</p>';
        $html .= '<p>⚠️ 警告服务：' . $warningServices . '</p>';
        $html .= '<p>❌ 失效服务：' . $unhealthyServices . '</p>';
        $html .= '<p>📈 总请求数：' . $totalRequests . '</p>';
        $html .= '<p>🎯 成功率：' . $successRate . '%</p>';
        $html .= '</div>';
        
        $html .= '<h2>📋 服务详细状态</h2>';
        $html .= '<table>';
        $html .= '<thead><tr><th>ID</th><th>名称</th><th>描述</th><th>状态</th><th>失败次数</th><th>最后检查</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($services as $service) {
            $statusClass = $service['status_class'] ?? '';
            $statusText = $service['status_text'] ?? '未知';
            $id = htmlspecialchars($service['id'] ?? '');
            $name = htmlspecialchars($service['name'] ?? '');
            $description = htmlspecialchars($service['description'] ?? '-');
            $fails = $service['fails'] ?? 0;
            $lastCheck = $service['last_check'] ?? '-';
            
            $html .= '<tr>';
            $html .= '<td>' . $id . '</td>';
            $html .= '<td>' . $name . '</td>';
            $html .= '<td>' . $description . '</td>';
            $html .= '<td class="' . $statusClass . '">' . $statusText . '</td>';
            $html .= '<td>' . $fails . '</td>';
            $html .= '<td>' . $lastCheck . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        $html .= '<h2>⚠️ 最近错误日志</h2>';
        $html .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px;">';
        
        if (empty($recentErrors)) {
            $html .= '<p style="color: #4CAF50;">✅ 暂无错误日志</p>';
        } else {
            foreach ($recentErrors as $error) {
                $time = htmlspecialchars($error['time'] ?? '');
                $level = htmlspecialchars($error['level'] ?? '');
                $message = htmlspecialchars($error['message'] ?? '');
                $color = $level === 'ERROR' ? '#f44336' : '#FF9800';
                
                $html .= '<div class="error-item">';
                $html .= '<span style="color: ' . $color . '; font-weight: bold;">[' . $level . ']</span> ';
                $html .= '<span style="color: #999;">' . $time . '</span> ';
                $html .= '<span>' . $message . '</span>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        
        $html .= '<div class="footer">';
        $html .= '<p>此报告由反向代理系统自动生成</p>';
        $html .= '<p>🔗 查看详细状态：<a href="' . $statusUrl . '">' . $statusUrl . '</a></p>';
        $html .= '<p>📧 如有问题，请联系管理员</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }
    
    /**
     * 构建报告文本
     */
    private function buildReportText($reportData)
    {
        $generatedAt = $reportData['generated_at'] ?? date('Y-m-d H:i:s');
        $totalServices = $reportData['total_services'] ?? 0;
        $healthyServices = $reportData['healthy_services'] ?? 0;
        $warningServices = $reportData['warning_services'] ?? 0;
        $unhealthyServices = $reportData['unhealthy_services'] ?? 0;
        $totalRequests = $reportData['total_requests'] ?? 0;
        $successRate = $reportData['success_rate'] ?? 0;
        $services = $reportData['services'] ?? [];
        $recentErrors = $reportData['recent_errors'] ?? [];
        
        $text = "═══════════════════════════════════════════\n";
        $text .= "       反向代理系统服务状态报告\n";
        $text .= "═══════════════════════════════════════════\n\n";
        $text .= "报告生成时间：{$generatedAt}\n\n";
        
        $text .= "【系统概要】\n";
        $text .= "──────────\n";
        $text .= "总服务数：{$totalServices}\n";
        $text .= "健康服务：{$healthyServices}\n";
        $text .= "警告服务：{$warningServices}\n";
        $text .= "失效服务：{$unhealthyServices}\n";
        $text .= "总请求数：{$totalRequests}\n";
        $text .= "成功率：{$successRate}%\n\n";
        
        $text .= "【服务详细状态】\n";
        $text .= "────────────\n";
        foreach ($services as $service) {
            $id = $service['id'] ?? '';
            $name = $service['name'] ?? '';
            $description = $service['description'] ?? '-';
            $status = $service['status_text'] ?? '未知';
            $fails = $service['fails'] ?? 0;
            $lastCheck = $service['last_check'] ?? '-';
            
            $text .= "ID: {$id}\n";
            $text .= "名称: {$name}\n";
            $text .= "描述: {$description}\n";
            $text .= "状态: {$status}\n";
            $text .= "失败次数: {$fails}\n";
            $text .= "最后检查: {$lastCheck}\n";
            $text .= "---\n";
        }
        
        $text .= "\n【最近错误日志】\n";
        $text .= "────────────\n";
        if (empty($recentErrors)) {
            $text .= "暂无错误日志\n";
        } else {
            foreach ($recentErrors as $error) {
                $time = $error['time'] ?? '';
                $level = $error['level'] ?? '';
                $message = $error['message'] ?? '';
                $text .= "[{$time}] [{$level}] {$message}\n";
            }
        }
        
        $text .= "\n────────────────────────────\n";
        $text .= "此报告由反向代理系统自动生成\n";
        $text .= "如需更多信息，请访问状态页面\n";
        $text .= "发送时间：" . date('Y-m-d H:i:s') . "\n";
        
        return $text;
    }
    
    /**
     * 格式化时长
     * @param int $seconds
     * @return string
     */
    private function formatDuration($seconds)
    {
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . '分钟' . ($seconds % 60) . '秒';
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . '小时' . $minutes . '分钟';
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return $days . '天' . $hours . '小时';
        }
    }
}