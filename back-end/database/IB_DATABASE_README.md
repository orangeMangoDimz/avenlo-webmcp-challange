# IB (Introducing Broker) Management Database Documentation

## 概述 (Overview)

IB管理数据库支持完整的介绍经纪人（Introducing Broker）系统，包括：

- IB申请管理和审批流程
- IB合作伙伴管理
- 多层级佣金规则配置
- 层级模板和权限管理
- IB网络层级关系
- 文档模板和签署追踪
- 性能指标和佣金历史

**相关页面：**

- `IBApplicationList.html` - IB申请列表和审批
- `IBList.html` - IB合作伙伴管理
- `IBRule.html` - 佣金规则配置
- `IBSettings.html` - IB程序设置和文档模板
- `IBTierTemplate.html` - 层级模板管理

**命名规范：** camelCase（驼峰命名法）

---

## 数据库表结构 (Database Tables)

### 1. 层级和权限 (Tier & Permissions)

#### 1.1 `ibTierLevels` - IB层级级别

定义IB代理的层级级别和权限。

| 字段                | 类型         | 说明                                    |
| ------------------- | ------------ | --------------------------------------- |
| id                  | INT UNSIGNED | 主键                                    |
| tierLevel           | INT          | 层级级别数字 (1=最高, 2, 3, 4...)       |
| tierName            | VARCHAR(100) | 层级名称 (Master Agent, Senior Agent等) |
| tierDescription     | VARCHAR(500) | 层级描述                                |
| canRecruitSubAgents | TINYINT(1)   | 权限：招募下级代理                      |
| canViewReports      | TINYINT(1)   | 权限：查看报告                          |
| canManageClients    | TINYINT(1)   | 权限：管理客户                          |
| status              | ENUM         | 状态：active, inactive, draft           |
| createdAt           | DATETIME     | 创建时间                                |
| updatedAt           | DATETIME     | 更新时间                                |

**默认层级：**

- Tier 1: Master Agent (全部权限)
- Tier 2: Senior Agent (招募 + 报告)
- Tier 3: Standard Agent (仅报告)
- Tier 4: Junior Agent (基本访问)

---

### 2. 佣金规则 (Commission Rules)

#### 2.1 `ibCommissionRules` - 佣金规则主表

定义不同的佣金规则模板。

| 字段               | 类型          | 说明                                                  |
| ------------------ | ------------- | ----------------------------------------------------- |
| id                 | INT UNSIGNED  | 主键                                                  |
| ruleName           | VARCHAR(200)  | 规则名称                                              |
| ruleType           | ENUM          | 规则类型：standard, premium, ultra, custom            |
| ruleDescription    | TEXT          | 规则描述                                              |
| targetRegion       | VARCHAR(100)  | 目标区域：global, asia, europe, americas, mena        |
| paymentCycle       | ENUM          | 支付周期：daily, weekly, biweekly, monthly, quarterly |
| paymentDay         | VARCHAR(50)   | 支付日配置                                            |
| minimumPayout      | DECIMAL(15,2) | 最低支付金额                                          |
| payoutCurrency     | VARCHAR(10)   | 支付货币                                              |
| autoPaymentEnabled | TINYINT(1)    | 自动支付启用                                          |
| status             | ENUM          | 状态：active, inactive, draft                         |

**佣金类型说明：**

- **Per Lot（按手）**: 基于交易手数的固定佣金
- **Percentage（百分比）**: 基于点差的百分比佣金
- **Per Trade（按交易）**: 每笔交易固定佣金
- **Cashback（返现）**: 直接返现给IB
- **Hybrid（混合）**: 固定佣金 + 点差百分比组合

#### 2.2 `ibRuleProducts` - 规则产品配置

为每个规则定义不同产品/证券的佣金率。

| 字段           | 类型          | 说明                              |
| -------------- | ------------- | --------------------------------- |
| id             | INT UNSIGNED  | 主键                              |
| ruleId         | INT UNSIGNED  | 关联 ibCommissionRules.id         |
| productType    | ENUM          | 产品类型：security, symbol        |
| productName    | VARCHAR(100)  | 产品名称：Forex, Crypto, EURUSD等 |
| commissionType | ENUM          | 佣金类型                          |
| commissionRate | DECIMAL(15,4) | 基础佣金率                        |
| additionalRate | DECIMAL(15,4) | 附加/奖励佣金率                   |
| minimumVolume  | VARCHAR(50)   | 最小交易量要求                    |

**产品类型：**

- **Securities（证券类别）**: Forex, Crypto, Metals, Energies, Indices, Stocks
- **Symbols（特定交易对）**: EURUSD, BTCUSD, XAUUSD等

#### 2.3 `ibRuleAdditionalRules` - 额外规则配置

高级佣金规则，如交易量层级、奖金、倍数等。

| 字段          | 类型         | 说明                      |
| ------------- | ------------ | ------------------------- |
| id            | INT UNSIGNED | 主键                      |
| ruleId        | INT UNSIGNED | 关联 ibCommissionRules.id |
| productType   | ENUM         | 产品类型                  |
| productName   | VARCHAR(100) | 产品名称                  |
| ruleType      | ENUM         | 规则类型                  |
| ruleValue     | VARCHAR(100) | 规则值或层级数量          |
| ruleCondition | VARCHAR(500) | 触发条件                  |

**额外规则类型：**

- **bonus_commission**: 奖励佣金（达到交易量阈值）
- **volume_tiers**: 基于交易量的分层佣金
- **volume_multiplier**: 交易量倍数（如1.25x）
- **performance_bonus**: 绩效奖金
- **cash_rebate**: 现金返还

#### 2.4 `ibRuleCommissionTiers` - 佣金层级

交易量分层的佣金率定义。

| 字段             | 类型          | 说明                          |
| ---------------- | ------------- | ----------------------------- |
| id               | INT UNSIGNED  | 主键                          |
| additionalRuleId | INT UNSIGNED  | 关联 ibRuleAdditionalRules.id |
| tierLevel        | INT           | 层级序号                      |
| tierName         | VARCHAR(100)  | 层级名称                      |
| commissionRate   | DECIMAL(15,4) | 该层级佣金率                  |
| minimumVolume    | DECIMAL(15,2) | 最小交易量（手）              |
| maximumVolume    | VARCHAR(50)   | 最大交易量或"Unlimited"       |

