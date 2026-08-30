<?php
/**
 * 仅写入后台操作日志（无业务副作用，供纯前端操作成功后调用）
 * POST /api/operation-log/record
 */

require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class AdminOperationLogRecordController {
    /**
     * POST /api/operation-log/record
     *
     * Body: modelKey, subModuleKey, operationTypeKey, detailZh, detailEn, targetId (optional)
     */
    public function record() {
        AuthMiddleware::authenticate();
        AuthMiddleware::requireAdmin();

        $body = $this->getJsonBody();
        $writer = new AdminOperationLogWriter();
        $id = $writer->record([
            'modelKey' => (string) ($body['modelKey'] ?? ''),
            'subModuleKey' => (string) ($body['subModuleKey'] ?? ''),
            'operationTypeKey' => (string) ($body['operationTypeKey'] ?? ''),
            'detailZh' => (string) ($body['detailZh'] ?? ''),
            'detailEn' => (string) ($body['detailEn'] ?? ''),
            'targetId' => isset($body['targetId']) ? (int) $body['targetId'] : null,
        ]);

        if ($id === false) {
            Response::success(['recorded' => false], 'Log skipped (module disabled or invalid)');
            return;
        }

        Response::success(['recorded' => true, 'id' => (int) $id], 'Operation log recorded');
    }

    private function getJsonBody() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
