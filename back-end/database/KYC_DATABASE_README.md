# KYC Management Database Documentation

## 概述 (Overview)

本文档说明 KYC 管理系统相关的数据库结构，用于支持 KYC 模板管理、问题配置、条件规则和客户提交的 KYC 表单。

## 相关页面

- `KYCTemplateList.html` - KYC 模板列表和配置页面
- `KYCList.html` - 客户 KYC 提交列表（审核页面）
- `KYCSettings.html` - KYC 系统设置

## 数据库文件

### `kyc_management_database.sql`

**创建日期**: 2024-12-20
**用途**: KYC 模板管理系统的完整数据库架构

---

## 核心表结构

### 1. 模板管理 (Template Management)

#### `kycTemplates` - KYC 模板主表

存储所有 KYC 模板的基本信息。

**关键字段**:

```sql
- id                        -- 模板ID
- templateName              -- 模板名称
- description               -- 模板描述
- status                    -- 状态: draft, active, inactive, archived
- isThirdPartyEnabled       -- 是否启用第三方KYC系统（如 Jumio, Onfido）
- thirdPartyProvider        -- 第三方提供商名称
- isAutoApproveEnabled      -- 是否自动审批（无需人工审核）
- requireDocumentSignature  -- 是否需要签署法律文档
- totalQuestions            -- 总问题数（自动维护）
- totalRules                -- 总规则数（自动维护）
```

**示例模板**:

- Standard Individual KYC（标准个人账户）
- Corporate Account KYC（企业账户）
- Professional Trader KYC（专业交易员）
- Simplified KYC - Asia Pacific（亚太简化版）

---

#### `kycTemplateCountries` - 模板适用国家

定义每个模板适用的国家/地区。

**关键字段**:

```sql
- templateId    -- 模板ID（外键）
- countryCode   -- 国家代码（US, UK, CA, AU, DE 等）或 "ALL"
- countryName   -- 国家显示名称
```

**特点**:

- 一个模板可以适用于多个国家
- 支持 "ALL" 表示适用所有国家
- 唯一约束防止重复分配

---

### 2. 问题系统 (Question System)

#### `kycQuestionCategories` - 问题分类

将问题组织成逻辑分组。

**关键字段**:

```sql
- templateId     -- 所属模板ID
- categoryName   -- 分类名称
- description    -- 分类描述
- displayOrder   -- 显示顺序
- isExpanded     -- UI默认展开状态
```

**默认分类**（模板1）:

1. Personal Information（个人信息）
2. Financial Information（财务信息）
3. Investment Experience（投资经验）
4. Risk Assessment（风险评估）
5. Compliance（合规性）

---

#### `kycQuestions` - KYC 问题

存储所有 KYC 问题的详细信息。

**关键字段**:

```sql
- templateId        -- 所属模板ID
- categoryId        -- 所属分类ID
- questionNumber    -- 问题编号（1, 2, 3...）
- questionText      -- 问题文本
- helpText          -- 帮助文本
- questionType      -- 问题类型（见下方）
- validationRules   -- 验证规则字符串
- isRequired        -- 是否必填
- displayOrder      -- 显示顺序
```

**支持的问题类型**:

```sql
- text              -- 文本输入
- number            -- 数字输入
- email             -- 邮箱
- tel               -- 电话号码
- date              -- 日期选择
- single_choice     -- 单选题
- multiple_choice   -- 多选题
- yes_no            -- 是/否
- file_upload       -- 文件上传
- textarea          -- 多行文本
```

**验证规则示例**:

```
- "required|min:2|max:100"
- "required|date|before:today"
- "required|numeric|min:1|max:100000"
- "required|file|mimes:jpg,jpeg,png,pdf|max:5120"
```

---

#### `kycQuestionOptions` - 问题选项

存储单选题和多选题的选项。

**关键字段**:

```sql
- questionId     -- 问题ID（外键）
- optionValue    -- 选项文本/值
- displayOrder   -- 显示顺序
```

**示例**（Question #4: Annual income）:

- Under $25,000
- $25,000-$50,000
- $50,000-$100,000
- $100,000-$250,000
- Over $250,000

---

#### `kycQuestionDocumentTypes` - 文档类型