---

### 3. IB合作伙伴 (IB Partners)

#### 3.1 `ibPartners` - IB合作伙伴主表

存储所有已批准的IB合作伙伴。

| 字段                  | 类型            | 说明                                       |
| --------------------- | --------------- | ------------------------------------------ |
| id                    | INT UNSIGNED    | 主键                                       |
| ibCode                | VARCHAR(50)     | IB代码：IB-2024-001                        |
| userId                | INT UNSIGNED    | 关联 clientUsers.id（如果从客户转换）      |
| companyName           | VARCHAR(200)    | 公司或个人名称                             |
| ibType                | ENUM            | IB类型：individual, company                |
| tierLevelId           | INT UNSIGNED    | 分配的层级级别                             |
| contactPerson         | VARCHAR(100)    | 联系人姓名                                 |
| contactEmail          | VARCHAR(200)    | 联系邮箱                                   |
| contactPhone          | VARCHAR(50)     | 联系电话                                   |
| country               | VARCHAR(100)    | 国家                                       |
| address               | VARCHAR(500)    | 地址                                       |
| website               | VARCHAR(200)    | 网站                                       |
| totalClients          | INT             | 总客户数                                   |
| activeClients         | INT             | 活跃客户数                                 |
| totalCommissionEarned | DECIMAL(15,2)   | 累计佣金收入                               |
| totalTradingVolume    | DECIMAL(20,2)   | 总交易量（USD）                            |
| registrationDate      | DATETIME        | 注册日期                                   |
| approvalDate          | DATETIME        | 批准日期                                   |
| approvedBy            | BIGINT UNSIGNED | 批准人（管理员ID）                         |
| status                | ENUM            | 状态：active, inactive, pending, suspended |

#### 3.2 `ibPartnerRuleAssignments` - IB规则分配

将IB合作伙伴与佣金规则关联（支持多规则）。

| 字段        | 类型            | 说明               |
| ----------- | --------------- | ------------------ |
| id          | INT UNSIGNED    | 主键               |
| ibPartnerId | INT UNSIGNED    | IB合作伙伴ID       |
| ruleId      | INT UNSIGNED    | 佣金规则ID         |
| assignedAt  | DATETIME        | 分配时间           |
| assignedBy  | BIGINT UNSIGNED | 分配人（管理员ID） |

**特点：**

- 一个IB可以分配多个佣金规则
- 不同规则可以应用于不同产品类型

#### 3.3 `ibPartnerPaymentSettings` - IB支付设置

存储IB的支付方式配置。

| 字段                | 类型         | 说明                                          |
| ------------------- | ------------ | --------------------------------------------- |
| id                  | INT UNSIGNED | 主键                                          |
| ibPartnerId         | INT UNSIGNED | IB合作伙伴ID                                  |
| paymentMethod       | ENUM         | 支付方式：bank_transfer, crypto, paypal, wise |
| bankName            | VARCHAR(200) | 银行名称                                      |
| accountHolderName   | VARCHAR(200) | 账户持有人                                    |
| accountNumber       | VARCHAR(100) | 账号（加密或掩码）                            |
| swiftCode           | VARCHAR(50)  | SWIFT代码                                     |
| iban                | VARCHAR(100) | IBAN                                          |
| cryptoWalletAddress | VARCHAR(255) | 加密货币钱包地址                              |
| cryptoCurrency      | VARCHAR(20)  | 加密货币类型                                  |
| currency            | VARCHAR(10)  | 货币                                          |
| isDefault           | TINYINT(1)   | 是否默认支付方式                              |

#### 3.4 `ibPartnerDocuments` - IB合作伙伴文档

存储IB上传的文档。

| 字段         | 类型          | 说明                                          |
| ------------ | ------------- | --------------------------------------------- |
| id           | INT UNSIGNED  | 主键                                          |
| ibPartnerId  | INT UNSIGNED  | IB合作伙伴ID                                  |
| documentName | VARCHAR(200)  | 文档名称                                      |
| documentType | VARCHAR(100)  | 文档类型：agreement, registration, tax, other |
| fileName     | VARCHAR(500)  | 原始文件名                                    |
| filePath     | VARCHAR(1000) | 服务器文件路径                                |
| fileSize     | BIGINT        | 文件大小（字节）                              |
| fileMimeType | VARCHAR(100)  | MIME类型                                      |
| uploadedAt   | DATETIME      | 上传时间                                      |

#### 3.5 `ibNetworkHierarchy` - IB网络层级

定义IB之间的父子关系（网络结构）。

| 字段              | 类型         | 说明                                |
| ----------------- | ------------ | ----------------------------------- |
| id                | INT UNSIGNED | 主键                                |
| ibPartnerId       | INT UNSIGNED | 子级IB                              |
| parentIbPartnerId | INT UNSIGNED | 父级IB（NULL表示顶级IB）            |
| hierarchyLevel    | INT          | 层级深度 (1=顶级, 2=子IB, 3=子子IB) |
| establishedAt     | DATETIME     | 关系建立时间                        |

**网络结构：**

```
Tier 1 IB (Root)
├── Tier 2 IB #1
│   ├── Tier 3 IB
│   │   └── Clients
│   └── Direct Clients
├── Tier 2 IB #2
│   └── Clients
└── Direct Clients
```

#### 3.6 `ibClientRelationships` - IB客户关系

追踪哪些客户是由哪个IB推荐的。

| 字段                 | 类型          | 说明                    |
| -------------------- | ------------- | ----------------------- |
| id                   | INT UNSIGNED  | 主键                    |
| ibPartnerId          | INT UNSIGNED  | IB合作伙伴ID            |
| clientId             | INT UNSIGNED  | 客户ID (clientUsers.id) |
| referralCode         | VARCHAR(50)   | 使用的推荐码            |
| referralDate         | DATETIME      | 推荐日期                |
| isActive             | TINYINT(1)    | 客户是否仍在此IB下活跃  |
| clientLifetimeVolume | DECIMAL(20,2) | 客户总交易量            |
| commissionGenerated  | DECIMAL(15,2) | 该客户产生的总佣金      |

