-- ============================================================
-- KYC Section Approval Feature (OPTIONAL)
-- ============================================================
-- Created: 2025-11-08
-- Description: Section级别的批准功能（可选）
--              用于多人协作审核或跨天审核场景
-- Status: OPTIONAL - 仅在有明确业务需求时使用
-- ============================================================

-- 使用场景：
-- 1. 多个审核员协作审核同一个KYC提交
-- 2. 审核员需要分多次完成审核（跨天审核）
-- 3. 需要记录每个section的审核时间和审核员
-- 4. 需要生成详细的审核报告

-- 如果不需要以上场景，前端组件状态管理已足够

-- ============================================================
-- Section Approval Table
-- ============================================================

CREATE TABLE IF NOT EXISTS `kycSectionApprovals` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `submissionId` INT(11) UNSIGNED NOT NULL COMMENT 'KYC提交ID',
  `categoryId` INT(11) UNSIGNED NOT NULL COMMENT '问题分类ID',
  `reviewerId` BIGINT(20) UNSIGNED NOT NULL COMMENT '审核员ID',
  `approvalStatus` ENUM('approved', 'pending', 'rejected') NOT NULL DEFAULT 'approved' COMMENT '批准状态',
  `reviewNotes` TEXT DEFAULT NULL COMMENT '审核备注',
  `approvedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '批准时间',
  `updatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueSubmissionCategory` (`submissionId`, `categoryId`),
  KEY `idx_submission_id` (`submissionId`),
  KEY `idx_category_id` (`categoryId`),
  KEY `idx_reviewer_id` (`reviewerId`),
  KEY `idx_approval_status` (`approvalStatus`),
  CONSTRAINT `fk_section_approval_submission` FOREIGN KEY (`submissionId`)
    REFERENCES `clientKycSubmissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_section_approval_category` FOREIGN KEY (`categoryId`)
    REFERENCES `kycQuestionCategories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='KYC Section级别批准记录（可选功能）';

-- ============================================================
-- View: Section Approval Progress
-- ============================================================

CREATE OR REPLACE VIEW `vw_kyc_section_approval_progress` AS
SELECT
    s.id AS submissionId,
    s.clientId,
    s.templateId,
    s.submissionStatus,
    cu.email AS clientEmail,
    cu.firstName,
    cu.lastName,
    t.templateName,
    -- 总分类数
    (SELECT COUNT(*) FROM kycQuestionCategories
     WHERE templateId = s.templateId AND isActive = 1) AS totalCategories,
    -- 已批准分类数
    (SELECT COUNT(DISTINCT categoryId) FROM kycSectionApprovals
     WHERE submissionId = s.id AND approvalStatus = 'approved') AS approvedCategories,
    -- 审核进度百分比
    ROUND(
        (SELECT COUNT(DISTINCT categoryId) FROM kycSectionApprovals
         WHERE submissionId = s.id AND approvalStatus = 'approved') /
        (SELECT COUNT(*) FROM kycQuestionCategories
         WHERE templateId = s.templateId AND isActive = 1) * 100, 2
    ) AS approvalProgress,
    -- 是否全部批准
    CASE
        WHEN (SELECT COUNT(DISTINCT categoryId) FROM kycSectionApprovals
              WHERE submissionId = s.id AND approvalStatus = 'approved') =
             (SELECT COUNT(*) FROM kycQuestionCategories
              WHERE templateId = s.templateId AND isActive = 1)
        THEN 1
        ELSE 0
    END AS allSectionsApproved
FROM clientKycSubmissions s
INNER JOIN clientUsers cu ON s.clientId = cu.id
INNER JOIN kycTemplates t ON s.templateId = t.id
WHERE s.submissionStatus = 'under_review'
ORDER BY s.submittedAt DESC;

-- ============================================================
-- API Endpoints (Reference)
-- ============================================================

-- 以下是需要实现的API端点（参考）：

-- 1. POST /api/kyc-submissions/{id}/approve-section
--    批准单个section
--    Body: {
--      "categoryId": 1,
--      "notes": "Section approved"
--    }

-- 2. DELETE /api/kyc-submissions/{id}/approve-section/{categoryId}
--    取消section批准

-- 3. GET /api/kyc-submissions/{id}/approved-sections
--    获取已批准的sections列表

-- 4. GET /api/kyc-submissions/{id}/section-approval-progress
--    获取section批准进度

-- ============================================================
-- Controller Methods (Reference)
-- ============================================================

-- 需要在 KycSubmissionController.php 中添加以下方法：

/*
public function approveSection($submissionId, $categoryId) {
    $submission = $this->submissionModel->findById($submissionId);

    if (!$submission) {
        Response::notFound('Submission not found');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $adminId = JWT::getPayload()['userId'] ?? null;

    try {
        // 检查category是否属于该template
        $category = $this->categoryModel->findById($categoryId);
        if (!$category || $category['templateId'] != $submission['templateId']) {
            Response::error('Invalid category for this submission', 400);
        }

        // 创建或更新section批准记录
        $sectionApprovalModel = new KycSectionApproval();
        $sectionApprovalModel->createOrUpdate([
            'submissionId' => $submissionId,
            'categoryId' => $categoryId,
            'reviewerId' => $adminId,
            'approvalStatus' => 'approved',
            'reviewNotes' => $input['notes'] ?? null
        ]);

        // 记录活动日志
        $this->activityLogModel->logActivity(
            $submissionId,
            'section_approved',
            "Section '{$category['categoryName']}' approved",
            $adminId
        );

        // 获取更新后的批准进度
        $progress = $sectionApprovalModel->getApprovalProgress($submissionId);

        Response::success($progress, 'Section approved successfully');

    } catch (Exception $e) {
        Response::error('Failed to approve section: ' . $e->getMessage(), 500);
    }
}

public function getApprovedSections($submissionId) {
    $submission = $this->submissionModel->findById($submissionId);

    if (!$submission) {
        Response::notFound('Submission not found');
    }

    $sectionApprovalModel = new KycSectionApproval();
    $approvedSections = $sectionApprovalModel->getApprovedSections($submissionId);

    Response::success([
        'approvedSections' => $approvedSections,
        'total' => count($approvedSections)
    ]);
}

public function getSectionApprovalProgress($submissionId) {
    $submission = $this->submissionModel->findById($submissionId);

    if (!$submission) {
        Response::notFound('Submission not found');
    }

    $sql = "SELECT * FROM vw_kyc_section_approval_progress WHERE submissionId = :id";
    $progress = $this->query($sql, ['id' => $submissionId]);

    Response::success($progress);
}
*/