定义文件上传问题接受的文档类型。

**关键字段**:

```sql
- questionId            -- 问题ID（外键）
- documentType          -- 文档类型代码
- documentDisplayName   -- 文档显示名称
- isRequired            -- 是否必须上传此类型
```

**支持的文档类型**:

```
- ID_CARD           -- 身份证
- PASSPORT          -- 护照
- DRIVERS_LICENSE   -- 驾驶执照
- PROOF_ADDRESS     -- 地址证明
- BANK_STATEMENT    -- 银行对账单
- UTILITY_BILL      -- 水电费账单
- INCOME_PROOF      -- 收入证明
- TAX_DOCUMENT      -- 税务文件
等...
```

---

### 3. 条件逻辑规则 (Conditional Logic Rules)

#### `kycConditionalRules` - 条件规则

简化的条件逻辑规则，支持两种操作。

**规则类型**:

```sql
1. jump_to  -- 跳转到指定问题（跳过中间问题）
2. reject   -- 拒绝申请（显示拒绝消息）
```

**关键字段**:

```sql
- templateId            -- 所属模板ID
- ruleName              -- 规则名称
- ruleType              -- 规则类型（jump_to 或 reject）
- triggerQuestionId     -- 触发问题ID
- triggerAnswer         -- 触发答案（用户选择的值）
- targetQuestionId      -- 目标问题ID（仅 jump_to）
- rejectMessage         -- 拒绝消息（仅 reject）
- isActive              -- 是否启用
```

**示例规则**:

**规则1: 交易经验跳转**

```
IF 用户在问题#8选择 "Forex"
THEN 跳转到问题#10
```

**规则2: 无经验拒绝**

```
IF 用户在问题#6选择 "No experience"
THEN 拒绝申请，消息："Sorry, we require trading experience"
```

**规则3: 高收入跳转**

```
IF 用户在问题#4选择 "Over $250,000"
THEN 跳转到问题#9
```

---

### 4. 法律文档 (Legal Documents)

#### `kycTemplateDocuments` - 模板文档要求

关联模板与需要签署的法律文档。

**关键字段**:

```sql
- templateId        -- 模板ID
- documentId        -- 法律文档ID（关联到 legalDocuments）
- documentTitle     -- 文档标题
- documentContent   -- 文档内容（HTML）
- displayOrder      -- 显示顺序
```

**注意**: 使用现有的 `legalDocuments` 表（来自 `client_portal_database.sql`）

**默认文档**（模板1）:

1. Terms of Service Agreement（服务条款）
2. Privacy Policy（隐私政策）
3. Risk Disclosure Statement（风险披露）

---

### 5. 客户提交 (Client Submissions)

#### `clientKycSubmissions` - KYC 提交记录

追踪客户的 KYC 表单提交。

**关键字段**:

```sql
- clientId              -- 客户ID（关联 clientUsers）
- templateId            -- 使用的模板ID
- submissionStatus      -- 提交状态（见下方）
- submittedAt           -- 提交时间
- reviewedAt            -- 审核时间
- reviewedBy            -- 审核人（Admin ID）
- approvalNotes         -- 审批备注
- rejectionReason       -- 拒绝原因
```

**提交状态**:

```sql
- draft             -- 草稿（未提交）
- submitted         -- 已提交，等待审核
- under_review      -- 审核中
- approved          -- 已批准
- rejected          -- 已拒绝
- incomplete        -- 不完整
```

---

#### `clientKycAnswers` - KYC 答案

存储客户对问题的回答。

**关键字段**:

```sql
- submissionId      -- 提交记录ID
- questionId        -- 问题ID
- answerText        -- 文本答案
- answerValues      -- JSON数组（多选答案）
- answerDate        -- 日期答案
- answerNumber      -- 数字答案
- uploadedFiles     -- JSON数组（上传的文件路径）
```

**设计说明**:

- 不同类型的答案存储在不同字段
- 多选题答案存储为 JSON 数组
- 文件上传路径存储为 JSON 数组

---

#### `clientKycDocumentSignatures` - 文档签署记录

追踪客户签署的法律文档。

**关键字段**:

