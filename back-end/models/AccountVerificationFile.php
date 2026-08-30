<?php
/**
 * Account Verification File Model
 * 账户验证文件模型
 */

require_once __DIR__ . '/../utils/Database.php';

class AccountVerificationFile {
    private $db;
    private $table = 'accountVerificationFiles';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 创建文件记录
     * @param array $data 文件数据
     * @return int 新创建的文件ID
     */
    public function createFile($data) {
        // 使用 Database 类的 insert 方法
        return $this->db->insert($this->table, $data);
    }

    /**
     * 获取验证的所有文件
     * @param int $verificationId 验证ID
     * @return array
     */
    public function getVerificationFiles($verificationId) {
        $sql = "SELECT
                    id,
                    verificationId,
                    fileName,
                    filePath,
                    fileType,
                    fileSize,
                    fileCategory,
                    uploadedAt
                FROM {$this->table}
                WHERE verificationId = :verificationId
                ORDER BY uploadedAt ASC";

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchAll($sql, ['verificationId' => $verificationId]);
    }

    /**
     * 通过ID获取文件信息
     * @param int $id 文件ID
     * @return array|null
     */
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";

        // 使用 Database 类的方法，自动规范化数据类型
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * 删除文件记录
     * @param int $id 文件ID
     * @return bool
     */
    public function deleteFile($id) {
        // 使用 Database 类的 delete 方法
        return $this->db->delete($this->table, 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * 获取文件总大小
     * @param int $verificationId 验证ID
     * @return int 总大小（字节）
     */
    public function getTotalFileSize($verificationId) {
        $sql = "SELECT COALESCE(SUM(fileSize), 0) as totalSize
                FROM {$this->table}
                WHERE verificationId = :verificationId";

        $result = $this->db->fetchOne($sql, ['verificationId' => $verificationId]);
        return (int)($result['totalSize'] ?? 0);
    }
}
