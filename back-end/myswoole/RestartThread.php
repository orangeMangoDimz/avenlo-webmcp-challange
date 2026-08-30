<?php
require_once __DIR__ . '/../utils/Logger.php';
//error_reporting(0);
use OSS\Core\OssException;
use OSS\OssClient;
use think\Exception;

class RestartThread
{
    var $queryCount = 0;
    var $conn;
    var $result;
    var $emailSender = null;
    var $dbConfig = [];

    public function __construct(){
        if(!function_exists('mysqli_connect')){
            die('服务器PHP不支持MySql数据库');
        }

        $this->dbConfig = require __DIR__ . '/../config/database.php';

        $host = $this->dbConfig['host'] ?? '127.0.0.1';
        $port = (int)($this->dbConfig['port'] ?? 3306);
        $database = $this->dbConfig['database'] ?? '';
        $username = $this->dbConfig['username'] ?? '';
        $password = $this->dbConfig['password'] ?? '';
        $charset = $this->dbConfig['charset'] ?? 'utf8mb4';

        $this->conn = mysqli_connect($host, $username, $password, null, $port) or die("连接数据库失败,可能是数据库用户名或密码错误");
        mysqli_query($this->conn, "SET NAMES {$charset}");
        // 设置时区，确保与数据库时间一致
        mysqli_query($this->conn, "SET time_zone = '+00:00'");
        mysqli_select_db($this->conn, $database) OR die("未找到指定数据库");
    }

