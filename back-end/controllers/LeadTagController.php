<?php
/**
 * Lead标签管理控制器
 */

require_once __DIR__ . '/../models/LeadTag.php';
require_once __DIR__ . '/../models/LeadTagAssignment.php';
require_once __DIR__ . '/../models/LeadActivityLog.php';
require_once __DIR__ . '/../models/LeadBulkOperation.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../services/AdminOperationLogWriter.php';
require_once __DIR__ . '/../services/OperationLogPages.php';
require_once __DIR__ . '/../services/OperationLogTexts/OperationLogTextHelpers.php';

class LeadTagController {
    private $tagModel;
    private $assignmentModel;
    private $activityLogModel;
    private $bulkOpModel;

    public function __construct() {
        $this->tagModel = new LeadTag();
        $this->assignmentModel = new LeadTagAssignment();
        $this->activityLogModel = new LeadActivityLog();
        $this->bulkOpModel = new LeadBulkOperation();
    }

    /**
     * 获取所有标签
     * GET /api/lead-tags
     */
    public function index() {
        $withUsage = $_GET['with_usage'] ?? false;

        if ($withUsage) {
            $tags = $this->tagModel->getTagsWithUsage();
        } else {
            $tags = $this->tagModel->getAllTags();
        }

        Response::success($tags);
    }

    /**
     * 获取单个标签
     * GET /api/lead-tags/{id}
     */
    public function show($id) {
        $tag = $this->tagModel->findById($id);

        if (!$tag) {
            Response::notFound('Tag not found');
        }

        // 获取使用统计
        $usage = $this->tagModel->getTagUsageStats($id);
        $tag['usageCount'] = $usage['usageCount'];

        Response::success($tag);
    }

    /**
     * 创建标签
     * POST /api/lead-tags
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'tagName' => 'required|unique:leadTags,tagName',
            'tagColor' => 'required'
        ]);

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];

        $tagId = $this->tagModel->create([
            'tagName' => $data['tagName'],
            'tagColor' => $data['tagColor'],
            'description' => $data['description'] ?? null,
            'isSystemTag' => 0,
            'createdBy' => $adminId
        ]);

        $tag = $this->tagModel->findById($tagId);

        Response::success($tag, 'Tag created successfully', 201);
    }

    /**
     * 更新标签
     * PUT /api/lead-tags/{id}
     */
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $tag = $this->tagModel->findById($id);
        if (!$tag) {
            Response::notFound('Tag not found');
        }

        // 系统标签不能修改名称
        if ($tag['isSystemTag'] == 1 && isset($data['tagName'])) {
            Response::error('Cannot modify system tag name', 403);
        }

        $updateData = [];

        if (isset($data['tagName'])) {
            // 检查名称是否已被使用
            $existing = $this->tagModel->findByName($data['tagName']);
            if ($existing && $existing['id'] != $id) {
                Response::error('Tag name already exists', 400);
            }
            $updateData['tagName'] = $data['tagName'];
        }

        if (isset($data['tagColor'])) {
            $updateData['tagColor'] = $data['tagColor'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        $this->tagModel->update($id, $updateData);

        $updatedTag = $this->tagModel->findById($id);

        Response::success($updatedTag, 'Tag updated successfully');
    }

    /**
     * 删除标签
     * DELETE /api/lead-tags/{id}
     */
    public function delete($id) {
        $tag = $this->tagModel->findById($id);

        if (!$tag) {
            Response::notFound('Tag not found');
        }

        if (!$this->tagModel->canDelete($id)) {
            Response::error('Cannot delete system tag', 403);
        }

        // 删除标签会级联删除所有分配
        $this->tagModel->delete($id);

        Response::success(null, 'Tag deleted successfully');
    }

    /**
     * 为Lead分配标签
     * POST /api/leads/{leadId}/tags
     */
    public function assignToLead($leadId) {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'tagId' => 'required|numeric'
        ]);

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $assignmentId = $this->assignmentModel->assignTag(
            $leadId,
            $data['tagId'],
            $adminId
        );

