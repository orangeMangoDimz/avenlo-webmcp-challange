<?php
/**
 * IB Document Template 模型
 * 对应表：ibDocumentTemplates
 */

require_once __DIR__ . '/BaseModel.php';

class IbDocumentTemplate extends BaseModel {
    protected $table = 'ibDocumentTemplates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'documentTitle', 'documentContent', 'iconClass', 'iconGradient',
        'isRequired', 'displayOrder', 'wordCount', 'characterCount',
        'estimatedReadTime', 'version', 'isActive', 'createdBy', 'updatedBy'
    ];

    /**
     * 获取所有激活的文档模板
     */
    public function getActiveDocuments() {
        $sql = "SELECT * FROM ibDocumentTemplates
                WHERE isActive = 1
                ORDER BY displayOrder ASC, id ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * 获取文档列表（带分页）
     */
    public function getDocuments($page = 1, $perPage = 10, $filters = []) {
        $offset = ($page - 1) * $perPage;
        $conditions = [];
        $params = [];

        // 构建筛选条件
        if (isset($filters['isRequired'])) {
            $conditions[] = "isRequired = :isRequired";
            $params['isRequired'] = $filters['isRequired'];
        }

        if (isset($filters['isActive'])) {
            $conditions[] = "isActive = :isActive";
            $params['isActive'] = $filters['isActive'];
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT * FROM {$this->table}
                {$whereClause}
                ORDER BY displayOrder ASC, id ASC
                LIMIT {$perPage} OFFSET {$offset}";

        $countSql = "SELECT COUNT(*) as count
                     FROM {$this->table}
                     {$whereClause}";

        $items = $this->db->fetchAll($sql, $params);
        $total = $this->db->fetchOne($countSql, $params)['count'];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    /**
     * 更新文档内容并自动计算统计
     */
    public function updateDocument($id, $data) {
        // 如果更新了content，计算统计数据
        if (isset($data['documentContent'])) {
            $content = $data['documentContent'];

            // 移除HTML标签来计算
            $plainText = strip_tags($content);
            $charCount = mb_strlen($plainText);
            $wordCount = str_word_count($plainText);
            $readTime = max(1, ceil($wordCount / 200)); // 200 words per minute

            $data['characterCount'] = $charCount;
            $data['wordCount'] = $wordCount;
            $data['estimatedReadTime'] = $readTime;
        }

        return $this->update($id, $data);
    }

    /**
     * 复制文档模板
     */
    public function duplicateDocument($documentId, $createdBy) {
        $original = $this->findById($documentId);
        if (!$original) {
            return false;
        }

        $newDocData = [
            'documentTitle' => $original['documentTitle'] . ' (Copy)',
            'documentContent' => $original['documentContent'],
            'iconClass' => $original['iconClass'],
            'iconGradient' => $original['iconGradient'],
            'isRequired' => $original['isRequired'],
            'displayOrder' => $original['displayOrder'] + 1,
            'wordCount' => $original['wordCount'],
            'characterCount' => $original['characterCount'],
            'estimatedReadTime' => $original['estimatedReadTime'],
            'version' => '1.0',
            'isActive' => 1,
            'createdBy' => $createdBy
        ];

        return $this->create($newDocData);
    }
}