#### 3.7 `ibCommissionHistory` - 佣金历史

追踪所有IB佣金支付记录。

| 字段                  | 类型          | 说明                                                   |
| --------------------- | ------------- | ------------------------------------------------------ |
| id                    | INT UNSIGNED  | 主键                                                   |
| ibPartnerId           | INT UNSIGNED  | IB合作伙伴ID                                           |
| commissionPeriodStart | DATE          | 佣金周期开始                                           |
| commissionPeriodEnd   | DATE          | 佣金周期结束                                           |
| commissionAmount      | DECIMAL(15,2) | 佣金金额                                               |
| currency              | VARCHAR(10)   | 货币                                                   |
| tradingVolume         | DECIMAL(20,2) | 该周期交易量                                           |
| numberOfTrades        | INT           | 交易笔数                                               |
| paymentStatus         | ENUM          | 支付状态：pending, processing, paid, failed, cancelled |
| paymentDate           | DATETIME      | 支付日期                                               |
| paymentReference      | VARCHAR(200)  | 支付参考号                                             |
| paymentMethod         | VARCHAR(100)  | 支付方式                                               |

---

### 4. IB申请 (IB Applications)

#### 4.1 `ibApplications` - IB申请主表

存储所有IB申请。

| 字段                    | 类型            | 说明                        |
| ----------------------- | --------------- | --------------------------- |
| id                      | INT UNSIGNED    | 主键                        |
| applicantName           | VARCHAR(200)    | 申请人姓名                  |
| applicantEmail          | VARCHAR(200)    | 申请人邮箱                  |
| applicantPhone          | VARCHAR(50)     | 申请人电话                  |
| ibType                  | ENUM            | IB类型：individual, company |
| companyName             | VARCHAR(200)    | 公司名称（公司类型）        |
| country                 | VARCHAR(100)    | 国家                        |
| expectedClients         | VARCHAR(100)    | 预期客户数                  |
| yearsOfExperience       | INT             | 从业年限                    |
| hasPreviousIbExperience | TINYINT(1)      | 是否有IB经验                |
| applicationStatus       | ENUM            | 申请状态                    |
| reviewerId              | BIGINT UNSIGNED | 审核人（管理员ID）          |
| assignedTierLevelId     | INT UNSIGNED    | 分配的层级级别              |
| applicationDate         | DATETIME        | 申请日期                    |
| reviewStartDate         | DATETIME        | 审核开始日期                |
| reviewCompletedDate     | DATETIME        | 审核完成日期                |
| approvedBy              | BIGINT UNSIGNED | 批准人                      |
| rejectedBy              | BIGINT UNSIGNED | 拒绝人                      |
| rejectionReason         | TEXT            | 拒绝原因                    |
| additionalInfoRequest   | TEXT            | 请求的额外信息              |
| createdIbPartnerId      | INT UNSIGNED    | 批准后创建的IB合作伙伴ID    |

**申请状态：**

- `pending`: 待处理
- `in_review`: 审核中
- `approved`: 已批准
- `rejected`: 已拒绝
- `more_info_requested`: 请求更多信息

#### 4.2 `ibApplicationProductFocus` - 申请产品焦点

存储申请人的主要产品和目标市场。

| 字段              | 类型         | 说明                 |
| ----------------- | ------------ | -------------------- |
| id                | INT UNSIGNED | 主键                 |
| applicationId     | INT UNSIGNED | 申请ID               |
| primaryProducts   | TEXT         | 主要产品（JSON数组） |
| targetRegions     | TEXT         | 目标区域（JSON数组） |
| marketingChannels | TEXT         | 营销渠道（JSON数组） |

**JSON格式示例：**

```json
{
  "primaryProducts": ["Forex", "Crypto", "Metals"],
  "targetRegions": ["North America", "Europe", "Asia Pacific"],
  "marketingChannels": ["Website/Blog", "Social Media", "Email Marketing"]
}
```

#### 4.3 `ibApplicationRuleAssignments` - 申请规则预分配

批准前为申请预分配佣金规则。

| 字段          | 类型            | 说明               |
| ------------- | --------------- | ------------------ |
| id            | INT UNSIGNED    | 主键               |
| applicationId | INT UNSIGNED    | 申请ID             |
| ruleId        | INT UNSIGNED    | 佣金规则ID         |
| assignedAt    | DATETIME        | 分配时间           |
| assignedBy    | BIGINT UNSIGNED | 分配人（管理员ID） |

#### 4.4 `ibApplicationStatusHistory` - 申请状态历史

追踪申请状态变化。

| 字段           | 类型            | 说明     |
| -------------- | --------------- | -------- |
| id             | INT UNSIGNED    | 主键     |
| applicationId  | INT UNSIGNED    | 申请ID   |
| previousStatus | VARCHAR(50)     | 之前状态 |
| newStatus      | VARCHAR(50)     | 新状态   |
| changedBy      | BIGINT UNSIGNED | 修改人   |
| notes          | TEXT            | 备注     |

---

### 5. IB邀请 (IB Invitations)

#### 5.1 `ibInvitations` - IB邀请

追踪发送给现有客户的IB邀请。

| 字段               | 类型            | 说明                       |
| ------------------ | --------------- | -------------------------- |
| id                 | INT UNSIGNED    | 主键                       |
| invitationCode     | VARCHAR(100)    | 唯一邀请码                 |
| clientId           | INT UNSIGNED    | 客户ID                     |
| invitedBy          | BIGINT UNSIGNED | 邀请人（管理员ID）         |
| invitationMessage  | TEXT            | 自定义邀请消息             |
| selectedDocuments  | TEXT            | 需要审阅的文档（JSON数组） |
| invitationStatus   | ENUM            | 邀请状态                   |
| sentAt             | DATETIME        | 发送时间                   |
| viewedAt           | DATETIME        | 查看时间                   |
| respondedAt        | DATETIME        | 响应时间                   |
| expiresAt          | DATETIME        | 过期时间                   |
| createdIbPartnerId | INT UNSIGNED    | 接受后创建的IB合作伙伴ID   |

