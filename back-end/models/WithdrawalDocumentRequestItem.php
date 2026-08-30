<?php
/**
 * Withdrawal Document Request Item Model
 * 对应表: withdrawalDocumentRequestItems
 */

require_once __DIR__ . '/BaseModel.php';

class WithdrawalDocumentRequestItem extends BaseModel {
    protected $table = 'withdrawalDocumentRequestItems';
    protected $primaryKey = 'id';
    protected $fillable = [
        'requestId',
        'itemType',
        'questionText',
        'questionType',
        'questionOptions',
        'questionValidation',
        'questionHelpText',
        'documentName',
        'documentType',
        'documentDescription',
        'acceptedFileTypes',
        'isRequired',
        'displayOrder',
        'clientResponse',
        'respondedAt'
    ];

    /**
     * 获取请求的所有项目
     */
    public function getRequestItems($requestId) {
        return $this->findAll(
            ['requestId' => $requestId],
            'displayOrder ASC'
        );
    }

    /**
     * 批量创建项目
     */
    public function bulkCreate($requestId, $items) {
        $results = [];

        foreach ($items as $index => $item) {
            $item['requestId'] = $requestId;
            $item['displayOrder'] = $item['displayOrder'] ?? $index;

            // 转换JSON字段
            if (isset($item['questionOptions']) && is_array($item['questionOptions'])) {
                $item['questionOptions'] = json_encode($item['questionOptions']);
            }

            if (isset($item['acceptedFileTypes']) && is_array($item['acceptedFileTypes'])) {
                $item['acceptedFileTypes'] = json_encode($item['acceptedFileTypes']);
            }

            $results[] = $this->create($item);
        }

        return $results;
    }

    /**
     * 更新客户回复
     */
    public function updateClientResponse($itemId, $response) {
        return $this->update($itemId, [
            'clientResponse' => $response,
            'respondedAt' => date('Y-m-d H:i:s')
        ]);
    }
}
