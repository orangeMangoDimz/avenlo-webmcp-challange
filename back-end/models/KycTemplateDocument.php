<?php
/**
 * KYC Template Document Model
 * 对应表: kycTemplateDocuments
 */

require_once __DIR__ . '/BaseModel.php';

class KycTemplateDocument extends BaseModel {
    protected $table = 'kycTemplateDocuments';
    protected $primaryKey = 'id';
    protected $fillable = [
        'templateId',
        'documentId',
        'documentTitle',
        'documentContent',
        'displayOrder',
        'isActive'
    ];

    /**
     * 获取模板的所有文档
     */
    public function getTemplateDocuments($templateId, $activeOnly = true) {
        $conditions = ['templateId' => $templateId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    /**
     * 创建模板文档
     */
    public function createTemplateDocument($data) {
        // 如果没有指定显示顺序，自动设置为最后
        if (!isset($data['displayOrder'])) {
            $maxOrder = $this->getMaxDisplayOrder($data['templateId']);
            $data['displayOrder'] = $maxOrder + 1;
        }

        return $this->create($data);
    }

    /**
     * 获取模板中文档的最大显示顺序
     */
    private function getMaxDisplayOrder($templateId) {
        $sql = "SELECT MAX(displayOrder) as maxOrder FROM {$this->table} WHERE templateId = :templateId";
        $result = $this->query($sql, ['templateId' => $templateId]);

        return $result[0]['maxOrder'] ?? 0;
    }

    /**
     * 更新文档显示顺序
     */
    public function updateDisplayOrder($templateId, $documentOrders) {
        foreach ($documentOrders as $order => $documentId) {
            $this->update($documentId, ['displayOrder' => $order + 1]);
        }
    }
}