```sql
- submissionId          -- 提交记录ID
- templateDocumentId    -- 模板文档ID
- signedAt              -- 签署时间
- ipAddress             -- IP地址
- userAgent             -- 浏览器信息
```

---

### 6. 审计和历史 (Audit & History)

#### `kycTemplateEditHistory` - 模板编辑历史

追踪对模板的所有修改。

**关键字段**:

```sql
- templateId    -- 模板ID
- changeType    -- 变更类型
- fieldName     -- 字段名称
- oldValue      -- 旧值
- newValue      -- 新值
- description   -- 变更描述
- editedBy      -- 编辑人（Admin ID）
```

**变更类型示例**:

- `template_info` - 模板信息修改
- `question_added` - 添加问题
- `question_removed` - 删除问题
- `rule_modified` - 规则修改
- `country_updated` - 国家列表更新

---

#### `kycSubmissionActivityLog` - 提交活动日志

追踪管理员对 KYC 提交的操作。

**关键字段**:

```sql
- submissionId  -- 提交记录ID
- activityType  -- 活动类型
- description   -- 活动描述
- performedBy   -- 执行人（Admin ID）
- metadata      -- JSON元数据
```

**活动类型示例**:

- `status_changed` - 状态变更
- `reviewed` - 审核
- `approved` - 批准
- `rejected` - 拒绝
- `note_added` - 添加备注

---

## 视图 (Views)

### `vw_kyc_template_summary`

模板汇总信息，包括统计数据。

**字段**:

- 模板基本信息
- 适用国家数量
- 活跃分类数量
- 总提交数
- 已批准提交数

**用途**: 模板列表页面（KYCTemplateList.html）

---

### `vw_kyc_questions_full`

问题完整信息，包含分类和选项统计。

**字段**:

- 问题所有详细信息
- 分类名称
- 选项数量
- 文档类型数量

**用途**: 问题管理和显示

---

### `vw_kyc_active_templates`

活跃模板列表，带国家列表。

**字段**:

- 模板基本信息
- 国家名称列表（逗号分隔）
- 国家代码列表

**用途**: 客户注册时选择模板

---

### `vw_client_kyc_progress`

客户 KYC 进度追踪。

**字段**:

- 客户信息
- 模板信息
- 提交状态
- 已回答问题数
- 进度百分比
- 已签署文档数

**用途**: KYC 审核列表页面（KYCList.html）

---

## 触发器 (Triggers)

### 自动更新问题数量

- `trg_update_total_questions_after_insert`
- `trg_update_total_questions_after_update`
- `trg_update_total_questions_after_delete`

**功能**: 自动维护 `kycTemplates.totalQuestions` 字段

---

### 自动更新规则数量

- `trg_update_total_rules_after_insert`
- `trg_update_total_rules_after_update`
- `trg_update_total_rules_after_delete`

**功能**: 自动维护 `kycTemplates.totalRules` 字段

---

## 数据库关系图