**邀请状态：**

- `sent`: 已发送
- `viewed`: 已查看
- `accepted`: 已接受
- `declined`: 已拒绝
- `expired`: 已过期

---

### 6. 文档模板 (Document Templates)

#### 6.1 `ibDocumentTemplates` - IB文档模板

存储IB必须审阅/签署的文档模板。

| 字段              | 类型         | 说明                 |
| ----------------- | ------------ | -------------------- |
| id                | INT UNSIGNED | 主键                 |
| documentTitle     | VARCHAR(200) | 文档标题             |
| documentContent   | LONGTEXT     | 富文本HTML内容       |
| iconClass         | VARCHAR(100) | FontAwesome图标类    |
| iconGradient      | VARCHAR(200) | 图标背景CSS渐变      |
| isRequired        | TINYINT(1)   | 是否必需             |
| displayOrder      | INT          | 显示顺序             |
| wordCount         | INT          | 字数统计             |
| characterCount    | INT          | 字符数统计           |
| estimatedReadTime | INT          | 预计阅读时间（分钟） |
| version           | VARCHAR(20)  | 文档版本             |
| isActive          | TINYINT(1)   | 是否启用             |

**默认文档：**

1. IB Partnership Agreement（IB合作协议）
2. Compliance & AML Declaration（合规与反洗钱声明）
3. Commission Payment Terms（佣金支付条款）
4. Data Protection & Confidentiality Agreement（数据保护与保密协议）

#### 6.2 `ibApplicationDocumentAcknowledgements` - 申请文档确认

追踪申请人审阅和签署的文档。

| 字段               | 类型         | 说明         |
| ------------------ | ------------ | ------------ |
| id                 | INT UNSIGNED | 主键         |
| applicationId      | INT UNSIGNED | 申请ID       |
| documentTemplateId | INT UNSIGNED | 文档模板ID   |
| acknowledged       | TINYINT(1)   | 是否已确认   |
| acknowledgedAt     | DATETIME     | 确认时间     |
| ipAddress          | VARCHAR(45)  | IP地址       |
| digitalSignature   | VARCHAR(500) | 数字签名数据 |

#### 6.3 `ibPartnerDocumentAcknowledgements` - 合作伙伴文档确认

追踪IB合作伙伴审阅和签署的文档。

| 字段               | 类型         | 说明                     |
| ------------------ | ------------ | ------------------------ |
| id                 | INT UNSIGNED | 主键                     |
| ibPartnerId        | INT UNSIGNED | IB合作伙伴ID             |
| documentTemplateId | INT UNSIGNED | 文档模板ID               |
| acknowledged       | TINYINT(1)   | 是否已确认               |
| acknowledgedAt     | DATETIME     | 确认时间                 |
| ipAddress          | VARCHAR(45)  | IP地址                   |
| digitalSignature   | VARCHAR(500) | 数字签名数据             |
| expiresAt          | DATETIME     | 文档过期日期（用于续签） |

---

### 7. 程序设置 (Program Settings)

#### 7.1 `ibProgramSettings` - IB程序全局设置

IB程序的全局配置。

| 字段         | 类型            | 说明                                            |
| ------------ | --------------- | ----------------------------------------------- |
| id           | INT UNSIGNED    | 主键                                            |
| settingKey   | VARCHAR(100)    | 设置键                                          |
| settingValue | TEXT            | 设置值                                          |
| settingType  | ENUM            | 值类型：boolean, string, number, json           |
| settingGroup | VARCHAR(100)    | 设置组：general, display, commission, documents |
| description  | VARCHAR(500)    | 描述                                            |
| updatedAt    | DATETIME        | 更新时间                                        |
| updatedBy    | BIGINT UNSIGNED | 更新人                                          |

**默认设置：**

- `enable_ib_program`: 启用IB程序
- `auto_approve_applications`: 自动批准申请
- `max_commission_rate`: 最大佣金率
- `min_trading_experience_months`: 最低交易经验（月）
- `application_processing_time`: 申请处理时间
- `default_tier_level`: 默认层级级别
- `require_document_signature`: 需要文档签名

---

### 8. 自定义配置 (Custom Configuration)

#### 8.1 `ibCustomSecurities` - 自定义证券

管理员定义的自定义证券类型。

| 字段                | 类型            | 说明     |
| ------------------- | --------------- | -------- |
| id                  | INT UNSIGNED    | 主键     |
| securityName        | VARCHAR(100)    | 证券名称 |
| securityDescription | VARCHAR(500)    | 证券描述 |
| createdAt           | DATETIME        | 创建时间 |
| createdBy           | BIGINT UNSIGNED | 创建人   |

#### 8.2 `ibCustomSymbols` - 自定义交易对

管理员定义的自定义交易对。

| 字段              | 类型            | 说明       |
| ----------------- | --------------- | ---------- |
| id                | INT UNSIGNED    | 主键       |
| symbolName        | VARCHAR(50)     | 交易对名称 |
| symbolDescription | VARCHAR(500)    | 交易对描述 |
| createdAt         | DATETIME        | 创建时间   |
| createdBy         | BIGINT UNSIGNED | 创建人     |

---

### 9. 性能追踪 (Performance Tracking)

#### 9.1 `ibPerformanceMetrics` - IB性能指标

按月追踪每个IB的性能指标。

| 字段                | 类型          | 说明                   |
| ------------------- | ------------- | ---------------------- |
| id                  | INT UNSIGNED  | 主键                   |
| ibPartnerId         | INT UNSIGNED  | IB合作伙伴ID           |
| metricPeriod        | DATE          | 指标周期（月份第一天） |
| totalClients        | INT           | 总客户数               |
| activeClients       | INT           | 活跃客户数             |
| newClients          | INT           | 新增客户数             |
| totalTradingVolume  | DECIMAL(20,2) | 交易量（USD）          |
| numberOfTrades      | INT           | 交易笔数               |
| commissionEarned    | DECIMAL(15,2) | 赚取佣金               |
| averageClientValue  | DECIMAL(15,2) | 平均客户价值           |
| clientRetentionRate | DECIMAL(5,2)  | 客户留存率             |

