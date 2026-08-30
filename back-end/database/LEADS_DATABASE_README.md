# Leads Management Database Documentation

## 概述 (Overview)

本文档说明 Leads Management 页面相关的数据库结构。数据库分为三个SQL文件，各司其职。

## 数据库文件说明

### 1. `client_portal_database.sql` (已存在)

**用途**: 客户端注册和认证系统

**核心表**:

- `clientUsers` - 客户用户主表（Leads的基础数据来源）
- `clientSessions` - 客户会话管理
- `clientEmailVerifications` - 邮箱验证
- `clientPasswordResets` - 密码重置
- `legalDocuments` - 法律文档主表
- `languagePacks` - 语言包
- `loginPageBranding` - 登录页面品牌设置

**说明**: 这个数据库已经包含了所有客户注册时填写的基本信息（姓名、邮箱、电话、国家等）。Leads管理页面直接使用 `clientUsers` 表作为leads数据源。

---

### 2. `leads_database.sql` (新建)

**用途**: Leads管理的扩展功能

**核心表**:

#### 状态管理

- `leadStatusHistory` - Lead状态变更历史（new, contacted, converted）

#### 标签系统

- `leadTags` - 标签主表
- `leadTagAssignments` - Lead和标签的关联
- `searchTags` - 快速搜索标签配置

#### 法律文档签署

- `legalDocumentSignatures` - 记录客户签署的法律文档

#### 审计和日志

- `leadActivityLog` - 管理员对leads的操作记录
- `leadEditHistory` - Lead信息修改历史
- `leadBulkOperations` - 批量操作日志

#### KYC管理

- `leadKycStatus` - KYC状态扩展信息

#### 视图

- `vw_lead_summary` - Lead汇总视图（不包含分配信息）

**特点**:

- 所有表都通过 `leadId` 外键关联到 `clientUsers.id`
- 不重复存储客户基本信息，只存储Leads管理特有的数据

---

### 3. `sales_assignment.sql` (新建 - 独立扩展模块)

**用途**: 销售分配和销售团队管理（独立模块，便于未来扩展）

**核心表**:

#### 销售代表管理

- `salesRepresentatives` - 销售代表主表
- `salesRepPerformance` - 销售代表绩效统计

#### 分配管理

- `leadAssignments` - 当前活跃的分配关系
- `leadAssignmentHistory` - 完整的分配变更历史
- `bulkAssignmentOperations` - 批量分配操作审计

#### 跟进和沟通

- `leadAssignmentNotes` - 跟进备注和沟通记录
- `assignmentReminders` - 提醒和任务管理

#### 视图

- `vw_active_assignments` - 活跃分配汇总
- `vw_salesrep_workload` - 销售代表工作负荷
- `vw_lead_assignment_timeline` - 分配时间线
- `vw_lead_summary_with_assignment` - Lead完整汇总（包含分配信息）

**特点**:

- 独立的销售管理体系
- 包含自动更新触发器（自动维护销售代表的leads数量）
- 为未来扩展预留了性能追踪、提醒系统等功能

---

## 数据库关系图

```
┌─────────────────────────────────────────────────────────────┐
│           client_portal_database.sql (已存在)                │
│                                                               │
│  ┌──────────────┐         ┌──────────────────┐              │
│  │ clientUsers  │◄────────│ legalDocuments   │              │
│  │ (基础客户表)  │         │ (法律文档主表)    │              │
│  └──────┬───────┘         └──────────────────┘              │
│         │                                                     │
└─────────┼─────────────────────────────────────────────────────┘
          │
          │ (leadId = clientUsers.id)
          │
    ┌─────┴──────────────────────────────────────────┐
    │                                                  │
┌───▼──────────────────────┐        ┌────────────────▼─────────┐
│  leads_database.sql      │        │  sales_assignment.sql     │
│  (Leads扩展功能)          │        │  (销售分配模块)            │
│                          │        │                           │
│  • leadStatusHistory     │        │  • salesRepresentatives   │
│  • leadTags              │        │  • leadAssignments        │
│  • leadTagAssignments    │        │  • leadAssignmentHistory  │
│  • searchTags            │        │  • leadAssignmentNotes    │
│  • legalDocSignatures    │        │  • assignmentReminders    │
│  • leadActivityLog       │        │  • salesRepPerformance    │
│  • leadEditHistory       │        │  • bulkAssignmentOps      │
│  • leadKycStatus         │        │                           │
│  • leadBulkOperations    │        │  [独立扩展模块]            │
└──────────────────────────┘        └───────────────────────────┘
```

---

## 安装顺序

⚠️ **重要**: 必须按照以下顺序执行SQL文件：

```bash
# 1. 首先执行客户端数据库（如果还未执行）
mysql -u root -p utrada_crm < client_portal_database.sql

# 2. 然后执行Leads管理数据库
mysql -u root -p utrada_crm < leads_database.sql

# 3. 最后执行销售分配数据库
mysql -u root -p utrada_crm < sales_assignment.sql
```

### 为什么必须按顺序执行？

1. **`leads_database.sql` 依赖 `client_portal_database.sql`**
   - 需要 `clientUsers` 表（Leads的基础数据）
   - 需要 `legalDocuments` 表（法律文档签署）

2. **`sales_assignment.sql` 依赖前两个文件**
   - 需要 `clientUsers` 表
   - 需要 `leadKycStatus`, `leadTagAssignments` 等表（用于完整视图）