```
┌─────────────────────────────────────────────────────────────┐
│            Dependencies (Must Install First)                 │
│                                                               │
│  client_portal_database.sql:                                 │
│    - clientUsers                                             │
│    - legalDocuments                                          │
│                                                               │
│  admin_system_database.sql:                                  │
│    - adminUsers                                              │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│            kyc_management_database.sql                       │
│                                                               │
│  ┌──────────────────┐         ┌────────────────────┐        │
│  │  kycTemplates    │◄────────│ kycTemplateCountries│       │
│  │  (模板主表)       │         │ (适用国家)           │       │
│  └────────┬─────────┘         └────────────────────┘        │
│           │                                                   │
│           ├────────┐                                         │
│           │        │                                         │
│  ┌────────▼─────┐  └──────┐                                │
│  │ kycQuestion  │         │                                 │
│  │ Categories   │         │                                 │
│  │ (问题分类)    │         │                                 │
│  └────────┬─────┘         │                                 │
│           │               │                                  │
│  ┌────────▼──────────┐    │                                 │
│  │  kycQuestions     │◄───┘                                 │
│  │  (KYC问题)        │                                       │
│  └────┬──────┬───────┘                                       │
│       │      │                                               │
│  ┌────▼──┐ ┌▼────────────────┐                             │
│  │ kycQ  │ │ kycQuestion     │                             │
│  │ uest  │ │ DocumentTypes   │                             │
│  │ ion   │ │ (文档类型)       │                             │
│  │ Opti  │ └─────────────────┘                             │
│  │ ons   │                                                  │
│  │ (选项) │                                                  │
│  └───────┘                                                  │
│                                                               │
│  ┌──────────────────┐    ┌────────────────────┐            │
│  │ kycConditional   │    │ kycTemplate        │            │
│  │ Rules            │    │ Documents          │            │
│  │ (条件规则)        │    │ (模板文档)          │            │
│  └──────────────────┘    └────────────────────┘            │
│                                                               │
│  ┌─────────────────────────────────────┐                    │
│  │    Client Submissions               │                    │
│  │  ┌─────────────────┐                │                    │
│  │  │ clientKyc       │                │                    │
│  │  │ Submissions     │                │                    │
│  │  │ (客户提交)       │                │                    │
│  │  └───┬──────┬──────┘                │                    │
│  │      │      │                        │                    │
│  │  ┌───▼──┐ ┌▼─────────────┐          │                    │
│  │  │ Answ │ │ Document      │          │                    │
│  │  │ ers  │ │ Signatures    │          │                    │
│  │  └──────┘ └───────────────┘          │                    │
│  └─────────────────────────────────────┘                    │
│                                                               │
│  ┌─────────────────────────────────────┐                    │
│  │    Audit & History                   │                    │
│  │  - kycTemplateEditHistory           │                    │
│  │  - kycSubmissionActivityLog         │                    │
│  └─────────────────────────────────────┘                    │
└─────────────────────────────────────────────────────────────┘
```

---

## 安装顺序

⚠️ **重要**: 必须按照以下顺序执行 SQL 文件：

```bash
# 1. 首先执行客户端数据库（提供 clientUsers 和 legalDocuments 表）
mysql -u root -p utrada_crm < client_portal_database.sql

# 2. 执行管理员系统数据库（提供 adminUsers 表）
mysql -u root -p utrada_crm < admin_system_database.sql

# 3. 最后执行 KYC 管理数据库
mysql -u root -p utrada_crm < kyc_management_database.sql
```

### 为什么必须按顺序？

`kyc_management_database.sql` 依赖以下表：

- `clientUsers` (来自 client_portal_database.sql)
- `legalDocuments` (来自 client_portal_database.sql)
- `adminUsers` (来自 admin_system_database.sql)

---

## 常用查询示例

### 1. 获取模板完整信息

```sql
SELECT * FROM vw_kyc_template_summary WHERE templateId = 1;
```

### 2. 获取模板的所有问题（按分类）

```sql
SELECT
  c.categoryName,
  q.*,
  (SELECT GROUP_CONCAT(optionValue ORDER BY displayOrder SEPARATOR '|')
   FROM kycQuestionOptions
   WHERE questionId = q.id) AS options
FROM kycQuestions q
INNER JOIN kycQuestionCategories c ON q.categoryId = c.id
WHERE q.templateId = 1
  AND q.isActive = 1
ORDER BY c.displayOrder, q.displayOrder;
```

### 3. 获取模板的条件规则

```sql
SELECT
  r.*,
  tq.questionText AS triggerQuestionText,
  tq.questionNumber AS triggerQuestionNumber,
  target.questionText AS targetQuestionText,
  target.questionNumber AS targetQuestionNumber
FROM kycConditionalRules r
INNER JOIN kycQuestions tq ON r.triggerQuestionId = tq.id
LEFT JOIN kycQuestions target ON r.targetQuestionId = target.id
WHERE r.templateId = 1
  AND r.isActive = 1
ORDER BY r.displayOrder;
```

### 4. 获取客户 KYC 进度

```sql
SELECT * FROM vw_client_kyc_progress
WHERE clientId = ?
ORDER BY submittedAt DESC;
```

### 5. 获取待审核的 KYC 提交

```sql
SELECT
  v.*,
  DATEDIFF(NOW(), v.submittedAt) AS daysSinceSubmission
FROM vw_client_kyc_progress v
WHERE v.submissionStatus = 'submitted'
ORDER BY v.submittedAt ASC;
```

