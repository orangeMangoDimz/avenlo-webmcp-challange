<?php
/**
 * 法律文档模型
 */

require_once __DIR__ . '/BaseModel.php';

class LegalDocument extends BaseModel {
    protected $table = 'legalDocuments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'documentType', 'title', 'content', 'languageCode',
        'isActive', 'version', 'effectiveDate', 'isMandatory',
        'displayOrder', 'updatedBy'
    ];

    /**
     * 覆盖 findAll 方法，添加布尔字段转换
     */
    public function findAll($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        $documents = parent::findAll($conditions, $orderBy, $limit, $offset);
        return $this->convertBooleanFields($documents);
    }

    /**
     * 检查文档标题是否存在
     */
    public function isTitleAvailable($title, $version, $languageCode): bool
    {
        $doc = $this->findOne([
            'title' => $title,
            'languageCode' => $languageCode,
            'version' => $version,
        ]);

        return empty($doc);
    }

    /**
     * 获取所有活跃文档（按显示顺序）
     */
    public function getActiveDocuments($languageCode = 'en') {
        return $this->findAll([
            'isActive' => 1,
            'languageCode' => $languageCode
        ], 'displayOrder ASC');
    }

    /**
     * 根据文档类型获取
     */
    public function getByType($documentType, $languageCode = 'en') {
        $doc = $this->findOne([
            'documentType' => $documentType,
            'languageCode' => $languageCode,
            'isActive' => 1
        ]);
        return $this->convertBooleanFields($doc);
    }

    /**
     * 获取所有文档类型
     */
    public function getAllDocuments($languageCode = null) {
        $conditions = ['isActive' => 1];
        if ($languageCode) {
            $conditions['languageCode'] = $languageCode;
        }
        return $this->findAll($conditions, 'displayOrder ASC');
    }

    /**
     * 转换布尔字段为真正的布尔值
     */
    private function convertBooleanFields($data) {
        if (empty($data)) {
            return $data;
        }

        // 单个记录
        if (isset($data['id'])) {
            $data['isActive'] = (bool)(int)$data['isActive'];
            return $data;
        }

        // 多个记录
        foreach ($data as &$doc) {
            $doc['isActive'] = (bool)(int)$doc['isActive'];
        }

        return $data;
    }

    /**
     * 更新文档顺序
     */
    public function updateOrder($id, $newOrder) {
        return $this->update($id, ['displayOrder' => $newOrder]);
    }

    /**
     * 批量更新文档顺序
     */
    public function batchUpdateOrder($orderData) {
        foreach ($orderData as $item) {
            $this->update($item['id'], ['displayOrder' => $item['order']]);
        }
        return true;
    }

    /**
     * 发布新版本
     */
    public function publishNewVersion($id, $version, $adminUserId = null) {
        $data = [
            'version' => $version,
            'effectiveDate' => date('Y-m-d')
        ];

        if ($adminUserId) {
            $data['updatedBy'] = $adminUserId;
        }

        return $this->update($id, $data);
    }

    /**
     * 切换激活状态
     */
    public function toggleActive($id, $isActive) {
        return $this->update($id, ['isActive' => $isActive]);
    }

    /**
     * 获取可用语言
     */
    public function getAvailableLanguages($documentType) {
        $sql = "SELECT DISTINCT languageCode FROM {$this->table}
                WHERE documentType = :documentType AND isActive = 1";

        $results = $this->db->fetchAll($sql, ['documentType' => $documentType]);
        return array_column($results, 'languageCode');
    }
}