#### 9.2 `ibReferralCodes` - 推荐码

IB合作伙伴的唯一推荐码。

| 字段          | 类型         | 说明                            |
| ------------- | ------------ | ------------------------------- |
| id            | INT UNSIGNED | 主键                            |
| ibPartnerId   | INT UNSIGNED | IB合作伙伴ID                    |
| referralCode  | VARCHAR(50)  | 唯一推荐码                      |
| codeName      | VARCHAR(100) | 推荐码名称                      |
| codeType      | ENUM         | 类型：default, campaign, custom |
| isActive      | TINYINT(1)   | 是否启用                        |
| usageCount    | INT          | 使用次数                        |
| maxUsageLimit | INT          | 最大使用限制                    |
| expiresAt     | DATETIME     | 过期时间                        |

---

### 10. 活动日志 (Activity Log)

#### 10.1 `ibActivityLog` - IB活动日志

记录所有IB相关的重要活动。

| 字段                | 类型            | 说明                              |
| ------------------- | --------------- | --------------------------------- |
| id                  | BIGINT UNSIGNED | 主键                              |
| ibPartnerId         | INT UNSIGNED    | 相关IB合作伙伴                    |
| applicationId       | INT UNSIGNED    | 相关申请                          |
| activityType        | VARCHAR(100)    | 活动类型                          |
| activityDescription | TEXT            | 活动描述                          |
| performedBy         | BIGINT UNSIGNED | 执行人ID                          |
| performedByType     | ENUM            | 执行人类型：admin, client, system |
| ipAddress           | VARCHAR(45)     | IP地址                            |
| metadata            | TEXT            | 附加JSON元数据                    |
| createdAt           | DATETIME        | 创建时间                          |

**常见活动类型：**

- `application_submitted`: 申请提交
- `application_approved`: 申请批准
- `application_rejected`: 申请拒绝
- `rule_assigned`: 规则分配
- `tier_changed`: 层级变更
- `commission_calculated`: 佣金计算
- `commission_paid`: 佣金支付
- `client_referred`: 客户推荐
- `document_signed`: 文档签署

---

### 11. 统计汇总 (Statistics)

#### 11.1 `ibStatisticsSummary` - IB统计汇总

预计算的统计数据用于快速加载仪表板。

| 字段                      | 类型          | 说明           |
| ------------------------- | ------------- | -------------- |
| id                        | INT UNSIGNED  | 主键           |
| summaryDate               | DATE          | 统计日期       |
| totalIbPartners           | INT           | 总IB合作伙伴数 |
| activeIbPartners          | INT           | 活跃IB数       |
| pendingApplications       | INT           | 待处理申请数   |
| approvedApplicationsToday | INT           | 今日批准数     |
| totalClientsReferred      | INT           | 总推荐客户数   |
| totalCommissionPaid       | DECIMAL(20,2) | 总支付佣金     |
| totalTradingVolume        | DECIMAL(25,2) | 总交易量       |

---

## 视图 (Views)

### 1. `vw_ibPartnersSummary` - IB合作伙伴汇总视图

提供IB合作伙伴的完整概览，包括层级、规则分配和统计。

### 2. `vw_ibApplicationsDetails` - IB申请详情视图

提供申请的完整信息，包括产品焦点和预分配规则。

### 3. `vw_ibCommissionRulesSummary` - 佣金规则汇总视图

提供规则的完整信息，包括产品数量、额外规则和分配的IB数量。

---

## 存储过程 (Stored Procedures)

### 1. `sp_approveIbApplication` - 批准IB申请

批准申请并自动创建IB合作伙伴记录。

**参数：**

- `IN p_applicationId`: 申请ID
- `IN p_approvedBy`: 批准人ID
- `IN p_tierLevelId`: 分配的层级ID
- `OUT p_ibPartnerId`: 创建的IB合作伙伴ID
- `OUT p_success`: 是否成功
- `OUT p_message`: 返回消息

**功能：**

1. 验证申请状态
2. 生成唯一IB代码
3. 创建IB合作伙伴记录
4. 更新申请状态为"approved"
5. 复制规则分配到IB合作伙伴
6. 记录活动日志

**使用示例：**

```sql
CALL sp_approveIbApplication(1, 100, 1, @ibId, @success, @msg);
SELECT @ibId, @success, @msg;
```

### 2. `sp_rejectIbApplication` - 拒绝IB申请

拒绝申请并记录原因。

**参数：**

- `IN p_applicationId`: 申请ID
- `IN p_rejectedBy`: 拒绝人ID
- `IN p_rejectionReason`: 拒绝原因
- `OUT p_success`: 是否成功
- `OUT p_message`: 返回消息

**使用示例：**

```sql
CALL sp_rejectIbApplication(5, 100, 'Insufficient trading experience', @success, @msg);
SELECT @success, @msg;
```

### 3. `sp_calculateIbCommission` - 计算IB佣金

计算指定周期内的IB佣金。

**参数：**

- `IN p_ibPartnerId`: IB合作伙伴ID
- `IN p_periodStart`: 周期开始日期
- `IN p_periodEnd`: 周期结束日期
- `OUT p_totalCommission`: 总佣金
- `OUT p_success`: 是否成功
- `OUT p_message`: 返回消息

**使用示例：**

```sql
CALL sp_calculateIbCommission(1, '2024-01-01', '2024-01-31', @commission, @success, @msg);
SELECT @commission, @success, @msg;
```

### 4. `sp_getIbNetworkTree` - 获取IB网络树

递归获取IB的网络层级结构。

**参数：**

- `IN p_rootIbPartnerId`: 根IB合作伙伴ID
- `IN p_maxDepth`: 最大深度

**使用示例：**

```sql
CALL sp_getIbNetworkTree(1, 5);
```

---

## 触发器 (Triggers)

### 1. `trg_ibClientRelationship_afterInsert`

当添加IB-客户关系时，自动更新IB的客户计数。

### 2. `trg_ibClientRelationship_afterUpdate`

当更新IB-客户关系时，更新活跃客户计数。

### 3. `trg_ibClientRelationship_afterDelete`

当删除IB-客户关系时，减少客户计数。

### 4. `trg_ibPartners_beforeInsert`