### 6. 获取问题的所有答案（特定提交）

```sql
SELECT
  q.questionNumber,
  q.questionText,
  q.questionType,
  a.answerText,
  a.answerValues,
  a.answerDate,
  a.answerNumber,
  a.uploadedFiles
FROM clientKycAnswers a
INNER JOIN kycQuestions q ON a.questionId = q.id
WHERE a.submissionId = ?
ORDER BY q.displayOrder;
```

### 7. 统计模板使用情况

```sql
SELECT
  t.templateName,
  COUNT(DISTINCT s.clientId) AS totalClients,
  SUM(CASE WHEN s.submissionStatus = 'approved' THEN 1 ELSE 0 END) AS approvedCount,
  SUM(CASE WHEN s.submissionStatus = 'rejected' THEN 1 ELSE 0 END) AS rejectedCount,
  SUM(CASE WHEN s.submissionStatus = 'submitted' THEN 1 ELSE 0 END) AS pendingCount
FROM kycTemplates t
LEFT JOIN clientKycSubmissions s ON t.id = s.templateId
GROUP BY t.id
ORDER BY totalClients DESC;
```

---

## API 开发建议

### 模板列表 API

```
GET /api/kyc/templates
Response: vw_kyc_template_summary 视图
```

### 模板详情 API

```
GET /api/kyc/templates/:id
Response:
  - Template info
  - Categories with questions
  - Conditional rules
  - Required documents
```

### 客户提交 KYC API

```
POST /api/kyc/submit
Body:
  - templateId
  - answers[] (array of {questionId, answer})
  - signatures[] (array of documentIds)
```

### 管理员审核 API

```
PUT /api/kyc/submissions/:id/review
Body:
  - status (approved/rejected)
  - notes
```

---

## 最佳实践

### 1. 模板设计

- ✓ 从简单开始，逐步增加复杂性
- ✓ 合理使用分类组织问题
- ✓ 提供清晰的帮助文本
- ✓ 谨慎使用条件规则（保持简单）

### 2. 问题配置

- ✓ 使用适当的问题类型
- ✓ 设置合理的验证规则
- ✓ 对文件上传限制大小和类型
- ✓ 必填字段标记清晰

### 3. 审核流程

- ✓ 定期检查待审核提交
- ✓ 记录审核备注
- ✓ 拒绝时提供明确原因
- ✓ 使用活动日志追踪操作

### 4. 性能优化

- ✓ 使用视图简化复杂查询
- ✓ 为常用查询字段建立索引
- ✓ 定期清理过期的草稿提交
- ✓ 文件上传使用外部存储服务

---

## 扩展功能建议

### 未来可以添加:

- [ ] 问题依赖关系（显示/隐藏问题）
- [ ] 多语言问题支持
- [ ] 问卷评分系统
- [ ] 自动分配审核人
- [ ] 邮件通知（提交/审批）
- [ ] 批量导出功能
- [ ] 数据分析报表
- [ ] 模板克隆功能
- [ ] 版本控制系统
- [ ] 移动端优化

---

## 维护注意事项

1. **触发器**: 修改问题或规则时，触发器会自动更新计数
2. **外键约束**: 删除模板会级联删除所有相关数据
3. **文件存储**: `uploadedFiles` 字段存储的是路径，实际文件需要单独管理
4. **状态流转**: 提交状态应按照规定的流程变更
5. **第三方集成**: 启用第三方 KYC 时，自定义问题将被忽略

---

## 技术支持

如有问题，请参考：

- `kyc_management_database.sql` - 完整的数据库架构和注释
- `KYCTemplateList.html` - 前端页面实现
- 其他数据库文件的 README 文档

---

## 更新日志

### 2024-12-20 - v1.0

- ✅ 初始版本发布
- ✅ 完整的模板管理系统
- ✅ 简化的条件规则（jump_to 和 reject）
- ✅ 支持10种问题类型
- ✅ 客户提交和审核功能
- ✅ 审计和历史追踪
- ✅ 4个视图用于常用查询
- ✅ 6个触发器自动维护数据

---

**结束** 🎉