-- ============================================================
-- Model Class (Reference)
-- ============================================================

-- 需要创建新的Model文件：back-end/models/KycSectionApproval.php

/*
<?php
require_once __DIR__ . '/BaseModel.php';

class KycSectionApproval extends BaseModel {
    protected $table = 'kycSectionApprovals';
    protected $primaryKey = 'id';

    protected $fillable = [
        'submissionId',
        'categoryId',
        'reviewerId',
        'approvalStatus',
        'reviewNotes'
    ];

    public function createOrUpdate($data) {
        // 检查是否已存在
        $existing = $this->findOne([
            'submissionId' => $data['submissionId'],
            'categoryId' => $data['categoryId']
        ]);

        if ($existing) {
            // 更新
            return $this->update($existing['id'], $data);
        } else {
            // 创建
            return $this->create($data);
        }
    }

    public function getApprovedSections($submissionId) {
        $sql = "SELECT sa.*, qc.categoryName, au.fullName as reviewerName
                FROM {$this->table} sa
                INNER JOIN kycQuestionCategories qc ON sa.categoryId = qc.id
                LEFT JOIN adminUsers au ON sa.reviewerId = au.id
                WHERE sa.submissionId = :submissionId
                AND sa.approvalStatus = 'approved'
                ORDER BY qc.displayOrder";

        return $this->query($sql, ['submissionId' => $submissionId]);
    }

    public function getApprovalProgress($submissionId) {
        $sql = "SELECT * FROM vw_kyc_section_approval_progress
                WHERE submissionId = :submissionId";

        return $this->queryOne($sql, ['submissionId' => $submissionId]);
    }

    public function isAllSectionsApproved($submissionId) {
        $progress = $this->getApprovalProgress($submissionId);
        return $progress && $progress['allSectionsApproved'] == 1;
    }
}
?>
*/

-- ============================================================
-- Frontend Integration (Reference)
-- ============================================================

-- 前端需要修改的文件：
-- admin_frontend/src/components/kyc/KYCSubmissionDetail.vue

/*
// 在 setup() 中添加：

// 从后端加载已批准的sections
const loadApprovedSections = async () => {
  try {
    const response = await kycSubmissionService.getApprovedSections(props.submission.submissionId)
    if (response && response.data) {
      approvedSections.value = response.data.approvedSections.map(s => s.categoryId)
    }
  } catch (error) {
    console.error('Failed to load approved sections:', error)
  }
}

// 修改 approveSection 方法：
const approveSection = async (categoryId) => {
  const index = approvedSections.value.indexOf(categoryId)
  if (index > -1) {
    // 取消批准
    approvedSections.value.splice(index, 1)
    // 调用API删除批准记录
    await kycSubmissionService.unapproveSection(props.submission.submissionId, categoryId)
  } else {
    // 批准
    approvedSections.value.push(categoryId)
    // 调用API保存批准记录
    await kycSubmissionService.approveSection(props.submission.submissionId, categoryId)
  }
}

// 在 onMounted 中调用：
onMounted(() => {
  loadSubmissionDetails()
  loadApprovedSections() // 加载已批准的sections
})
*/

-- ============================================================
-- Service Layer (Reference)
-- ============================================================

-- admin_frontend/src/services/kycListService.js

/*
// 添加新的API方法：

approveSection(submissionId, categoryId, notes) {
  return api.post(`/kyc-submissions/${submissionId}/approve-section`, {
    categoryId,
    notes
  })
},

unapproveSection(submissionId, categoryId) {
  return api.delete(`/kyc-submissions/${submissionId}/approve-section/${categoryId}`)
},

getApprovedSections(submissionId) {
  return api.get(`/kyc-submissions/${submissionId}/approved-sections`)
},

getSectionApprovalProgress(submissionId) {
  return api.get(`/kyc-submissions/${submissionId}/section-approval-progress`)
}
*/

-- ============================================================
-- Notes
-- ============================================================

-- 1. 此功能为可选功能，仅在有以下需求时实施：
--    - 多人协作审核
--    - 跨天/跨会话审核
--    - 需要详细的审核追踪

-- 2. 如果只是简单的单人单次审核，前端状态管理已足够

-- 3. 实施此功能需要：
--    - 执行本SQL文件创建表和视图
--    - 创建Model类 (KycSectionApproval.php)
--    - 在Controller中添加方法
--    - 在API路由中添加端点
--    - 更新前端Service和组件

-- 4. 数据迁移：
--    如果已有in-progress的审核，可以从kycSubmissionActivityLog
--    中提取section审核记录并迁移到新表

-- ============================================================
-- END OF OPTIONAL FEATURE
-- ============================================================