在插入IB合作伙伴前自动生成IB代码（如果未提供）。

### 5. `trg_ibApplications_afterUpdate`

申请状态变更时自动记录到状态历史表。

### 6. `trg_ibDocumentTemplates_beforeUpdate`

更新文档模板时自动计算字数、字符数和阅读时间。

---

## 常用查询示例 (Common Queries)

### 1. 获取所有待处理的IB申请

```sql
SELECT
    app.id,
    app.applicantName,
    app.applicantEmail,
    app.ibType,
    app.expectedClients,
    app.applicationDate,
    tl.tierName as assignedTier,
    COUNT(ara.ruleId) as assignedRulesCount
FROM ibApplications app
LEFT JOIN ibTierLevels tl ON app.assignedTierLevelId = tl.id
LEFT JOIN ibApplicationRuleAssignments ara ON app.id = ara.applicationId
WHERE app.applicationStatus = 'pending'
GROUP BY app.id
ORDER BY app.applicationDate DESC;
```

### 2. 获取IB合作伙伴及其所有分配的规则

```sql
SELECT
    ib.id,
    ib.ibCode,
    ib.companyName,
    ib.totalClients,
    ib.totalCommissionEarned,
    cr.ruleName,
    cr.paymentCycle,
    COUNT(rp.id) as ruleProductCount
FROM ibPartners ib
INNER JOIN ibPartnerRuleAssignments ra ON ib.id = ra.ibPartnerId
INNER JOIN ibCommissionRules cr ON ra.ruleId = cr.id
LEFT JOIN ibRuleProducts rp ON cr.id = rp.ruleId
WHERE ib.id = 1
GROUP BY ib.id, cr.id;
```

### 3. 获取IB网络层级结构

```sql
CALL sp_getIbNetworkTree(1, 5);
```

### 4. 获取所有活跃佣金规则及其产品配置

```sql
SELECT
    cr.id,
    cr.ruleName,
    cr.ruleType,
    cr.paymentCycle,
    cr.minimumPayout,
    rp.productName,
    rp.commissionType,
    rp.commissionRate,
    rp.additionalRate
FROM ibCommissionRules cr
INNER JOIN ibRuleProducts rp ON cr.id = rp.ruleId
WHERE cr.status = 'active'
ORDER BY cr.id, rp.id;
```

### 5. 获取表现最佳的IB合作伙伴

```sql
SELECT
    ib.ibCode,
    ib.companyName,
    ib.totalClients,
    ib.totalCommissionEarned,
    ib.totalTradingVolume,
    tl.tierName,
    COUNT(DISTINCT cr.clientId) as directClients
FROM ibPartners ib
LEFT JOIN ibTierLevels tl ON ib.tierLevelId = tl.id
LEFT JOIN ibClientRelationships cr ON ib.id = cr.ibPartnerId
WHERE ib.status = 'active'
GROUP BY ib.id
ORDER BY ib.totalCommissionEarned DESC
LIMIT 10;
```

### 6. 获取IB申请统计

```sql
SELECT
    COUNT(*) as totalApplications,
    SUM(CASE WHEN applicationStatus = 'pending' THEN 1 ELSE 0 END) as pendingCount,
    SUM(CASE WHEN applicationStatus = 'in_review' THEN 1 ELSE 0 END) as inReviewCount,
    SUM(CASE WHEN applicationStatus = 'approved' THEN 1 ELSE 0 END) as approvedCount,
    SUM(CASE WHEN applicationStatus = 'rejected' THEN 1 ELSE 0 END) as rejectedCount,
    SUM(CASE WHEN DATE(applicationDate) = CURDATE() THEN 1 ELSE 0 END) as todayApplications
FROM ibApplications;
```

### 7. 获取佣金规则使用统计

```sql
SELECT * FROM vw_ibCommissionRulesSummary
WHERE status = 'active'
ORDER BY assignedIbCount DESC;
```

### 8. 获取即将过期的IB文档

```sql
SELECT
    ib.ibCode,
    ib.companyName,
    dt.documentTitle,
    pda.acknowledgedAt,
    pda.expiresAt,
    DATEDIFF(pda.expiresAt, CURDATE()) as daysUntilExpiry
FROM ibPartnerDocumentAcknowledgements pda
INNER JOIN ibPartners ib ON pda.ibPartnerId = ib.id
INNER JOIN ibDocumentTemplates dt ON pda.documentTemplateId = dt.id
WHERE pda.expiresAt IS NOT NULL
  AND pda.expiresAt <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  AND ib.status = 'active'
ORDER BY pda.expiresAt ASC;
```

---

## 数据关系图 (Entity Relationships)

### 核心关系：

```
ibTierLevels (层级级别)
    ↓ (多对一)
ibPartners (IB合作伙伴) ← ibApplications (IB申请)
    ↓ (一对多)
    ├── ibPartnerRuleAssignments (规则分配)
    │       ↓ (多对一)
    │   ibCommissionRules (佣金规则)
    │       ↓ (一对多)
    │       ├── ibRuleProducts (产品配置)
    │       └── ibRuleAdditionalRules (额外规则)
    │               ↓ (一对多)
    │           ibRuleCommissionTiers (佣金层级)
    │
    ├── ibPartnerPaymentSettings (支付设置)
    ├── ibPartnerDocuments (文档)
    ├── ibClientRelationships (客户关系)
    │       ↓ (多对一)
    │   clientUsers (客户)
    │
    ├── ibNetworkHierarchy (网络层级)
    ├── ibCommissionHistory (佣金历史)
    ├── ibPerformanceMetrics (性能指标)
    └── ibReferralCodes (推荐码)

ibApplications (申请)
    ↓ (一对一)
    ├── ibApplicationProductFocus (产品焦点)
    ├── ibApplicationRuleAssignments (规则预分配)
    └── ibApplicationDocumentAcknowledgements (文档确认)
            ↓ (多对一)
        ibDocumentTemplates (文档模板)

ibInvitations (邀请)
    ↓ (多对一)
    ├── clientUsers (被邀请客户)
    └── ibPartners (创建的IB合作伙伴)
```

---

## 业务流程 (Business Flows)