### 如果执行顺序错误怎么办？

如果你遇到类似 `Table doesn't exist` 的错误，请：

1. 检查是否先执行了依赖的SQL文件
2. 确认数据库名称正确（例如：utrada_crm）
3. 按照正确顺序重新执行

---

## 主要功能映射

### Leads.html 页面功能对应的数据表

| 页面功能                            | 数据表                           | 文件                       |
| ----------------------------------- | -------------------------------- | -------------------------- |
| 客户基本信息                        | `clientUsers`                    | client_portal_database.sql |
| Lead状态（New/Contacted/Converted） | `leadStatusHistory`              | leads_database.sql         |
| Lead标签                            | `leadTags`, `leadTagAssignments` | leads_database.sql         |
| 快速搜索标签                        | `searchTags`                     | leads_database.sql         |
| 法律文档签署记录                    | `legalDocumentSignatures`        | leads_database.sql         |
| 信息编辑历史                        | `leadEditHistory`                | leads_database.sql         |
| KYC状态                             | `leadKycStatus`                  | leads_database.sql         |
| 销售代表列表                        | `salesRepresentatives`           | sales_assignment.sql       |
| 销售分配                            | `leadAssignments`                | sales_assignment.sql       |
| 分配历史                            | `leadAssignmentHistory`          | sales_assignment.sql       |
| 跟进备注                            | `leadAssignmentNotes`            | sales_assignment.sql       |
| 批量分配                            | `bulkAssignmentOperations`       | sales_assignment.sql       |
| 批量操作日志                        | `leadBulkOperations`             | leads_database.sql         |

---

## 关键设计决策

### 1. 为什么不重复存储客户基本信息？

- `clientUsers` 表已经包含了所有注册信息
- 避免数据冗余和不一致
- Leads就是已注册的客户，只需要添加额外的管理属性

### 2. 为什么销售分配独立成单独的SQL文件？

- 便于未来扩展销售管理功能
- 可以独立升级和维护
- 更清晰的模块划分
- 方便在不同项目中复用

### 3. 为什么使用视图（Views）？

- 简化常用查询
- 提供清晰的数据访问接口
- 便于前端API开发

---

## 常用查询示例

### 获取Lead基本信息（不含分配状态）

```sql
-- 使用 leads_database.sql 中的视图
SELECT * FROM vw_lead_summary WHERE leadId = ?;
```

### 获取Lead完整信息（含分配状态）

```sql
-- 使用 sales_assignment.sql 中的视图
SELECT * FROM vw_lead_summary_with_assignment WHERE leadId = ?;
```

### 获取Lead完整信息（带标签列表）

```sql
SELECT
  vls.*,
  GROUP_CONCAT(lt.tagName SEPARATOR ', ') AS tags
FROM vw_lead_summary_with_assignment vls
LEFT JOIN leadTagAssignments lta ON vls.leadId = lta.leadId
LEFT JOIN leadTags lt ON lta.tagId = lt.id
WHERE vls.leadId = ?
GROUP BY vls.leadId;
```

### 获取所有Leads（Leads管理页面主列表）

```sql
SELECT
  vls.*,
  GROUP_CONCAT(lt.tagName) AS tags
FROM vw_lead_summary_with_assignment vls
LEFT JOIN leadTagAssignments lta ON vls.leadId = lta.leadId
LEFT JOIN leadTags lt ON lta.tagId = lt.id
GROUP BY vls.leadId
ORDER BY vls.registrationDate DESC
LIMIT 10 OFFSET 0;
```

### 获取销售代表的工作负荷

```sql
SELECT * FROM vw_salesrep_workload;
```

### 获取特定Lead的分配时间线

```sql
SELECT * FROM vw_lead_assignment_timeline WHERE leadId = ?;
```

### 搜索Leads（按名称、邮箱、国家）

```sql
SELECT * FROM vw_lead_summary_with_assignment
WHERE firstName LIKE ?
   OR lastName LIKE ?
   OR email LIKE ?
   OR country LIKE ?
ORDER BY registrationDate DESC;
```

---

## 未来扩展建议

### Leads管理模块

- [ ] Lead评分系统（Lead Scoring）
- [ ] 自动化工作流（Automation Workflows）
- [ ] Lead来源追踪（Source Tracking）
- [ ] 自定义字段（Custom Fields）

### 销售分配模块

- [ ] 自动分配规则（Auto-Assignment Rules）
- [ ] 销售漏斗分析（Sales Funnel Analytics）
- [ ] 佣金计算（Commission Calculation）
- [ ] 团队协作功能（Team Collaboration）
- [ ] 销售预测（Sales Forecasting）

---

## 维护注意事项

1. **外键约束**: 删除 `clientUsers` 记录会级联删除相关的所有leads数据
2. **触发器**: `leadAssignments` 表有自动更新触发器，修改时需注意
3. **唯一约束**: 每个lead同时只能有一个活跃的销售分配
4. **索引优化**: 已为常用查询字段添加索引，大数据量时需定期优化

---

## 技术支持

如有问题，请查看：

- `client_portal_database.sql` 文件头部的详细注释
- `leads_database.sql` 文件头部的详细注释
- `sales_assignment.sql` 文件头部的详细注释

每个表都包含详细的字段说明和用途注释。
