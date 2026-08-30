<?php
/**
 * Transaction Search Tag Controller
 * 负责交易搜索标签相关接口
 */

require_once __DIR__ . '/../models/TransactionSearchTag.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class TransactionSearchTagController {
    private $searchTagModel;

    public function __construct() {
        $this->searchTagModel = new TransactionSearchTag();
    }

    /**
     * 获取搜索标签列表
     * GET /api/transaction-search-tags
     */
    public function index() {
        $transactionType = $_GET['type'] ?? 'both';

        $tags = $this->searchTagModel->getActiveTags($transactionType);

        Response::success($tags);
    }

    /**
     * 创建搜索标签 (管理员)
     * POST /api/transaction-search-tags
     */
    public function create() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $subModule = OperationLogPages::resolveLogTransactionSearchTag($data);
        $opLog = new AdminOperationLogWriter();
        $logFailure = OperationLogPages::isWithdrawalsSubModule($subModule)
            || OperationLogPages::isDepositsSubModule($subModule)
            || OperationLogPages::isInternalTransfersSubModule($subModule);

        $validator = new Validator($data, [
            'tagName' => 'required|string',
            'searchKeywords' => 'required|string'
        ]);
        if (!$validator->validate()) {
            if ($logFailure) {
                $createErrors = $validator->getErrors();
                $opLog->logTransactionSearchTagCreateFailure(
                    $subModule,
                    OperationLogTextHelpers::validationErrorsToMessage($createErrors)
                );
            }
            Response::validationError($validator->getErrors());
        }

        $tagName = trim($data['tagName']);

        $existing = $this->searchTagModel->findByName($tagName);
        if ($existing) {
            if ($logFailure) {
                $opLog->logTransactionSearchTagCreateFailure(
                    $subModule,
                    'A search tag with this name already exists'
                );
            }
            Response::validationError([
                'tagName' => ['A search tag with this name already exists']
            ]);
        }

        $tagId = $this->searchTagModel->create([
            'tagName' => $tagName,
            'searchKeywords' => trim($data['searchKeywords']),
            'transactionType' => $data['transactionType'] ?? 'both',
            'displayOrder' => $data['displayOrder'] ?? 0,
            'createdBy' => $admin['userId']
        ]);

        $tag = $this->searchTagModel->findById($tagId);

        $opLog->logTransactionSearchTagCreate(
            $subModule,
            $tagName,
            trim($data['searchKeywords'])
        );

        Response::created($tag, 'Search tag created successfully');
    }

    /**
     * 删除搜索标签 (管理员)
     * DELETE /api/transaction-search-tags/{id}
     */
    public function delete($id) {
        $this->requireAdmin();

        $subModule = OperationLogPages::resolveLogTransactionSearchTagFromRequest();
        $opLog = new AdminOperationLogWriter();
        $logFailure = OperationLogPages::isWithdrawalsSubModule($subModule)
            || OperationLogPages::isDepositsSubModule($subModule)
            || OperationLogPages::isInternalTransfersSubModule($subModule);

        $tag = $this->searchTagModel->findById($id);
        if (!$tag) {
            if ($logFailure) {
                $opLog->logTransactionSearchTagDeleteFailure($subModule, 'Search tag not found');
            }
            Response::notFound('Search tag not found');
        }

        $tagName = trim((string) ($tag['tagName'] ?? ''));
        $this->searchTagModel->delete($id);

        $opLog->logTransactionSearchTagDelete($subModule, $tagName);

        Response::success(null, 'Search tag deleted successfully');
    }

    /**
     * 要求管理员认证
     */
    private function requireAdmin() {
        $payload = JWT::getPayload();

        if (!$payload || ($payload['type'] ?? '') !== 'admin') {
            Response::forbidden('Admin authentication required');
        }

        $userId = $payload['userId'] ?? null;
        if (!$userId) {
            Response::unauthorized('Invalid token payload');
        }

        return [
            'userId' => $userId
        ];
    }
}