### 1. IB申请审批流程

```
1. 客户提交IB申请
   → INSERT INTO ibApplications
   → INSERT INTO ibApplicationProductFocus

2. 管理员分配审核人
   → UPDATE ibApplications SET reviewerId = X, applicationStatus = 'in_review'

3. 管理员配置层级和规则
   → UPDATE ibApplications SET assignedTierLevelId = X
   → INSERT INTO ibApplicationRuleAssignments

4. 管理员批准申请
   → CALL sp_approveIbApplication(...)
   → 创建 ibPartners 记录
   → 复制规则分配到 ibPartnerRuleAssignments
   → 记录到 ibActivityLog

5. IB开始运营
   → 推荐客户 → INSERT INTO ibClientRelationships
   → 生成佣金 → INSERT INTO ibCommissionHistory
```

### 2. 邀请客户成为IB流程

```
1. 管理员选择客户并发送邀请
   → INSERT INTO ibInvitations
   → 发送邮件通知

2. 客户查看邀请
   → UPDATE ibInvitations SET invitationStatus = 'viewed', viewedAt = NOW()

3. 客户接受邀请并审阅文档
   → INSERT INTO ibApplicationDocumentAcknowledgements
   → 完成申请流程

4. 创建IB合作伙伴记录
   → INSERT INTO ibPartners
   → UPDATE ibInvitations SET invitationStatus = 'accepted', createdIbPartnerId = X
```

### 3. 佣金计算和支付流程

```
1. 定期计算佣金（月度/周期）
   → CALL sp_calculateIbCommission(ibId, startDate, endDate, @commission, ...)
   → 基于客户交易数据和佣金规则计算

2. 创建佣金记录
   → INSERT INTO ibCommissionHistory

3. 处理支付
   → UPDATE ibCommissionHistory SET paymentStatus = 'processing'
   → 执行支付
   → UPDATE ibCommissionHistory SET paymentStatus = 'paid', paymentDate = NOW()

4. 更新IB统计
   → UPDATE ibPartners SET totalCommissionEarned = totalCommissionEarned + X
   → INSERT/UPDATE ibPerformanceMetrics
```

---

## 数据完整性约束 (Data Integrity)

### 外键约束：

- 所有关联表都使用外键确保引用完整性
- 级联删除（CASCADE）用于从属数据
- SET NULL 用于可选引用

### 唯一性约束：

- `ibPartners.ibCode`: 唯一IB代码
- `ibTierLevels.tierLevel`: 唯一层级编号
- `ibReferralCodes.referralCode`: 唯一推荐码
- `ibInvitations.invitationCode`: 唯一邀请码

### 自动维护：

- 触发器自动更新客户计数
- 触发器自动生成IB代码
- 触发器自动记录状态变更历史
- 触发器自动计算文档统计

---

## 性能优化 (Performance Optimization)

### 索引策略：

1. **主键索引**: 所有表都有自增主键
2. **外键索引**: 所有外键字段都有索引
3. **状态索引**: status, applicationStatus等常用过滤字段
4. **日期索引**: createdAt, applicationDate等时间字段
5. **复合索引**: 常见组合查询字段

### 查询优化建议：

- 使用视图（`vw_*`）获取汇总数据
- 使用存储过程处理复杂业务逻辑
- 使用 `ibStatisticsSummary` 获取仪表板统计（避免实时聚合）
- 对大表使用分页查询（LIMIT + OFFSET）

---

## 安全考虑 (Security Considerations)

### 敏感数据保护：

1. **密码**: 永远不存储明文密码，使用 passwordHash
2. **支付信息**: accountNumber 应该加密或掩码（****1234）
3. **个人信息**: 遵循GDPR和数据保护法规
4. **API密钥**: paymentGatewaySettings 中的密钥应加密存储

### 访问控制：

- 管理员权限验证（使用 adminUsers 表）
- 所有修改操作记录 updatedBy
- 活动日志记录所有关键操作（ibActivityLog）

---

## 数据迁移和维护 (Migration & Maintenance)

### 初始化顺序：

1. 运行 `ib_management_database.sql`
2. 验证外键依赖（需要先有 `clientUsers` 和 `adminUsers` 表）
3. 验证默认数据插入成功

### 定期维护任务：

1. **每日**: 更新 ibStatisticsSummary
2. **每月**: 计算和生成佣金记录
3. **每月**: 更新 ibPerformanceMetrics
4. **定期**: 清理过期邀请记录
5. **定期**: 归档旧的活动日志

### 数据备份：

- 关键表：ibPartners, ibCommissionRules, ibCommissionHistory
- 配置表：ibTierLevels, ibDocumentTemplates, ibProgramSettings
- 日志表：ibActivityLog（可定期归档）

---

## API集成建议 (API Integration Recommendations)

### RESTful API端点建议：

#### IB Applications:

- `GET /api/ib/applications` - 获取申请列表
- `GET /api/ib/applications/:id` - 获取申请详情
- `POST /api/ib/applications` - 创建新申请
- `PUT /api/ib/applications/:id` - 更新申请
- `POST /api/ib/applications/:id/approve` - 批准申请
- `POST /api/ib/applications/:id/reject` - 拒绝申请
- `POST /api/ib/applications/:id/request-info` - 请求更多信息

#### IB Partners:

- `GET /api/ib/partners` - 获取IB列表
- `GET /api/ib/partners/:id` - 获取IB详情
- `PUT /api/ib/partners/:id` - 更新IB信息
- `GET /api/ib/partners/:id/network` - 获取网络层级
- `GET /api/ib/partners/:id/clients` - 获取客户列表
- `GET /api/ib/partners/:id/commissions` - 获取佣金历史
- `POST /api/ib/partners/:id/rules` - 分配规则

#### IB Commission Rules:

- `GET /api/ib/rules` - 获取规则列表
- `GET /api/ib/rules/:id` - 获取规则详情
- `POST /api/ib/rules` - 创建新规则
- `PUT /api/ib/rules/:id` - 更新规则
- `DELETE /api/ib/rules/:id` - 删除规则
- `POST /api/ib/rules/:id/products` - 添加产品配置
- `POST /api/ib/rules/:id/additional-rules` - 添加额外规则

