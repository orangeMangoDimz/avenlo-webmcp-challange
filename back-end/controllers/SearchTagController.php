<?php
/**
 * 搜索标签管理控制器
 */

require_once __DIR__ . '/../models/SearchTag.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class SearchTagController {
    private $searchTagModel;

    public function __construct() {
        $this->searchTagModel = new SearchTag();
    }

    /**
     * 获取所有搜索标签
     * GET /api/search-tags
     */
    public function index() {
        $activeOnly = $_GET['active_only'] ?? false;

        if ($activeOnly) {
            $tags = $this->searchTagModel->getActiveTags();
        } else {
            $tags = $this->searchTagModel->getAllTags();
        }

        Response::success($tags);
    }

    /**
     * 获取单个搜索标签
     * GET /api/search-tags/{id}
     */
    public function show($id) {
        $tag = $this->searchTagModel->findById($id);

        if (!$tag) {
            Response::notFound('Search tag not found');
        }

        Response::success($tag);
    }

    /**
     * 创建搜索标签
     * POST /api/search-tags
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_leads'));

        $validator = new Validator($data, [
            'tagName' => 'required',
            'searchKeywords' => 'required'
        ]);
        if (!$validator->validate()) {
            $failureMessage = OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors());
            (new AdminOperationLogWriter())->logSearchTagCreate(
                $subModule,
                $data['tagName'] ?? '',
                $data['searchKeywords'] ?? '',
                false,
                $failureMessage
            );
            Response::validationError($validator->getErrors());
        }

        if (!$this->searchTagModel->isTagNameAvailable($data['tagName'])) {
            (new AdminOperationLogWriter())->logSearchTagCreate(
                $subModule,
                $data['tagName'],
                $data['searchKeywords'] ?? '',
                false,
                'Tag name already exists'
            );
            Response::error('Tag name already exists', 400);
        }

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $tagId = $this->searchTagModel->create([
            'tagName' => $data['tagName'],
            'searchKeywords' => $data['searchKeywords'],
            'displayOrder' => $data['displayOrder'] ?? 999,
            'isActive' => $data['isActive'] ?? 1,
            'createdBy' => $adminId
        ]);

        $tag = $this->searchTagModel->findById($tagId);

        (new AdminOperationLogWriter())->logSearchTagCreate(
            $subModule,
            $data['tagName'],
            $data['searchKeywords']
        );

        Response::success($tag, 'Search tag created successfully', 201);
    }

    /**
     * 更新搜索标签
     * PUT /api/search-tags/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $tag = $this->searchTagModel->findById($id);
        if (!$tag) {
            Response::notFound('Search tag not found');
        }

        $updateData = [];

        if (isset($data['tagName'])) {
            if ($data['tagName'] !== $tag['tagName'] &&
                !$this->searchTagModel->isTagNameAvailable($data['tagName'])) {
                Response::error('Tag name already exists', 400);
            }
            $updateData['tagName'] = $data['tagName'];
        }

        if (isset($data['searchKeywords'])) {
            $updateData['searchKeywords'] = $data['searchKeywords'];
        }

        if (isset($data['displayOrder'])) {
            $updateData['displayOrder'] = $data['displayOrder'];
        }

        if (isset($data['isActive'])) {
            $updateData['isActive'] = $data['isActive'];
        }

        $this->searchTagModel->update($id, $updateData);

        $updatedTag = $this->searchTagModel->findById($id);

        Response::success($updatedTag, 'Search tag updated successfully');
    }

    /**
     * 删除搜索标签
     * DELETE /api/search-tags/{id}
     */
    public function delete($id) {
        $tag = $this->searchTagModel->findById($id);

        $subModule = OperationLogPages::resolveLogClientFromRequest(OperationLogPages::subModuleKeyByAlias('page_leads'));

        if (!$tag) {
            (new AdminOperationLogWriter())->logSearchTagDelete($subModule, '', false, 'Search tag not found');
            Response::notFound('Search tag not found');
        }

        $tagName = $tag['tagName'] ?? '';
        $this->searchTagModel->delete($id);
        (new AdminOperationLogWriter())->logSearchTagDelete($subModule, $tagName);

        Response::success(null, 'Search tag deleted successfully');
    }

    /**
     * 切换标签状态
     * POST /api/search-tags/{id}/toggle
     */
    public function toggle($id) {
        $tag = $this->searchTagModel->findById($id);

        if (!$tag) {
            Response::notFound('Search tag not found');
        }

        $this->searchTagModel->toggleActive($id);

        $updatedTag = $this->searchTagModel->findById($id);

        Response::success($updatedTag, 'Tag status toggled successfully');
    }

    /**
     * 批量更新显示顺序
     * POST /api/search-tags/reorder
     */
    public function reorder() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'tags' => 'required|array'
        ]);

        $this->searchTagModel->bulkUpdateOrder($data['tags']);

        Response::success(null, 'Display order updated successfully');
    }
}
