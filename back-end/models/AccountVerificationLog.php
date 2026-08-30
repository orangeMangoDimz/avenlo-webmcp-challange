<?php
/**
 * Account Verification Log Model
 * 账户验证日志模型 - 记录所有验证相关操作
 */

require_once __DIR__ . '/../utils/Database.php';

class AccountVerificationLog {
    private $db;
    private $table = 'accountVerificationLogs';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 创建日志记录
     * @param array $data 日志数据
     * @return int 新创建的日志ID
     */
    public function createLog($data) {
        // 使用 Database 类的 insert 方法
        return $this->db->insert($this->table, $data);
    }

    /**
     * 获取验证的操作日志
     * @param int $verificationId 验证ID
     * @return array
     */
    public function getVerificationLogs($verificationId) {
        $sql = "SELECT
                    id,
                    verificationId,
                    actionType,
                    actionBy,
                    actionDetails,
                    ipAddress,
                    createdAt
                FROM {$this->table}
                WHERE verificationId = :verificationId
                ORDER BY createdAt DESC";

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchAll($sql, ['verificationId' => $verificationId]);
    }

    /**
     * 记录文件上传
     * @param int $verificationId
     * @param int $userId
     * @param string $fileName
     * @param string $ipAddress
     * @return int
     */
    public function logFileUpload($verificationId, $userId, $fileName, $ipAddress = null) {
        return $this->createLog([
            'verificationId' => $verificationId,
            'actionType' => 'file_uploaded',
            'actionBy' => $userId,
            'actionDetails' => json_encode(['fileName' => $fileName]),
            'ipAddress' => $ipAddress
        ]);
    }

    /**
     * 记录创建操作
     * @param int $verificationId
     * @param int $userId
     * @param string $ipAddress
     * @return int
     */
    public function logCreation($verificationId, $userId, $ipAddress = null) {
        return $this->createLog([
            'verificationId' => $verificationId,
            'actionType' => 'created',
            'actionBy' => $userId,
            'actionDetails' => json_encode(['action' => 'Verification created by client']),
            'ipAddress' => $ipAddress
        ]);
    }

    /**
     * 记录提交操作
     * @param int $verificationId
     * @param int $userId
     * @param string $ipAddress
     * @return int
     */
    public function logSubmission($verificationId, $userId, $ipAddress = null) {
        return $this->createLog([
            'verificationId' => $verificationId,
            'actionType' => 'submitted',
            'actionBy' => $userId,
            'actionDetails' => json_encode(['action' => 'Verification submitted for review']),
            'ipAddress' => $ipAddress
        ]);
    }
}