#### IB Invitations:

- `POST /api/ib/invitations` - 发送邀请
- `GET /api/ib/invitations/:code` - 验证邀请码
- `POST /api/ib/invitations/:code/accept` - 接受邀请
- `POST /api/ib/invitations/:code/decline` - 拒绝邀请

#### IB Settings:

- `GET /api/ib/settings` - 获取IB程序设置
- `PUT /api/ib/settings` - 更新设置
- `GET /api/ib/documents` - 获取文档模板列表
- `POST /api/ib/documents` - 创建文档模板
- `PUT /api/ib/documents/:id` - 更新文档模板

---

## 扩展功能建议 (Future Enhancements)

### 1. 多级佣金分配

实现父IB从子IB的客户中获得二级佣金。

**新表建议：**

```sql
CREATE TABLE `ibMultiLevelCommissionRules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `parentIbPartnerId` INT UNSIGNED,
  `childIbPartnerId` INT UNSIGNED,
  `commissionPercentage` DECIMAL(5,2) COMMENT '父IB从子IB佣金中获取的百分比',
  -- ...
);
```

### 2. IB推广材料

存储IB可下载的推广材料（横幅、图片、视频等）。

**新表建议：**

```sql
CREATE TABLE `ibMarketingMaterials` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `materialName` VARCHAR(200),
  `materialType` ENUM('banner','image','video','pdf','link'),
  `filePath` VARCHAR(1000),
  `downloadCount` INT DEFAULT 0,
  -- ...
);
```

### 3. IB培训和认证

跟踪IB培训课程和认证状态。

**新表建议：**

```sql
CREATE TABLE `ibTrainingPrograms` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `programName` VARCHAR(200),
  `programType` ENUM('onboarding','advanced','compliance'),
  `duration` INT COMMENT '培训时长（分钟）',
  -- ...
);

CREATE TABLE `ibPartnerTrainingProgress` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ibPartnerId` INT UNSIGNED,
  `programId` INT UNSIGNED,
  `progressPercentage` DECIMAL(5,2),
  `completedAt` DATETIME,
  `certificateIssued` TINYINT(1),
  -- ...
);
```

### 4. IB竞赛和激励

跟踪IB竞赛和特殊激励活动。

**新表建议：**

```sql
CREATE TABLE `ibCompetitions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `competitionName` VARCHAR(200),
  `competitionType` ENUM('volume','recruitment','retention'),
  `startDate` DATE,
  `endDate` DATE,
  `prizePool` DECIMAL(15,2),
  -- ...
);

CREATE TABLE `ibCompetitionParticipants` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `competitionId` INT UNSIGNED,
  `ibPartnerId` INT UNSIGNED,
  `currentScore` DECIMAL(15,2),
  `ranking` INT,
  -- ...
);
```

---

## 维护脚本 (Maintenance Scripts)

### 每日统计更新脚本：

```sql
-- 更新当日IB统计
INSERT INTO ibStatisticsSummary (
    summaryDate,
    totalIbPartners,
    activeIbPartners,
    pendingApplications,
    approvedApplicationsToday,
    totalClientsReferred,
    totalCommissionPaid,
    totalTradingVolume
)
SELECT
    CURDATE(),
    (SELECT COUNT(*) FROM ibPartners),
    (SELECT COUNT(*) FROM ibPartners WHERE status = 'active'),
    (SELECT COUNT(*) FROM ibApplications WHERE applicationStatus = 'pending'),
    (SELECT COUNT(*) FROM ibApplications WHERE DATE(reviewCompletedDate) = CURDATE() AND applicationStatus = 'approved'),
    (SELECT SUM(totalClients) FROM ibPartners),
    (SELECT SUM(totalCommissionEarned) FROM ibPartners),
    (SELECT SUM(totalTradingVolume) FROM ibPartners)
ON DUPLICATE KEY UPDATE
    totalIbPartners = VALUES(totalIbPartners),
    activeIbPartners = VALUES(activeIbPartners),
    pendingApplications = VALUES(pendingApplications),
    approvedApplicationsToday = VALUES(approvedApplicationsToday),
    totalClientsReferred = VALUES(totalClientsReferred),
    totalCommissionPaid = VALUES(totalCommissionPaid),
    totalTradingVolume = VALUES(totalTradingVolume);
```

### 过期邀请清理脚本：

```sql
-- 标记过期的邀请
UPDATE ibInvitations
SET invitationStatus = 'expired'
WHERE invitationStatus = 'sent'
  AND expiresAt < NOW();
```

---

## 故障排查 (Troubleshooting)

### 常见问题：

#### 1. 外键约束错误

**问题**: 创建表时报外键错误
**解决**: 确保先创建被引用的表（`clientUsers`, `adminUsers`）

#### 2. IB代码重复

**问题**: 插入时IB代码冲突
**解决**: 触发器会自动生成，如果手动插入确保代码唯一

#### 3. 佣金计算不准确

**问题**: 佣金金额与预期不符
**解决**: 检查规则配置、产品配置、额外规则是否正确

#### 4. 网络层级查询慢

**问题**: 递归查询性能问题
**解决**: 限制 `sp_getIbNetworkTree` 的 maxDepth 参数

---

## 测试数据 (Test Data)

数据库已包含测试数据：

- 4个层级级别（Tier 1-4）
- 5个佣金规则（Standard, Premium, Ultra, Crypto, Regional APAC）
- 3个IB合作伙伴
- 6个IB申请（不同状态）
- 4个文档模板
- 性能指标和佣金历史示例

---

## 版本历史 (Version History)

### Version 1.0 - 2024-11-13

- 初始版本
- 包含所有核心IB管理功能
- 支持多规则分配
- 支持网络层级结构
- 包含文档管理和签署跟踪

---

## 技术支持 (Technical Support)

如有问题或需要功能扩展，请联系开发团队。

**相关文档：**

- `FUNDING_DATABASE_README.md` - 资金管理数据库文档
- `KYC_DATABASE_README.md` - KYC管理数据库文档
- `LEADS_DATABASE_README.md` - 线索管理数据库文档

---

## License

© 2024 Utrada CRM. All rights reserved.