    /**
     * 获取到期待发送的通知/邮件任务
     */
    public function getDueNotificationTasks()
    {
        try {
            // 查询条件：scheduledAt 小于等于当前时间（允许1分钟误差，避免时间精度问题）
            $sql = "SELECT id, clientId, subject, scheduledAt, status, NOW() as currentTime
                    FROM clientNotifications
                    WHERE scheduleType = 'scheduled'
                      AND status = 'pending'
                      AND scheduledAt IS NOT NULL
                      AND scheduledAt <= DATE_ADD(NOW(), INTERVAL 1 MINUTE)
                    ORDER BY scheduledAt ASC
                    LIMIT 20";

            $result = $this->fetch_all_assoc($sql);

            return $result;
        } catch (\Exception $e) {
            Logger::error("[" . date('Y-m-d H:i:s') . "] 获取待发送通知失败: " . $e->getMessage());
            Logger::error("错误堆栈: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * 处理到时间的定时通知/邮件任务
     */
    public function processScheduledNotification($notificationId)
    {
        $notificationId = intval($notificationId);
        if ($notificationId <= 0) {
            return "无效的通知ID: {$notificationId}";
        }

        $now = date('Y-m-d H:i:s');

        try {
            // 更新通知状态为发送中（仅处理pending状态的任务）
            $updateSql = "UPDATE clientNotifications
                          SET status = 'sending', updatedAt = '{$now}'
                          WHERE id = {$notificationId} AND status = 'pending'";
            $this->query($updateSql);

            if (mysqli_affected_rows($this->conn) === 0) {
                return "通知 {$notificationId} 当前无需处理";
            }

            // 获取通知详情
            $notification = $this->once_fetch_assoc("SELECT * FROM clientNotifications WHERE id = {$notificationId} LIMIT 1");
            if (!$notification) {
                return "通知不存在: {$notificationId}";
            }

            $clientId = intval($notification['clientId']);
            $client = $this->once_fetch_assoc("SELECT * FROM clientUsers WHERE id = {$clientId} LIMIT 1");
            if (!$client) {
                $this->query("UPDATE clientNotifications SET status = 'failed', updatedAt = '{$now}' WHERE id = {$notificationId}");
                return "通知 {$notificationId} 的客户不存在";
            }

            // 将相关投递记录标记为发送中
            $this->query("UPDATE clientNotificationDeliveries SET status = 'sending' WHERE notificationId = {$notificationId} AND status = 'pending'");

            $deliveries = $this->fetch_all_assoc("SELECT * FROM clientNotificationDeliveries WHERE notificationId = {$notificationId}");
            if (empty($deliveries)) {
                $this->query("UPDATE clientNotifications SET status = 'failed', updatedAt = '{$now}' WHERE id = {$notificationId}");
                return "通知 {$notificationId} 没有投递渠道";
            }

            $successfulDeliveries = 0;
            $failedDeliveries = 0;
            $template = null;

            // 预加载邮件模板（如有）：统一从 emailTemplates 表读取（与后台 Email Settings 一致）
            if (!empty($notification['emailTemplate'])) {
                $templateKey = $this->escape($notification['emailTemplate']);
                $row = $this->once_fetch_assoc("SELECT emailSubject, emailBody FROM emailTemplates WHERE templateKey = '{$templateKey}' AND isActive = 1 LIMIT 1");
                if ($row) {
                    $template = [
                        'subject' => $row['emailSubject'] ?? '',
                        'body' => $row['emailBody'] ?? ''
                    ];
                } else {
                    $template = null;
                }
            }

            foreach ($deliveries as $delivery) {
                if ($delivery['status'] === 'sent') {
                    $successfulDeliveries++;
                    continue;
                }

                if ($delivery['channel'] === 'system') {
                    if ($this->deliverSystemNotification($notification, $client, $delivery)) {
                        $successfulDeliveries++;
                    } else {
                        $failedDeliveries++;
                    }
                } elseif ($delivery['channel'] === 'email') {
                    if ($this->deliverEmailNotification($notification, $client, $delivery, $template)) {
                        $successfulDeliveries++;
                    } else {
                        $failedDeliveries++;
                    }
                }
            }

            $finalStatus = $successfulDeliveries > 0 ? 'sent' : 'failed';
            $finalUpdate = "UPDATE clientNotifications
                            SET status = '{$finalStatus}', updatedAt = '{$now}'
                            WHERE id = {$notificationId}";
            $this->query($finalUpdate);

            return "通知 {$notificationId} 处理完成: 成功 {$successfulDeliveries} 条, 失败 {$failedDeliveries} 条";
        } catch (\Exception $e) {
            $errorRaw = substr($e->getMessage(), 0, 500);
            $error = $this->escape($errorRaw);
            $this->query("UPDATE clientNotifications SET status = 'failed', updatedAt = '{$now}' WHERE id = {$notificationId}");
            $this->query("UPDATE clientNotificationDeliveries SET status = 'failed', errorMessage = '{$error}' WHERE notificationId = {$notificationId} AND status != 'sent'");
            return "通知 {$notificationId} 处理异常: " . $e->getMessage();
        }
    }

    /**
     * 发送系统通知
     */
    private function deliverSystemNotification($notification, $client, $delivery)
    {
        $now = date('Y-m-d H:i:s');
        try {
            $subject = trim($notification['subject']);
            $message = $this->sanitizeSystemMessage($notification['message']);

            $subjectEscaped = $this->escape($subject);
            $messageEscaped = $this->escape($message);

            $sql = "INSERT INTO clientSystemNotifications
                    (notificationId, clientId, subject, message, isRead, createdAt)
                    VALUES ({$notification['id']}, {$client['id']}, '{$subjectEscaped}', '{$messageEscaped}', 0, '{$now}')";
            $this->query($sql);

            $this->markDeliverySuccess($delivery['id'], $now);
            return true;
        } catch (\Exception $e) {
            $this->markDeliveryFailure($delivery['id'], $e->getMessage());
            return false;
        }
    }

    /**
     * 发送邮件通知
     */
    private function deliverEmailNotification($notification, $client, $delivery, $template = null)
    {
        $now = date('Y-m-d H:i:s');

        try {
            if (empty($client['email'])) {
                $this->markDeliveryFailure($delivery['id'], 'Client email address is empty.');
                return false;
            }

            $emailSender = $this->getEmailSender();
            $payload = $this->buildEmailPayload($notification, $client, $template);

            $result = $emailSender->send($client['email'], $payload['subject'], $payload['body']);
            if ($result) {
                $this->markDeliverySuccess($delivery['id'], $now);
                return true;
            }

            $this->markDeliveryFailure($delivery['id'], 'Email sending failed. Please check email configuration.');
            return false;
        } catch (\Exception $e) {
            $this->markDeliveryFailure($delivery['id'], $e->getMessage());
            return false;
        }
    }

    /**
     * 构建邮件内容
     */
    private function buildEmailPayload($notification, $client, $template = null)
    {
        $rawSubject = trim($notification['subject']);
        $rawMessage = $notification['message'];

        $finalSubject = $this->replacePlaceholders($rawSubject, $client, $rawMessage, false);
        $safeMessageHtml = nl2br(htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8'));
        $finalBody = '<p>' . $safeMessageHtml . '</p>';

        if ($template) {
            $templateSubject = $template['subject'] ?? $finalSubject;
            $finalSubject = $this->replacePlaceholders($templateSubject, $client, $rawMessage, false);

            $templateBody = $template['body'] ?? '';
            if ($templateBody !== '') {
                $finalBody = $this->replacePlaceholders($templateBody, $client, $rawMessage, true);

                if (strpos($templateBody, '{{message}}') === false) {
                    $finalBody .= '<p>' . $safeMessageHtml . '</p>';
                }
            }
        }

        return [
            'subject' => $finalSubject,
            'body' => $finalBody
        ];
    }

    /**
     * 获取 EmailSender 实例
     */
    private function getEmailSender()
    {
        if ($this->emailSender === null) {
            require_once __DIR__ . '/../utils/EmailSender.php';
            $this->emailSender = new EmailSender();
        }

        return $this->emailSender;
    }

    /**
     * 替换占位符
     */
    private function replacePlaceholders($text, $client, $message, $isHtml)
    {
        $fullName = trim(($client['firstName'] ?? '') . ' ' . ($client['lastName'] ?? ''));
        $replacements = [
            '{{firstName}}' => $client['firstName'] ?? '',
            '{{lastName}}' => $client['lastName'] ?? '',
            '{{fullName}}' => $fullName,
            '{{email}}' => $client['email'] ?? '',
            '{{clientId}}' => $client['id'] ?? '',
            '{{now}}' => date('Y-m-d H:i'),
        ];

        if ($isHtml) {
            $replacements['{{message}}'] = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        } else {
            $plain = trim(preg_replace('/\s+/', ' ', $message));
            $replacements['{{message}}'] = $plain;
        }

        return strtr($text, $replacements);
    }

    /**
     * 清理系统通知内容
     */
    private function sanitizeSystemMessage($message)
    {
        $clean = strip_tags($message);
        $clean = preg_replace('/\s+/', ' ', $clean);
        return trim($clean);
    }

    /**
     * 标记投递成功
     */
    private function markDeliverySuccess($deliveryId, $sentAt = null)
    {
        $sentAt = $sentAt ?? date('Y-m-d H:i:s');
        $sql = "UPDATE clientNotificationDeliveries
                SET status = 'sent', sentAt = '{$sentAt}', errorMessage = NULL
                WHERE id = {$deliveryId}";
        $this->query($sql);
    }

    /**
     * 标记投递失败
     */
    private function markDeliveryFailure($deliveryId, $message)
    {
        $errorRaw = substr($message ?? 'Unknown error', 0, 500);
        $error = $this->escape($errorRaw);
        $sql = "UPDATE clientNotificationDeliveries
                SET status = 'failed', errorMessage = '{$error}'
                WHERE id = {$deliveryId}";
        $this->query($sql);
    }

    /**
     * 转义 SQL 字符串
     */
    private function escape($value)
    {
        if ($value === null) {
            $value = '';
        }
        return mysqli_real_escape_string($this->conn, (string)$value);
    }





    function __destruct() {
        $this->close();
    }
    function close(){
        return mysqli_close($this->conn);
    }

    function changeCode($code='utf8')
    {
        mysqli_query($this->conn, "SET NAMES {$code}");
    }

    function query($sql){
        $this->result = mysqli_query($this->conn, $sql);
        $this->queryCount++;
        if (!$this->result){
            die("SQL语句执行错误：$sql <br />".$this->geterror($sql));
        }else{
            return $this->result;
        }
    }
    function fetch_all_array($sql){
        $all_array = array();
        $query = $this->query($sql);
        while($list_item = $this->fetch_array($query)){
            $all_array[] = $list_item;
        }
        return $all_array;
    }
    function fetch_all_row($sql){
        $all_row = array();
        $query = $this->query($sql);
        while($list_item = $this -> fetch_row($query)){
            $all_row[] = $list_item;
        }
        return $all_row;
    }
    function fetch_array($query){
        return mysqli_fetch_array($query);
    }
    function once_fetch_array($sql){
        $this->result = $this->query($sql);
        return $this->fetch_array($this->result);
    }
    function fetch_row($query){
        return mysqli_fetch_row($query);
    }
    function fetch_all_assoc($sql,$max=0){
        $all_array = array();
        $query = $this->query($sql);
        $current_index=0;
        while($list_item = $this->fetch_assoc($query)){
            $current_index ++;
            if($current_index > $max && $max != 0){
                break;
            }
            $all_array[] = $list_item;
        }
        return $all_array;
    }
    function fetch_assoc($query){
        return mysqli_fetch_assoc($query);
    }
    function once_fetch_assoc($sql){
        $list 	= $this->query($sql);
        $list_array = $this->fetch_assoc($list);
        return $list_array;
    }
    function num_rows($query){
        return mysqli_num_rows($query);
    }
    function once_num_rows($sql){
        $query=$this->query($sql);
        return mysqli_num_rows($query);
    }
    function num_fields($query){
        return mysqli_num_fields($query);
    }
    function insert_id(){
        return mysqli_insert_id($this->conn);
    }
    function insertArr($arrData,$table,$where='',$ignore=''){
        $Item = array();
        foreach($arrData as $key=>$data){
            $Item[] = "`$key`='$data'";
        }
        $intStr = implode(',',$Item);
        $this->query("insert $ignore into $table  SET $intStr $where");
        return mysqli_insert_id($this->conn);
    }
    function updateArr($arrData,$table,$where=''){
        $Item = array();
        foreach($arrData as $key => $date)
        {
            $Item[] = "`$key`=\"$date\"";
        }
        $upStr = implode(',',$Item);
        $this->query("UPDATE $table  SET  $upStr $where");
        return true;
    }
    function batchInsert($table, $colunms, $values)
    {
        $sql = 'insert into `' . $table . '` (';
        foreach ($colunms as $val) {
            $sql .= '`' . $val . '`,';
        }
        unset($val);
        $sql = substr_replace($sql,'',-1);
        $sql .= ') values ';
        foreach ($values as $val) {
            $valStr = implode('\',\'',$val);
            $sql .= '(\'' . $valStr . '\'),';
        }
        unset($val);
        $sql = substr_replace($sql,'',-1);
        $this->query($sql);
        return true;
    }
    function geterror($sql=''){
        /*$error = mysql_error();
        $file = date('Y_m_d');
        $file = './log/log_mysql_'.$file.".txt";
        $fp = fopen($file,'a+');
        $str = date('G:i:s').$sql." ".$error."\r\n\r\n";
        fwrite($fp,$str);
        fclose($fp);

        return $error;*/

//        // 替代 mysql_error()，使用 mysqli
//        $conn = $this->db ?? $this->conn; // 你实际的 mysqli 对象
//        $error = $conn instanceof \mysqli ? $conn->error : '无法获取数据库错误';
//
//        // 日志文件路径（自动按日期）
//        $dir = __DIR__ . '/log/';
//        $file = $dir . 'log_mysql_' . date('Y_m_d') . '.txt';
//
//        // 自动创建 log 目录
//        if (!is_dir($dir)) {
//            mkdir($dir, 0777, true);
//        }
//
//        // 如果文件不存在，创建并赋权限
//        if (!file_exists($file)) {
//            file_put_contents($file, '');
//            chmod($file, 0666); // 可读写
//        }
//
//        // 写入错误日志
//        $time = date('Y-m-d H:i:s');
//        $log = "[$time]\nSQL: $sql\nERROR: $error\n\n";
//
//        file_put_contents($file, $log, FILE_APPEND);
//
//        return $error;
    }
    function affected_rows(){
        return mysqli_affected_rows($this->conn);
    }
    function getMysqlVersion(){
        return @mysqli_get_server_info();
    }

    /**
     * 获取待处理的佣金计算订单
     * 双重检测机制：
     * 1. 优先处理队列中的订单（由触发器或接口加入）
     * 2. 同时检测orders表中满足条件但未加入队列的订单（兜底机制）
     *
     * 使用方案4：时间窗口 + 状态标识（不使用FOR UPDATE和事务）
     * 通过UPDATE ... WHERE status='pending'原子性地获取记录
     */
    public function getPendingCommissionCalculations()
    {
        try {
            $results = [];
            $processingAt = date('Y-m-d H:i:s');

            // 方式1：从队列中原子性地获取待处理的订单（优先级高）
            // 使用UPDATE原子性地将pending改为processing
            $updateSql = "UPDATE commissionCalculationQueue
                         SET status = 'processing',
                             processingAt = '{$processingAt}',
                             updatedAt = '{$processingAt}'
                         WHERE status = 'pending'
                         ORDER BY createdAt ASC
                         LIMIT 20";
            $this->query($updateSql);
            $affectedRows = mysqli_affected_rows($this->conn);

            if ($affectedRows > 0) {
                // 查询刚才更新的记录（使用processingAt精确匹配）
                $queueSql = "SELECT orderId, id, retryCount, maxRetries
                            FROM commissionCalculationQueue
                            WHERE status = 'processing'
                              AND processingAt = '{$processingAt}'
                            ORDER BY createdAt ASC
                            LIMIT 20";
                $queueOrders = $this->fetch_all_assoc($queueSql);
                foreach ($queueOrders as $order) {
                    $results[] = $order;
                }
            }

            // 方式2：检测orders表中满足条件但未加入队列的订单（兜底机制）
            // 注意：如果队列中已有订单，不再检测orders表，避免重复
            if (empty($results)) {
                // 先批量插入到队列（状态直接设为processing，利用唯一约束避免重复）
                $insertSql = "INSERT INTO commissionCalculationQueue (orderId, status, retryCount, maxRetries, processingAt, createdAt, updatedAt)
                             SELECT o.id, 'processing', 0, 3, '{$processingAt}', '{$processingAt}', '{$processingAt}'
                             FROM orders o
                             WHERE o.trading_status = 2
                               AND o.closetime > 0
                               AND NOT EXISTS (
                                   SELECT 1 FROM commissionCalculationQueue
                                   WHERE orderId = o.id
                               )
                               AND NOT EXISTS (
                                   SELECT 1 FROM ibCommissionCalculations
                                   WHERE orderId = o.id
                                   LIMIT 1
                               )
                             ORDER BY o.closetime ASC
                             LIMIT 20
                             ON DUPLICATE KEY UPDATE orderId = orderId";
                $this->query($insertSql);
                $affectedRows = mysqli_affected_rows($this->conn);

                if ($affectedRows > 0) {
                    // 查询刚才插入的记录
                    $ordersSql = "SELECT orderId, id, retryCount, maxRetries
                                 FROM commissionCalculationQueue
                                 WHERE status = 'processing'
                                   AND processingAt = '{$processingAt}'
                                 ORDER BY createdAt ASC
                                 LIMIT 20";
                    $directOrders = $this->fetch_all_assoc($ordersSql);
                    foreach ($directOrders as $order) {
                        $results[] = $order;
                    }
                }
            }

            return $results;
        } catch (\Exception $e) {
            Logger::error("[" . date('Y-m-d H:i:s') . "] 获取待处理佣金计算订单失败: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 将订单加入队列（如果不存在）
     */
    private function addOrderToQueue($orderId)
    {
        try {
            $orderId = intval($orderId);
            if ($orderId <= 0) {
                return false;
            }

            // 使用INSERT IGNORE或ON DUPLICATE KEY UPDATE避免重复
            $now = date('Y-m-d H:i:s');
            $sql = "INSERT INTO commissionCalculationQueue (orderId, status, retryCount, maxRetries, createdAt, updatedAt)
                    VALUES ({$orderId}, 'pending', 0, 3, {$now}, {$now})
                    ON DUPLICATE KEY UPDATE orderId = {$orderId}";

            $this->query($sql);
            return mysqli_affected_rows($this->conn) > 0;
        } catch (\Exception $e) {
            Logger::error("[" . date('Y-m-d H:i:s') . "] 将订单加入队列失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理佣金计算
     */
    public function processCommissionCalculation($orderId)
    {
        $orderId = intval($orderId);
        if ($orderId <= 0) {
            return "无效的订单ID: {$orderId}";
        }

        $now = date('Y-m-d H:i:s');

        try {
            // 注意：在方案4中，status已经在getPendingCommissionCalculations中更新为'processing'
            // 这里只需要验证记录是否存在且状态为processing
            $queueRecord = $this->once_fetch_assoc("SELECT * FROM commissionCalculationQueue WHERE orderId = {$orderId} LIMIT 1");
            if (!$queueRecord) {
                return "队列记录不存在: {$orderId}";
            }

            // 如果状态不是processing，说明可能已被其他进程处理或状态异常
            if ($queueRecord['status'] != 'processing') {
                return "订单 {$orderId} 当前状态为 '{$queueRecord['status']}'，无需处理或已被处理";
            }

            // 加载佣金计算服务
            require_once __DIR__ . '/../services/IbCommissionCalculator.php';
            $calculator = new \IbCommissionCalculator();

            // 计算佣金
            $result = $calculator->calculateOrderCommission($orderId, false);

            if ($result['success']) {
                $calculationsCount = count($result['calculations'] ?? []);

                // 如果calculations为空，说明可能是客户没有关联IB代理或其他原因
                // 但仍然标记为完成，因为这不是错误
                $message = $result['message'] ?? 'Commission calculated successfully';

                // 更新队列状态为完成
                $this->query("UPDATE commissionCalculationQueue
                             SET status = 'completed', processedAt = '{$now}', errorMessage = NULL
                             WHERE orderId = {$orderId}");

                if ($calculationsCount > 0) {
                    return "订单 {$orderId} 佣金计算完成，共计算 {$calculationsCount} 条记录";
                } else {
                    // 记录为什么没有计算（用于调试）
                    Logger::error("[" . date('Y-m-d H:i:s') . "] 订单 {$orderId} 佣金计算完成但无记录: {$message}");
                    return "订单 {$orderId} 佣金计算完成，但无佣金记录（原因: {$message}）";
                }
            } else {
                // 重试逻辑
                $retryCount = (int)$queueRecord['retryCount'];
                $maxRetries = (int)$queueRecord['maxRetries'];

                if ($retryCount < $maxRetries) {
                    $errorMsg = $this->escape(substr($result['message'] ?? 'Unknown error', 0, 500));
                    // 重试时：清除processingAt（因为要重新处理），更新updatedAt
                    $this->query("UPDATE commissionCalculationQueue
                                 SET status = 'pending',
                                     retryCount = " . ($retryCount + 1) . ",
                                     processingAt = NULL,
                                     updatedAt = '{$now}',
                                     errorMessage = '{$errorMsg}'
                                 WHERE orderId = {$orderId}");
                    return "订单 {$orderId} 佣金计算失败，将重试 ({$retryCount}/{$maxRetries}): " . $result['message'];
                } else {
                    $errorMsg = $this->escape(substr($result['message'] ?? 'Unknown error', 0, 500));
                    // 失败时：设置processedAt和updatedAt
                    $this->query("UPDATE commissionCalculationQueue
                                 SET status = 'failed',
                                     processedAt = '{$now}',
                                     updatedAt = '{$now}',
                                     errorMessage = '{$errorMsg}'
                                 WHERE orderId = {$orderId}");
                    return "订单 {$orderId} 佣金计算失败，已达到最大重试次数: " . $result['message'];
                }
            }
        } catch (\Exception $e) {
            $errorRaw = substr($e->getMessage(), 0, 500);
            $error = $this->escape($errorRaw);
            // 异常时：设置processedAt和updatedAt
            $this->query("UPDATE commissionCalculationQueue
                         SET status = 'failed',
                             processedAt = '{$now}',
                             updatedAt = '{$now}',
                             errorMessage = '{$error}'
                         WHERE orderId = {$orderId}");
            return "订单 {$orderId} 佣金计算异常: " . $e->getMessage();
        }
    }
}
