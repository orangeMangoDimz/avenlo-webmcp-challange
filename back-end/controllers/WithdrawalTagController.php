<?php
/**
 * Withdrawal Tag Controller
 * 负责提款标签管理
 */

require_once __DIR__ . '/../models/WithdrawalTag.php';
require_once __DIR__ . '/../models/WithdrawalTagAssignment.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';

class WithdrawalTagController {
    private $tagModel;
    private $tagAssignmentModel;

    public function __construct() {
        $this->tagModel = new WithdrawalTag();
        $this->tagAssignmentModel = new WithdrawalTagAssignment();
    }

    /**
     * 获取所有标签
     * GET /api/withdrawal-tags
     */
    public function index() {
        $tags = $this->tagModel->getAllTags();
        Response::success($tags);
    }

    /**
     * 创建标签 (管理员)
     * POST /api/withdrawal-tags
     */
    public function create() {
        $admin = $this->requireAdmin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        Validator::make($data, [
            'tagName' => 'required|string'
        ]);

        $tagName = trim($data['tagName']);

        // 检查是否已存在
        $existing = $this->tagModel->findByName($tagName);
        if ($existing) {
            Response::success($existing, 'Tag already exists');
            return;
        }

        $tagId = $this->tagModel->create([
            'tagName' => $tagName,
            'tagColor' => $data['tagColor'] ?? '#fef3c7',
            'textColor' => $data['textColor'] ?? '#92400e',
            'description' => $data['description'] ?? null,
            'createdBy' => $admin['userId']
        ]);

        $tag = $this->tagModel->findById($tagId);

        Response::created($tag, 'Tag created successfully');
    }

    /**
     * 删除标签 (管理员)
     * DELETE /api/withdrawal-tags/{id}
     */
    public function delete($id) {
        $this->requireAdmin();

        $tag = $this->tagModel->findById($id);
        if (!$tag) {
            Response::notFound('Tag not found');
        }

        if ($tag['isSystemTag']) {
            Response::error('System tags cannot be deleted', 403);
        }

        $this->tagModel->delete($id);

        Response::success(null, 'Tag deleted successfully');
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