        // 获取标签信息
        $tag = $this->tagModel->findById($data['tagId']);

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $leadId,
            'tag_added',
            "Tag '{$tag['tagName']}' added to lead",
            $adminId,
            ['tagId' => $data['tagId'], 'tagName' => $tag['tagName']],
            $ipAddress
        );

        Response::success([
            'assignmentId' => $assignmentId,
            'tag' => $tag
        ], 'Tag assigned successfully');
    }

    /**
     * 移除Lead的标签
     * DELETE /api/leads/{leadId}/tags/{tagId}
     */
    public function removeFromLead($leadId, $tagId) {
        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // 获取标签信息
        $tag = $this->tagModel->findById($tagId);

        $this->assignmentModel->removeTag($leadId, $tagId);

        // 记录活动日志
        if ($tag) {
            $this->activityLogModel->logActivity(
                $leadId,
                'tag_removed',
                "Tag '{$tag['tagName']}' removed from lead",
                $adminId,
                ['tagId' => $tagId, 'tagName' => $tag['tagName']],
                $ipAddress
            );

            $subModule = OperationLogPages::resolveLogClientFromRequest(OperationLogPages::subModuleKeyByAlias('page_leads'));
            (new AdminOperationLogWriter())->logClientTagRemove(
                $subModule,
                $leadId,
                $tag['tagName'] ?? ''
            );
        }

        Response::success(null, 'Tag removed successfully');
    }

    /**
     * 获取Lead的所有标签
     * GET /api/leads/{leadId}/tags
     */
    public function getLeadTags($leadId) {
        $tags = $this->assignmentModel->getLeadTags($leadId);

        Response::success($tags);
    }

    /**
     * 批量分配标签
     * POST /api/lead-tags/bulk-assign
     */
    public function bulkAssign() {
        $data = json_decode(file_get_contents('php://input'), true);
        $subModule = OperationLogPages::resolveLogClient($data, OperationLogPages::subModuleKeyByAlias('page_leads'));

        $validator = new Validator($data, [
            'leadIds' => 'required|array',
            'tagId' => 'required|numeric'
        ]);
        if (!$validator->validate()) {
            $failureMessage = OperationLogTextHelpers::validationErrorsToMessage($validator->getErrors());
            (new AdminOperationLogWriter())->logClientTagBulk(
                $subModule,
                $data['leadIds'] ?? [],
                '',
                false,
                $failureMessage
            );
            Response::validationError($validator->getErrors());
        }

        $token = JWT::getTokenFromHeader();
        $payload = JWT::decode($token);
        $adminId = $payload['userId'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $successCount = $this->assignmentModel->bulkAssignTag(
            $data['leadIds'],
            $data['tagId'],
            $adminId
        );

        // 获取标签信息
        $tag = $this->tagModel->findById($data['tagId']);

        // 记录批量操作
        $this->bulkOpModel->logBulkOperation(
            'bulk_tag',
            $data['leadIds'],
            ['tagId' => $data['tagId'], 'tagName' => $tag['tagName']],
            $adminId,
            $ipAddress
        );

        (new AdminOperationLogWriter())->logClientTagBulk(
            $subModule,
            $data['leadIds'],
            $tag['tagName'] ?? ''
        );

        Response::success([
            'successCount' => $successCount,
            'totalLeads' => count($data['leadIds']),
            'tag' => $tag
        ], 'Tags assigned successfully');
    }

    /**
     * 批量移除标签
     * POST /api/lead-tags/bulk-remove
     */
    public function bulkRemove() {
        $data = json_decode(file_get_contents('php://input'), true);

        Validator::make($data, [
            'leadIds' => 'required|array',
            'tagId' => 'required|numeric'
        ]);

        $this->assignmentModel->bulkRemoveTag(
            $data['leadIds'],
            $data['tagId']
        );

        Response::success([
            'totalLeads' => count($data['leadIds'])
        ], 'Tags removed successfully');
    }

    /**
     * 获取拥有特定标签的所有Leads
     * GET /api/lead-tags/{tagId}/leads
     */
    public function getLeadsByTag($tagId) {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 10;

        $result = $this->assignmentModel->getLeadsByTag($tagId, $page, $perPage);

        Response::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }
}
