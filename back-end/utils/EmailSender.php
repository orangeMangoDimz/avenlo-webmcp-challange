<?php
/**
 * 邮件发送工具类
 * Email Sender Utility
 *
 * 支持多种发送方式:
 * 1. SMTP (推荐用于生产环境)
 * 2. PHP mail() 函数 (仅适用于开发)
 * 3. 第三方服务 (SendGrid, AWS SES, Mailgun)
 */

require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/RequestLogContext.php';
require_once __DIR__ . '/../models/EmailSentLog.php';
require_once __DIR__ . '/../services/DeveloperSettingsService.php';

class EmailSender {
    private const CONTENT_TRUNCATE_LENGTH = 2000;
    private const STACK_TRACE_TRUNCATE_LENGTH = 65000;
    private const ERROR_MESSAGE_TRUNCATE_LENGTH = 500;

    private $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/email.php';
    }

    /**
     * 发送客户通知邮件（公共方法）
     * 用于Deposit、Withdrawal、Internal Transfer等场景
     *
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $content 邮件内容（纯文本，会自动转换为HTML）
     * @param array $context 上下文信息（用于日志记录，如 ['transactionId' => 123, 'type' => 'deposit']）
     * @return bool 发送是否成功
     */
    public function sendClientNotification($to, $subject, $content, $context = []) {
        $config = require __DIR__ . '/../config/app.php';
        $logoname = $config['logoname'];
        // 将纯文本内容转换为HTML格式
        $htmlContent = nl2br(htmlspecialchars($content));
        $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #174f46; color: white; padding: 20px; text-align: center; }
                    .content { background: #f7fafc; padding: 20px; margin: 20px 0; }
                    .footer { text-align: center; color: #718096; font-size: 12px; padding: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>{$logoname}</h2>
                    </div>
                    <div class='content'>
                        {$htmlContent}
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from {$logoname}.</p>
                        <p>Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        return $this->send($to, $subject, $htmlBody, $this->buildSentLogOptionsFromContext($context));
    }

    /**
     * 发送邮件主方法
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件内容(HTML)
     * @param array $options 额外选项
     * @return bool 发送是否成功
     */
    public function send($to, $subject, $body, $options = []) {
        $success = false;
        $errorMessage = null;
        $stackTrace = null;

        if ($this->config['debug_mode']) {
            $success = true;
            $this->persistSentLog($to, $subject, $body, $options, $success, $errorMessage, $stackTrace);
            return true;
        }

        if (!(new DeveloperSettingsService())->isEmailSendingEnabled()) {
            $meta = [];
            if (isset($options['meta']) && is_array($options['meta'])) {
                $meta = $options['meta'];
            }
            $meta['skippedByDeveloperSettings'] = true;
            $options['meta'] = $meta;
            $this->persistSentLog($to, $subject, $body, $options, false, 'Skipped by developer settings', null);
            return true;
        }

        try {
            $driver = strtolower(trim((string)($this->config['driver'] ?? 'smtp')));

            switch ($driver) {
                case 'smtp':
                    $success = $this->sendViaSMTP($to, $subject, $body, $options);
                    break;
                case 'mail':
                case 'sendmail':
                    $success = $this->sendViaPhpMail($to, $subject, $body, $options);
                    break;
                case 'sendgrid':
                    $success = $this->sendViaSendGrid($to, $subject, $body, $options);
                    break;
                case 'aws':
                case 'aws_ses':
                    $success = $this->sendViaAWS($to, $subject, $body, $options);
                    break;
                case 'mailgun':
                    $success = $this->sendViaMailgun($to, $subject, $body, $options);
                    break;
                case 'azure':
                    $success = $this->sendViaAzureGraph($to, $subject, $body, $options);
                    break;
                default:
                    throw new Exception("Unsupported email driver: {$driver}");
            }

            if (!$success && $errorMessage === null) {
                $errorMessage = 'Email sending failed';
            }
        } catch (Exception $e) {
            $success = false;
            $errorMessage = $e->getMessage();
            $stackTrace = $e->getTraceAsString();
            $this->log("Email sending failed: " . $e->getMessage());
        }

        $this->persistSentLog($to, $subject, $body, $options, $success, $errorMessage, $stackTrace);
        return $success;
    }

    /**
     * 通过SMTP发送邮件（推荐方式）
     */
    private function sendViaSMTP($to, $subject, $body, $options) {
        $smtp = $this->config['smtp'];
        $from = $this->config['from'];

        // Gmail要求：发件人地址必须与SMTP用户名匹配
        if (strpos($smtp['host'], 'gmail.com') !== false) {
            $from['address'] = $smtp['username'];
        }

        // 建立SMTP连接
        $socket = @fsockopen($smtp['host'], $smtp['port'], $errno, $errstr, $smtp['timeout']);

        if (!$socket) {
            $this->log("SMTP connection failed: $errstr ($errno)");
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }

        // 读取服务器响应
        $response = $this->readResponse($socket);
        // $this->log("SMTP Connect: $response");
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new Exception("SMTP connection failed: $response");
        }

        // EHLO/HELO
        fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
        $response = $this->readResponse($socket);
        // $this->log("EHLO: $response");

        // STARTTLS (如果启用)
        if ($smtp['encryption'] === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = $this->readResponse($socket);
            // $this->log("STARTTLS: $response");
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                throw new Exception("STARTTLS failed: $response");
            }

            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                throw new Exception("Failed to enable TLS encryption");
            }

            fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
            $response = $this->readResponse($socket);
            // $this->log("EHLO after TLS: $response");
        }

        // 登录认证
        fputs($socket, "AUTH LOGIN\r\n");
        $response = $this->readResponse($socket);
        // $this->log("AUTH LOGIN: $response");

        fputs($socket, base64_encode($smtp['username']) . "\r\n");
        $response = $this->readResponse($socket);
        // $this->log("Username sent: " . substr($response, 0, 50));

        fputs($socket, base64_encode($smtp['password']) . "\r\n");
        $response = $this->readResponse($socket);
        // $this->log("Password sent: " . substr($response, 0, 50));

        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            throw new Exception("SMTP authentication failed. Check username/password: $response");
        }

        // 发件人
        fputs($socket, "MAIL FROM: <{$from['address']}>\r\n");
        $response = $this->readResponse($socket);
        // $this->log("MAIL FROM: $response");
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("MAIL FROM failed: $response");
        }

        // 收件人
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        $response = $this->readResponse($socket);
        // $this->log("RCPT TO: $response");
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("RCPT TO failed: $response");
        }

        // 邮件数据
        fputs($socket, "DATA\r\n");
        $response = $this->readResponse($socket);
        // $this->log("DATA: $response");
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            throw new Exception("DATA command failed: $response");
        }

        // 邮件头和内容
        $headers = $this->buildHeaders($from, $to, $subject);
        $message = $headers . "\r\n" . $body . "\r\n.\r\n";
        fputs($socket, $message);
        $response = $this->readResponse($socket);
        // $this->log("Message sent: $response");

        // 退出
        fputs($socket, "QUIT\r\n");
        $this->readResponse($socket);
        fclose($socket);

        if (substr($response, 0, 3) != '250') {
            throw new Exception("Email sending failed: $response");
        }

        // $this->log("✓ Email sent successfully to: $to");
        return true;
    }

    /**
     * 读取SMTP响应（支持多行）
     */
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // 如果行的第4个字符是空格，表示这是最后一行
            if (isset($line[3]) && $line[3] == ' ') {
                break;
            }
        }
        return trim($response);
    }

    /**
     * 使用PHP mail()函数发送（不推荐，容易进垃圾箱）
     */
    private function sendViaPhpMail($to, $subject, $body, $options) {
        $from = $this->config['from'];

        $headers = "From: {$from['name']} <{$from['address']}>\r\n";
        $headers .= "Reply-To: {$from['address']}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $success = mail($to, $subject, $body, $headers);

        if ($success) {
            $this->log("Email sent via PHP mail() to: $to");
        } else {
            $this->log("Email sending failed via PHP mail()");
        }

        return $success;
    }

    /**
     * 通过SendGrid发送
     */
    private function sendViaSendGrid($to, $subject, $body, $options) {
        $apiKey = $this->config['sendgrid']['api_key'];
        $from = $this->config['from'];

        $data = [
            'personalizations' => [
                ['to' => [['email' => $to]]]
            ],
            'from' => [
                'email' => $from['address'],
                'name' => $from['name']
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $body
                ]
            ]
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 200 && $statusCode < 300) {
            $this->log("Email sent via SendGrid to: $to");
            return true;
        } else {
            $this->log("SendGrid sending failed: $response");
            return false;
        }
    }

    /**
     * 通过AWS SES发送
     */
    private function sendViaAWS($to, $subject, $body, $options) {
        // AWS SES 实现 (需要 AWS SDK)
        // 这里提供基础框架，实际使用需要安装 aws/aws-sdk-php
        throw new Exception("AWS SES not implemented yet. Please install aws-sdk-php");
    }

    /**
     * 通过Mailgun发送
     */
    private function sendViaMailgun($to, $subject, $body, $options) {
        $domain = $this->config['mailgun']['domain'];
        $apiKey = $this->config['mailgun']['api_key'];
        $from = $this->config['from'];

        $data = [
            'from' => "{$from['name']} <{$from['address']}>",
            'to' => $to,
            'subject' => $subject,
            'html' => $body
        ];

        $ch = curl_init("https://api.mailgun.net/v3/{$domain}/messages");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$apiKey}");

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode == 200) {
            $this->log("Email sent via Mailgun to: $to");
            return true;
        } else {
            $this->log("Mailgun sending failed: $response");
            return false;
        }
    }

    /**
     * 通过 Azure Graph 发送
     */
    private function sendViaAzureGraph($to, $subject, $body, $options) {
        $azure = $this->config['azure'] ?? [];
        $from = $this->config['from'];
        $sender = trim((string)($azure['sender'] ?? $from['address']));

        if ($sender === '') {
            throw new Exception('Azure Graph sender is not configured');
        }

        $this->log("[Azure] sendMail start | to={$to} | sender={$sender} | subject=" . $this->shortenForLog($subject, 120));
        $this->stdout("[Azure] sendMail start | to={$to} | sender={$sender} | subject=" . $this->shortenForLog($subject, 120));
        $token = $this->getAzureGraphAccessToken($azure);

        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body
                ],
                'from' => [
                    'emailAddress' => [
                        'address' => $sender,
                        'name' => $from['name']
                    ]
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to
                        ]
                    ]
                ]
            ],
            'saveToSentItems' => true
        ];

        $baseUrl = rtrim((string)($azure['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
        $url = $baseUrl . '/users/' . rawurlencode($sender) . '/sendMail';

        $response = $this->executeJsonRequest(
            $url,
            [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            json_encode($payload),
            (int)($azure['timeout'] ?? 30),
            [
                'label' => 'Azure Graph sendMail',
                'log_payload' => [
                    'to' => $to,
                    'sender' => $sender,
                    'subject' => $subject
                ]
            ]
        );

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $this->log("[Azure] sendMail success | status={$response['status']} | stdout=" . $this->shortenForLog($response['body'], 300));
            $this->stdout("[Azure] sendMail success | status={$response['status']} | stdout=" . $this->shortenForLog($response['body'], 300));
            $this->log("Email sent via Azure Graph to: $to");
            return true;
        }

        $this->log("[Azure] sendMail failed | status={$response['status']} | stdout=" . $this->shortenForLog($response['body'], 500));
        $this->stdout("[Azure] sendMail failed | status={$response['status']} | stdout=" . $this->shortenForLog($response['body'], 500));
        $this->log("Azure Graph sending failed: HTTP {$response['status']} - {$response['body']}");
        return false;
    }

    /**
     * 获取 Azure Graph access token
     */
    private function getAzureGraphAccessToken($azure) {
        $tenantId = trim((string)($azure['tenant_id'] ?? ''));
        $clientId = trim((string)($azure['client_id'] ?? ''));
        $clientSecret = trim((string)($azure['client_secret'] ?? ''));
        $scope = trim((string)($azure['scope'] ?? 'https://graph.microsoft.com/.default'));

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
            throw new Exception('Azure Graph tenant_id/client_id/client_secret is not fully configured');
        }

        $url = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
        $payload = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => $scope,
            'grant_type' => 'client_credentials'
        ]);

        $response = $this->executeJsonRequest(
            $url,
            ['Content-Type: application/x-www-form-urlencoded'],
            $payload,
            (int)($azure['timeout'] ?? 30),
            [
                'label' => 'Azure Graph token',
                'log_payload' => [
                    'tenant_id' => $tenantId,
                    'client_id' => $clientId,
                    'scope' => $scope
                ],
                'sensitive_body' => true
            ]
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $tokenError = $this->extractAzureErrorSummary($response['body']);
            $this->stdout("[Azure Graph token] error_summary | " . $tokenError);
            $this->log("[Azure Graph token] error_summary | " . $tokenError);
            throw new Exception("Azure token request failed: HTTP {$response['status']} - {$response['body']}");
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new Exception('Azure token response missing access_token');
        }

        return $data['access_token'];
    }

    /**
     * 执行 HTTP 请求并返回响应
     */
    private function executeJsonRequest($url, $headers, $payload, $timeout = 30, $logOptions = []) {
        $label = (string)($logOptions['label'] ?? 'HTTP request');
        $logPayload = $logOptions['log_payload'] ?? null;
        $sensitiveBody = !empty($logOptions['sensitive_body']);

        if ($logPayload !== null) {
            $this->log("[{$label}] request | " . $this->stringifyLogContext($logPayload));
            $this->stdout("[{$label}] request | " . $this->stringifyLogContext($logPayload));
        } else {
            $this->log("[{$label}] request | url={$url}");
            $this->stdout("[{$label}] request | url={$url}");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            $this->log("[{$label}] failed | status={$statusCode} | stdout=" . $this->shortenForLog($curlError, 300));
            $this->stdout("[{$label}] failed | status={$statusCode} | stdout=" . $this->shortenForLog($curlError, 300));
            throw new Exception('cURL request failed: ' . $curlError);
        }

        $stdout = $sensitiveBody ? '[hidden]' : $this->shortenForLog($responseBody, 500);
        $this->log("[{$label}] response | status={$statusCode} | stdout={$stdout}");
        $this->stdout("[{$label}] response | status={$statusCode} | stdout={$stdout}");

        return [
            'status' => $statusCode,
            'body' => $responseBody
        ];
    }

    /**
     * 构建邮件头
     */
    private function buildHeaders($from, $to, $subject) {
        $headers = "From: {$from['name']} <{$from['address']}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        return $headers;
    }

    private function shortenForLog($value, $maxLength = 300) {
        $text = trim((string)$value);
        if ($text === '') {
            return '[empty]';
        }

        $text = preg_replace('/\s+/', ' ', $text);
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength) . '...';
    }

    private function stringifyLogContext($context) {
        if (is_array($context) || is_object($context)) {
            return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)$context;
    }

    private function stdout($message) {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . (string)$message . PHP_EOL;

        if (defined('STDOUT')) {
            @fwrite(STDOUT, $line);
            return;
        }

        @file_put_contents('php://stdout', $line, FILE_APPEND);
    }

    private function extractAzureErrorSummary($responseBody) {
        $data = json_decode((string)$responseBody, true);
        if (!is_array($data)) {
            return 'unable to parse response body';
        }

        $parts = [];

        if (!empty($data['error'])) {
            $parts[] = 'error=' . $data['error'];
        }

        if (!empty($data['error_description'])) {
            $parts[] = 'error_description=' . $this->shortenForLog($data['error_description'], 300);
        }

        if (!empty($data['error_codes']) && is_array($data['error_codes'])) {
            $parts[] = 'error_codes=' . implode(',', array_map('strval', $data['error_codes']));
        }

        if (!empty($data['trace_id'])) {
            $parts[] = 'trace_id=' . $data['trace_id'];
        }

        if (!empty($data['correlation_id'])) {
            $parts[] = 'correlation_id=' . $data['correlation_id'];
        }

        if (!empty($data['timestamp'])) {
            $parts[] = 'timestamp=' . $data['timestamp'];
        }

        if (empty($parts)) {
            return 'no structured Azure error fields found';
        }

        return implode(' | ', $parts);
    }

    /**
     * 发送邮箱验证邮件
     */
    public function sendVerificationEmail($to, $userName, $verificationLink, $expiryHours = 24) {
        $settings = new EmailVerificationSettings();
        $config = $settings->getSettings();

        $subject = $config['emailSubject'];
        $template = $config['emailTemplate'];

        // 替换模板占位符
        $body = str_replace(
            ['{user_name}', '{verification_link}', '{expiry_hours}'],
            [$userName, $verificationLink, $expiryHours],
            $template
        );

        // 转换换行符为HTML
        $body = nl2br($body);

        // 包装在HTML模板中
        $body = $this->wrapInTemplate($body, 'Email Verification');

        return $this->send($to, $subject, $body);
    }

    /**
     * 发送密码重置邮件
     */
    public function sendPasswordResetEmail($to, $userName, $resetLink, $expiryHours = 1) {
        $config = require __DIR__ . '/../config/app.php';
        $logoname = $config['logoname'];
        $subject = 'Reset Your Password';
        $body = "Dear {$userName},\n\n";
        $body .= "You have requested to reset your password. Click the link below to reset it:\n\n";
        $body .= "{$resetLink}\n\n";
        $body .= "This link will expire in {$expiryHours} hour(s).\n\n";
        $body .= "If you did not request this, please ignore this email.\n\n";
        $body .= "Best regards,\nThe {$logoname} Team";

        $body = nl2br($body);
        $body = $this->wrapInTemplate($body, 'Password Reset');

        return $this->send($to, $subject, $body);
    }

    /**
     * 发送 MT5 账户凭据邮件（不生成密码，密码由上层业务提供）
     *
     * @param string $to 收件人邮箱
     * @param string $userName 收件人名称
     * @param string|int $accountLogin MT5 登录号
     * @param array $options 必填：mainPassword, investPassword；可选：platformName, serverName, subject
     * @return array ['success'=>bool,'mainPassword'=>string,'investPassword'=>string,'subject'=>string]
     */
    public function sendMt5CredentialsEmail($to, $userName, $accountLogin, $options = []) {
        $platformName = (string)($options['platformName'] ?? 'MetaTrader 5');
        $serverName = (string)($options['serverName'] ?? '');
        $subject = (string)($options['subject'] ?? 'Your MT5 Trading Account Credentials');

        $mainPassword = (string)($options['mainPassword'] ?? '');
        $investPassword = (string)($options['investPassword'] ?? '');
        if ($mainPassword === '' || $investPassword === '') {
            throw new InvalidArgumentException('sendMt5CredentialsEmail requires mainPassword and investPassword');
        }

        $safeUserName = htmlspecialchars($userName ?: 'Client', ENT_QUOTES, 'UTF-8');
        $safePlatformName = htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8');
        $safeLogin = htmlspecialchars((string)$accountLogin, ENT_QUOTES, 'UTF-8');
        $safeServerName = htmlspecialchars($serverName, ENT_QUOTES, 'UTF-8');
        $safeMainPassword = htmlspecialchars($mainPassword, ENT_QUOTES, 'UTF-8');
        $safeInvestPassword = htmlspecialchars($investPassword, ENT_QUOTES, 'UTF-8');

        $serverLine = '';
        if ($safeServerName !== '') {
            $serverLine = "<tr><td style=\"padding: 8px 0; color: #4a5568;\"><strong>Server:</strong> {$safeServerName}</td></tr>";
        }

        $content = <<<HTML
<p style="margin: 0 0 16px 0;">Dear {$safeUserName},</p>
<p style="margin: 0 0 20px 0;">Your {$safePlatformName} trading account has been created successfully. Please find your login credentials below:</p>

<table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #fafcff; margin: 0 0 20px 0;">
    <tr>
        <td style="padding: 18px 20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="padding: 0 0 8px 0; color: #1a202c;"><strong>Platform:</strong> {$safePlatformName}</td></tr>
                {$serverLine}
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Account Login:</strong> {$safeLogin}</td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Main Password:</strong> <span style="font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;">{$safeMainPassword}</span></td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Investor Password:</strong> <span style="font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;">{$safeInvestPassword}</span></td></tr>
            </table>
        </td>
    </tr>
</table>

<div style="border-left: 4px solid #ed8936; background: #fffaf0; padding: 12px 14px; margin: 0 0 18px 0;">
    <p style="margin: 0; color: #744210; font-size: 14px;">
        Security reminder: Please change your main password after first login and keep your investor password for read-only access only.
    </p>
</div>

<p style="margin: 0;">If you did not request this account, please contact support immediately.</p>
HTML;

        $body = $this->wrapInTemplate($content, 'Trading Account Credentials');
        $success = $this->send($to, $subject, $body);

        $logContext = [
            'email' => $to,
            'subject' => $subject,
            'type' => 'mt5_credentials',
            'accountLogin' => (string)$accountLogin,
            'success' => $success ? 1 : 0
        ];
        $this->log('MT5 credentials email ' . ($success ? 'sent' : 'failed') . ': ' . json_encode($logContext));

        return [
            'success' => $success,
            'mainPassword' => $mainPassword,
            'investPassword' => $investPassword,
            'subject' => $subject
        ];
    }

    /**
     * 发送 MT4 开户凭据邮件。MT4 只有一个主密码（无 investor 密码），所以不复用 MT5 那套。
     *
     * @param array $options 必填：mainPassword；可选：platformName, serverName, subject
     * @return array ['success'=>bool,'mainPassword'=>string,'subject'=>string]
     */
    public function sendMt4CredentialsEmail($to, $userName, $accountLogin, $options = []) {
        $platformName = (string)($options['platformName'] ?? 'MetaTrader 4');
        $serverName = (string)($options['serverName'] ?? '');
        $subject = (string)($options['subject'] ?? 'Your MT4 Trading Account Credentials');

        $mainPassword = (string)($options['mainPassword'] ?? '');
        if ($mainPassword === '') {
            throw new InvalidArgumentException('sendMt4CredentialsEmail requires mainPassword');
        }

        $safeUserName = htmlspecialchars($userName ?: 'Client', ENT_QUOTES, 'UTF-8');
        $safePlatformName = htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8');
        $safeLogin = htmlspecialchars((string)$accountLogin, ENT_QUOTES, 'UTF-8');
        $safeServerName = htmlspecialchars($serverName, ENT_QUOTES, 'UTF-8');
        $safeMainPassword = htmlspecialchars($mainPassword, ENT_QUOTES, 'UTF-8');

        $serverLine = '';
        if ($safeServerName !== '') {
            $serverLine = "<tr><td style=\"padding: 8px 0; color: #4a5568;\"><strong>Server:</strong> {$safeServerName}</td></tr>";
        }

        $content = <<<HTML
<p style="margin: 0 0 16px 0;">Dear {$safeUserName},</p>
<p style="margin: 0 0 20px 0;">Your {$safePlatformName} trading account has been created successfully. Please find your login credentials below:</p>

<table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #fafcff; margin: 0 0 20px 0;">
    <tr>
        <td style="padding: 18px 20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="padding: 0 0 8px 0; color: #1a202c;"><strong>Platform:</strong> {$safePlatformName}</td></tr>
                {$serverLine}
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Account Login:</strong> {$safeLogin}</td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Main Password:</strong> <span style="font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;">{$safeMainPassword}</span></td></tr>
            </table>
        </td>
    </tr>
</table>

<div style="border-left: 4px solid #ed8936; background: #fffaf0; padding: 12px 14px; margin: 0 0 18px 0;">
    <p style="margin: 0; color: #744210; font-size: 14px;">
        Security reminder: Please change your main password after first login.
    </p>
</div>

<p style="margin: 0;">If you did not request this account, please contact support immediately.</p>
HTML;

        $body = $this->wrapInTemplate($content, 'Trading Account Credentials');
        $success = $this->send($to, $subject, $body);

        $logContext = [
            'email' => $to,
            'subject' => $subject,
            'type' => 'mt4_credentials',
            'accountLogin' => (string)$accountLogin,
            'success' => $success ? 1 : 0
        ];
        $this->log('MT4 credentials email ' . ($success ? 'sent' : 'failed') . ': ' . json_encode($logContext));

        return [
            'success' => $success,
            'mainPassword' => $mainPassword,
            'subject' => $subject
        ];
    }

    /**
     * 发送交易账户密码重置邮件（HTML，样式与开户凭据邮件一致）。
     *
     * @param array $options 可选：platformName, subject
     * @return array ['success'=>bool,'subject'=>string]
     */
    public function sendTradingPasswordResetEmail($to, $userName, $accountLogin, $newPassword, $options = []) {
        $platformName = (string)($options['platformName'] ?? 'Trading Platform');
        $subject = (string)($options['subject'] ?? 'Your Trading Account Password Was Reset');

        if ((string)$newPassword === '') {
            throw new InvalidArgumentException('sendTradingPasswordResetEmail requires newPassword');
        }

        $safeUserName = htmlspecialchars($userName ?: 'Client', ENT_QUOTES, 'UTF-8');
        $safePlatformName = htmlspecialchars($platformName, ENT_QUOTES, 'UTF-8');
        $safeLogin = htmlspecialchars((string)$accountLogin, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars((string)$newPassword, ENT_QUOTES, 'UTF-8');

        $content = <<<HTML
<p style="margin: 0 0 16px 0;">Dear {$safeUserName},</p>
<p style="margin: 0 0 20px 0;">The password for your {$safePlatformName} trading account has been reset. Please use the new password below to log in:</p>

<table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e2e8f0; border-radius: 8px; background: #fafcff; margin: 0 0 20px 0;">
    <tr>
        <td style="padding: 18px 20px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td style="padding: 0 0 8px 0; color: #1a202c;"><strong>Platform:</strong> {$safePlatformName}</td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>Account Login:</strong> {$safeLogin}</td></tr>
                <tr><td style="padding: 8px 0; color: #1a202c;"><strong>New Password:</strong> <span style="font-family: monospace; background: #edf2f7; padding: 2px 6px; border-radius: 4px;">{$safePassword}</span></td></tr>
            </table>
        </td>
    </tr>
</table>

<div style="border-left: 4px solid #ed8936; background: #fffaf0; padding: 12px 14px; margin: 0 0 18px 0;">
    <p style="margin: 0; color: #744210; font-size: 14px;">
        Security reminder: Please change your password after logging in and keep it safe.
    </p>
</div>

<p style="margin: 0;">If you did not request this change, please contact support immediately.</p>
HTML;

        $body = $this->wrapInTemplate($content, 'Trading Account Password Reset');
        $success = $this->send($to, $subject, $body);

        $logContext = [
            'email' => $to,
            'subject' => $subject,
            'type' => 'trading_password_reset',
            'accountLogin' => (string)$accountLogin,
            'success' => $success ? 1 : 0
        ];
        $this->log('Trading password reset email ' . ($success ? 'sent' : 'failed') . ': ' . json_encode($logContext));

        return [
            'success' => $success,
            'subject' => $subject
        ];
    }

    /**
     * 包装邮件内容在HTML模板中
     */
    private function wrapInTemplate($content, $title = 'Notification') {
        $config = require __DIR__ . '/../config/app.php';
        $logoname = $config['logoname'];
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: #174f46; padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700;">{$logoname}</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; opacity: 0.9; font-size: 16px;">Trading Platform</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px; color: #333333; line-height: 1.6;">
                            {$content}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0; color: #718096; font-size: 14px;">
                                © 2025 {$logoname}. All rights reserved.
                            </p>
                            <p style="margin: 10px 0 0 0; color: #a0aec0; font-size: 12px;">
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function buildSentLogOptionsFromContext(array $context) {
        if ($context === []) {
            return [];
        }

        $options = [];
        if (!empty($context['relatedType'])) {
            $options['relatedType'] = (string) $context['relatedType'];
        } elseif (!empty($context['type'])) {
            $options['relatedType'] = (string) $context['type'];
        }

        $idKeys = ['relatedId', 'depositId', 'withdrawalId', 'transferId', 'applicationId', 'transactionId'];
        foreach ($idKeys as $key) {
            if (!isset($context[$key]) || $context[$key] === '' || $context[$key] === null) {
                continue;
            }
            $options['relatedId'] = (int) $context[$key];
            break;
        }

        $options['meta'] = $context;
        return $options;
    }

    private function resolveProvider(array $options) {
        $override = $options['provider'] ?? null;
        if (in_array($override, ['api', 'swoole'], true)) {
            return $override;
        }

        if (class_exists('RequestLogContext')) {
            $service = RequestLogContext::get()['service'] ?? null;
            if (in_array($service, ['api', 'swoole'], true)) {
                return $service;
            }
        }

        return PHP_SAPI === 'cli' ? 'swoole' : 'api';
    }

    private function persistSentLog($to, $subject, $body, array $options, $success, $errorMessage = null, $stackTrace = null) {
        try {
            $content = trim((string) $body);
            if ($content === '') {
                $content = null;
            } else {
                $content = mb_substr($content, 0, self::CONTENT_TRUNCATE_LENGTH);
            }

            $meta = $options['meta'] ?? null;
            if (is_array($meta) || is_object($meta)) {
                $meta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($meta !== null) {
                $meta = (string) $meta;
            }

            $relatedId = $options['relatedId'] ?? null;
            if ($relatedId !== null && $relatedId !== '') {
                $relatedId = (int) $relatedId;
                if ($relatedId <= 0) {
                    $relatedId = null;
                }
            } else {
                $relatedId = null;
            }

            $relatedType = isset($options['relatedType']) ? trim((string) $options['relatedType']) : '';
            if ($relatedType === '') {
                $relatedType = null;
            } else {
                $relatedType = mb_substr($relatedType, 0, 64);
            }

            $row = [
                'sender' => mb_substr((string) ($this->config['from']['address'] ?? ''), 0, 255),
                'recipient' => mb_substr((string) $to, 0, 255),
                'subject' => mb_substr((string) $subject, 0, 500),
                'content' => $content,
                'provider' => $this->resolveProvider($options),
                'status' => $success ? 'success' : 'failed',
                'errorMessage' => $errorMessage !== null && $errorMessage !== ''
                    ? mb_substr((string) $errorMessage, 0, self::ERROR_MESSAGE_TRUNCATE_LENGTH)
                    : null,
                'stackTrace' => $stackTrace !== null && $stackTrace !== ''
                    ? mb_substr((string) $stackTrace, 0, self::STACK_TRACE_TRUNCATE_LENGTH)
                    : null,
                'relatedType' => $relatedType,
                'relatedId' => $relatedId,
                'meta' => $meta,
                'createdAt' => gmdate('Y-m-d H:i:s'),
            ];

            (new EmailSentLog())->create($row);
        } catch (Throwable $e) {
        }
    }

    private function log($message) {
    }
}

// 需要引入的Model类
require_once __DIR__ . '/../models/EmailVerificationSettings.php';
