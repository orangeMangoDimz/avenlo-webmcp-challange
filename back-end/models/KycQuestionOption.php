<?php
/**
 * KYC Question Option Model
 * 对应表: kycQuestionOptions
 */

require_once __DIR__ . '/BaseModel.php';

class KycQuestionOption extends BaseModel {
    protected $table = 'kycQuestionOptions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'questionId',
        'optionValue',
        'displayOrder',
        'isActive'
    ];

    /**
     * 获取问题的所有选项
     */
    public function getQuestionOptions($questionId, $activeOnly = true) {
        $conditions = ['questionId' => $questionId];

        if ($activeOnly) {
            $conditions['isActive'] = 1;
        }

        return $this->findAll($conditions, 'displayOrder');
    }

    /**
     * 批量创建选项
     */
    public function createBatch($questionId, $options) {
        $results = [];
        $order = 1;

        foreach ($options as $option) {
            $data = [
                'questionId' => $questionId,
                'optionValue' => is_array($option) ? $option['value'] : $option,
                'displayOrder' => $order++
            ];

            $results[] = $this->create($data);
        }

        return $results;
    }

    /**
     * 更新问题的所有选项
     */
    public function updateQuestionOptions($questionId, $options) {
        // 删除现有选项
        $sql = "DELETE FROM {$this->table} WHERE questionId = :questionId";
        $this->db->query($sql, ['questionId' => $questionId]);

        // 创建新选项
        return $this->createBatch($questionId, $options);
    }
}
