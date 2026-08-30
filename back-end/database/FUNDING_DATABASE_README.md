# Funding Management Database Documentation

## 概述 (Overview)

本文档说明出入金管理系统（Deposit & Withdrawal Management）相关的数据库结构。

## 相关页面 (Related Pages)

1. **DepositManagement.html** - 管理员存款管理页面
2. **WithdrawManagement.html** - 管理员提款管理页面
3. **TransactionSettings.html** - 交易设置配置页面
4. **client-transactions.html** - 客户端交易页面
5. **FundingReport.html** - 资金报告页面

## 数据库文件

### `funding_management_database.sql`

完整的出入金管理系统数据库，包含所有必要的表、视图、存储过程和触发器。

---

## 数据库表结构 (Table Structure)

### 1. 支付方式配置 (Payment Method Configuration)

#### `paymentMethods` - 支付方式主表

存储所有可用的支付方式（加密货币和法币）。

**字段说明**:

- `methodKey` - 唯一标识（bitcoin, ethereum, usdt, usdc, bank_transfer, alchemy_pay）
- `methodName` - 显示名称（Bitcoin, Ethereum, USDT等）
- `methodType` - 类型（crypto, fiat）
- `iconClass` - FontAwesome图标类名
- `shortCode` - 简码（BTC, ETH, USDT等）
- `networkName` - 网络名称（Bitcoin Mainnet, Ethereum ERC-20等）
- `isDepositEnabled` - 是否启用存款
- `isWithdrawalEnabled` - 是否启用提款
- `processingTime` - 处理时间描述

**对应页面功能**:

- DepositManagement.html - Payment Method列
- WithdrawManagement.html - Payment Method列
- client-transactions.html - 支付方式选择

---

#### `cryptoDepositAddresses` - 加密货币存款地址

存储组织的钱包地址用于接收客户存款。

**字段说明**:

- `paymentMethodId` - 关联支付方式ID
- `walletAddress` - 区块链钱包地址
- `networkType` - 网络类型（mainnet, erc20, trc20, bep20）
- `qrCodePath` - QR码图片路径
- `minimumDeposit` - 最小存款金额（加密货币单位）
- `minimumDepositUsd` - 最小存款金额（USD）
- `confirmationBlocks` - 所需确认区块数

**对应页面功能**:

- TransactionSettings.html - Cryptocurrency Deposit Addresses配置
- client-transactions.html - 显示存款地址和QR码

---

#### `paymentGatewaySettings` - 支付网关设置

存储第三方支付网关（如AlchemyPay）的配置信息。

**字段说明**:

- `gatewayKey` - 网关标识（alchemy_pay等）
- `isEnabled` - 是否启用
- `environment` - 环境（sandbox, production）
- `appId` - 应用ID
- `apiKey` - API密钥（加密存储）
- `secretKey` - 密钥（加密存储）
- `merchantName` - 商户名称
- `webhookUrl` - Webhook URL
- `returnUrl` - 返回URL
- `supportedFiatCurrencies` - 支持的法币（JSON数组）
- `supportedCryptoCurrencies` - 支持的加密货币（JSON数组）

**对应页面功能**:

- TransactionSettings.html - AlchemyPay Gateway Configuration

---

### 2. 交易限额和费用 (Transaction Limits & Fees)

#### `transactionLimits` - 交易限额配置

定义存款和提款的最小、最大、每日、每月限额。

**字段说明**:

- `transactionType` - 交易类型（deposit, withdrawal）
- `paymentType` - 支付类型（crypto, fiat, all）
- `minimumAmount` - 最小金额（USD）
- `maximumAmount` - 最大单笔金额（USD）
- `dailyLimit` - 每日限额（USD）
- `monthlyLimit` - 每月限额（USD）

**对应页面功能**:

- TransactionSettings.html - Transaction Limits配置
- client-transactions.html - 显示限额提示信息

---

#### `transactionFees` - 交易费用配置

定义不同交易类型的费用设置。

**字段说明**:

- `transactionType` - 交易类型（deposit, withdrawal）
- `paymentType` - 支付类型（crypto, fiat）
- `feeType` - 费用类型（percentage, fixed, both）
- `feePercentage` - 百分比费用（如2.5表示2.5%）
- `feeFixed` - 固定费用（USD）
- `chargeToClient` - 是否向客户收费（1=是，0=平台承担）

**对应页面功能**:

- TransactionSettings.html - Transaction Fees配置

---

### 3. 存款管理 (Deposit Management)

#### `deposits` - 存款交易主表

存储所有存款交易记录。

**核心字段**:

- `transactionId` - 唯一交易ID（格式：TXN-YYYYMMDD-XXXXX）
- `userId` - 客户用户ID
- `tradingAccountId` - 目标交易账户ID
- `paymentMethodId` - 支付方式ID
- `amount` - 存款金额（USD）
- `amountCrypto` - 加密货币金额
- `cryptoNetwork` - 加密货币网络
- `fromAddress` - 发送方地址
- `toAddress` - 接收方地址（我们的钱包）
- `networkFee` - 网络费用
- `platformFee` - 平台费用
- `netAmount` - 实际到账金额
- `exchangeRate` - 汇率（加密货币到USD）
- `status` - 状态（pending, processing, completed, failed, cancelled）
- `confirmations` - 当前确认数
- `requiredConfirmations` - 所需确认数
- `transactionHash` - 区块链交易哈希
- `gatewayTransactionId` - 外部网关交易ID
- `requestedAt` - 请求时间
- `approvedAt` - 批准时间
- `approvedBy` - 批准人（管理员ID）
- `completedAt` - 完成时间
- `adminNotes` - 管理员备注
- `clientNotes` - 客户备注

**对应页面功能**:

- DepositManagement.html - 完整的存款列表和详情
- FundingReport.html - 存款统计和列表
- client-transactions.html - 客户查看存款记录

---

#### `depositStatusHistory` - 存款状态历史

追踪存款的状态变更，用于显示交易时间线。

**字段说明**:

- `depositId` - 存款ID
- `previousStatus` - 之前的状态
- `newStatus` - 新状态
- `description` - 状态变更描述
- `changedBy` - 变更人（管理员ID，NULL表示系统自动变更）

**对应页面功能**:

- DepositManagement.html - Transaction Timeline（交易时间线）

---

#### `depositTags` - 存款标签主表

存储所有可用的存款标签。

**字段说明**:

- `tagName` - 标签名称
- `tagColor` - 背景颜色
- `textColor` - 文字颜色
- `isSystemTag` - 是否系统标签（不可删除）

**默认标签**:

- Large Amount - 大额存款
- VIP - VIP客户
- Crypto - 加密货币
- Fiat - 法币
- Verified - 已验证
- Priority - 优先处理
- Stablecoin - 稳定币

**对应页面功能**:

- DepositManagement.html - Tags列和批量添加标签功能

---

#### `depositTagAssignments` - 存款标签关联

关联存款和标签的多对多关系表。

---

### 4. 提款管理 (Withdrawal Management)

#### `withdrawals` - 提款交易主表

存储所有提款交易记录。

**核心字段**:

- `transactionId` - 唯一交易ID（格式：TXN-YYYYMMDD-WXXXXX）
- `userId` - 客户用户ID
- `tradingAccountId` - 源交易账户ID
- `paymentMethodId` - 支付方式ID
- `amount` - 提款金额（USD）
- `amountCrypto` - 加密货币金额
- `destinationAddress` - 目标地址（钱包地址或银行账户）
- `destinationLabel` - 地址标签
- `bankName` - 银行名称
- `accountHolderName` - 账户持有人姓名
- `accountNumber` - 账户号码
- `swiftBic` - SWIFT/BIC代码
- `networkFee` - 网络费用
- `platformFee` - 平台费用
- `netAmount` - 实际发送金额
- `status` - 状态（pending, processing, completed, rejected, cancelled）
- `withdrawalReason` - 提款原因
- `requestedAt` - 请求时间
- `approvedAt` - 批准时间
- `approvedBy` - 批准人
- `rejectedAt` - 拒绝时间
- `rejectedBy` - 拒绝人
- `rejectionReasonId` - 拒绝原因ID
- `rejectionNotes` - 拒绝备注
- `completedAt` - 完成时间
- `previousWithdrawalsCount30Days` - 30天内提款次数
- `previousWithdrawalsAmount30Days` - 30天内提款总额
- `accountBalance` - 请求时账户余额

**对应页面功能**:

- WithdrawManagement.html - 完整的提款列表和详情
- FundingReport.html - 提款统计和列表
- client-transactions.html - 客户查看提款记录

---

#### `withdrawalStatusHistory` - 提款状态历史

追踪提款的状态变更，用于显示交易时间线。

**对应页面功能**:

- WithdrawManagement.html - Transaction Timeline

---

#### `withdrawalRejectionReasons` - 提款拒绝原因

预定义的提款拒绝原因列表。

**默认拒绝原因**:

1. Insufficient Documentation - 文档不足
2. Suspicious Activity Detected - 检测到可疑活动
3. Exceeds Withdrawal Limit - 超过提款限额
4. Insufficient Available Balance - 余额不足
5. Invalid Destination Address/Account - 无效的目标地址
6. Active Trading Positions - 存在活跃交易持仓
7. Compliance/Regulatory Issue - 合规问题
8. Terms of Service Violation - 违反服务条款
9. Other (Custom Reason) - 其他自定义原因

**对应页面功能**:

- WithdrawManagement.html - Reject Withdrawal Modal（拒绝提款模态框）

---

#### `withdrawalTags` - 提款标签主表

存储所有可用的提款标签。

**默认标签**:

- Large Amount, VIP, Crypto, Bank Transfer, Verified, Priority, Urgent, Regular Client, BTC, Stablecoin

**对应页面功能**:

- WithdrawManagement.html - Tags列和批量添加标签功能

---

#### `withdrawalTagAssignments` - 提款标签关联

关联提款和标签的多对多关系表。

---

### 5. 文档请求系统 (Document Request System)

#### `withdrawalDocumentRequests` - 提款文档请求

当管理员需要客户提供额外文档或回答问题时创建的请求。

**字段说明**:

- `withdrawalId` - 关联的提款ID
- `requestStatus` - 请求状态（pending, submitted, approved, rejected）
- `requestedBy` - 请求人（管理员ID）
- `requestedAt` - 请求时间
- `submittedAt` - 客户提交时间
- `reviewedAt` - 审核时间
- `adminInstructions` - 管理员的额外说明

**对应页面功能**:

- WithdrawManagement.html - Need More Documents功能

---

#### `withdrawalDocumentRequestItems` - 文档请求项目

具体请求的问题或文档列表。

**字段说明**:

- `requestId` - 关联的请求ID
- `itemType` - 项目类型（question, document）
- **问题相关字段**:
  - `questionText` - 问题文本
  - `questionType` - 问题类型（Text Input, Single Choice, Multiple Choice等）
  - `questionOptions` - 选项（JSON数组）
  - `questionValidation` - 验证规则
  - `questionHelpText` - 帮助文本
- **文档相关字段**:
  - `documentName` - 文档名称
  - `documentType` - 文档类型图标（passport, id-card等）
  - `documentDescription` - 文档描述
  - `acceptedFileTypes` - 接受的文件类型（JSON数组）
- `isRequired` - 是否必填
- `clientResponse` - 客户的回答或上传的文件路径
- `respondedAt` - 客户回复时间

**对应页面功能**:

- WithdrawManagement.html - Add Question和Add Document模态框

---

### 6. 客户保存的钱包 (Client Saved Wallets)

#### `clientSavedWallets` - 客户保存的钱包地址

允许客户保存常用的钱包地址以便快速提款。

**字段说明**:

- `userId` - 客户用户ID
- `walletName` - 钱包名称（如"My BTC Wallet"）
- `paymentMethodId` - 支付方式ID
- `walletAddress` - 钱包地址
- `networkType` - 网络类型
- `isVerified` - 是否已验证
- `verifiedAt` - 验证时间
- `verificationMethod` - 验证方式
- `isDefault` - 是否默认钱包
- `lastUsedAt` - 最后使用时间
- `usageCount` - 使用次数

**对应页面功能**:

- client-transactions.html - Saved Wallets列表和Add New Wallet功能

---

### 7. 通知设置 (Notification Settings)

#### `transactionNotificationSettings` - 交易通知设置

配置存款和提款的邮件通知规则。

**默认设置**:

- `clientEmailNotifications` - 客户邮件通知
- `adminEmailNotifications` - 管理员邮件通知
- `adminNotificationEmails` - 管理员通知邮箱列表
- `largeDepositAlerts` - 大额存款提醒
- `largeDepositThreshold` - 大额存款阈值
- `largeWithdrawalAlerts` - 大额提款提醒
- `largeWithdrawalThreshold` - 大额提款阈值

**对应页面功能**:

- TransactionSettings.html - Deposit Notifications配置

---

### 8. 搜索标签 (Search Tags)

#### `transactionSearchTags` - 交易搜索标签

用于快速过滤交易的预定义搜索标签。

**字段说明**:

- `tagName` - 标签显示名称
- `searchKeywords` - 搜索关键词
- `transactionType` - 适用的交易类型（deposit, withdrawal, both）

**默认搜索标签**:

- Bitcoin, Ethereum, Pending, Large Amount, Bank Transfer, Crypto Only

**对应页面功能**:

- DepositManagement.html - Quick Search Tags
- WithdrawManagement.html - Quick Search Tags

---

## 视图 (Views)

### `vAllTransactions`

合并存款和提款的统一视图，用于综合报告。

**包含字段**:

- 基本交易信息
- 客户信息（姓名、邮箱）
- 交易类型标识（deposit/withdrawal）
- 支付方式信息
- 状态和时间戳

**对应页面功能**:

- FundingReport.html - Recent Transactions表格

---

### `vDepositsSummary`

存款汇总视图，包含标签信息。

**对应页面功能**:

- DepositManagement.html - 存款列表展示
- FundingReport.html - 存款统计

---

### `vWithdrawalsSummary`

提款汇总视图，包含标签和拒绝原因。

**对应页面功能**:

- WithdrawManagement.html - 提款列表展示
- FundingReport.html - 提款统计

---

## 存储过程 (Stored Procedures)

### `spCreateDeposit`

创建新的存款记录，自动计算费用和生成交易ID。

**参数**:

- IN: userId, tradingAccountId, paymentMethodId, amount, amountCrypto, fromAddress, ipAddress
- OUT: transactionId, depositId

**功能**:

- 生成唯一交易ID
- 根据支付类型获取费用配置
- 计算平台费用和净到账金额
- 创建存款记录
- 创建初始状态历史记录

---

### `spCreateWithdrawal`

创建新的提款记录，包含历史提款统计。

**参数**:

- IN: userId, tradingAccountId, paymentMethodId, amount, destinationAddress, destinationLabel, withdrawalReason, ipAddress
- OUT: transactionId, withdrawalId

**功能**:

- 生成唯一交易ID（带W前缀）
- 计算费用和净发送金额
- 统计30天内的提款历史
- 创建提款记录
- 创建初始状态历史记录

---

### `spApproveDeposit`

批准存款，更新状态为completed。

**参数**:

- IN: depositId, approvedBy (admin user ID), adminNotes

---

### `spApproveWithdrawal`

批准提款，更新状态为processing。

**参数**:

- IN: withdrawalId, approvedBy (admin user ID), adminNotes

---

### `spRejectWithdrawal`

拒绝提款，记录拒绝原因。

**参数**:

- IN: withdrawalId, rejectedBy, rejectionReasonId, rejectionNotes, customReason

---

### `spCompleteWithdrawal`

完成提款，记录交易哈希。

**参数**:

- IN: withdrawalId, transactionHash, completedBy

**功能**:

- 更新状态为completed
- 记录完成时间
- 记录区块链交易哈希
- 更新相关保存钱包的使用统计

---

### `spGetTransactionStatistics`

获取指定日期范围内的交易统计数据。

**参数**:

- IN: startDate, endDate
- OUT: totalDeposits, totalWithdrawals, netFlow, depositCount, withdrawalCount

**对应页面功能**:

- FundingReport.html - Statistics Cards（统计卡片）
- DepositManagement.html - Statistics Header
- WithdrawManagement.html - Statistics Header

---

## 触发器 (Triggers)

### `trgDepositsAfterUpdate`

存款更新后自动执行：

- 自动记录状态变更历史
- 当确认数达到要求时，自动将状态从pending改为processing

---

### `trgWithdrawalsAfterUpdate`

提款更新后自动执行：

- 自动记录状态变更历史

---

## 数据关系图 (Entity Relationships)

```
clientUsers (from client_portal_database.sql)
    ↓ 1:N
deposits / withdrawals
    ↓ 1:N
depositStatusHistory / withdrawalStatusHistory

deposits / withdrawals
    ↓ N:1
paymentMethods
    ↓ 1:N (crypto only)
cryptoDepositAddresses

deposits ←→ depositTags (N:N via depositTagAssignments)
withdrawals ←→ withdrawalTags (N:N via withdrawalTagAssignments)

withdrawals
    ↓ 1:1
withdrawalDocumentRequests
    ↓ 1:N
withdrawalDocumentRequestItems

clientUsers
    ↓ 1:N
clientSavedWallets
```

---

## 业务流程 (Business Flows)

### 存款流程 (Deposit Flow)

1. **客户发起存款** (client-transactions.html)
   - 选择支付方式
   - 输入金额
   - 调用 `spCreateDeposit`
   - 状态: `pending`

2. **等待确认** (自动/系统)
   - 对于加密货币：等待区块链确认
   - 对于法币：等待支付网关确认
   - 触发器自动更新状态: `pending` → `processing`

3. **管理员审核** (DepositManagement.html)
   - 查看交易详情
   - 添加标签
   - 批准: 调用 `spApproveDeposit`
   - 状态: `processing` → `completed`

4. **完成** (系统)
   - 资金到账
   - 发送通知邮件
   - 记录完成时间

---

### 提款流程 (Withdrawal Flow)

1. **客户发起提款** (client-transactions.html)
   - 选择提款方式
   - 选择/输入目标地址
   - 输入金额和原因
   - 调用 `spCreateWithdrawal`
   - 状态: `pending`

2. **管理员审核** (WithdrawManagement.html)
   - 查看交易详情和客户信息
   - 检查30天内提款历史
   - **选项A**: 批准提款
     - 调用 `spApproveWithdrawal`
     - 状态: `pending` → `processing`
   - **选项B**: 需要更多文档
     - 创建 `withdrawalDocumentRequests`
     - 添加问题或文档请求
     - 客户收到通知
   - **选项C**: 拒绝提款
     - 选择拒绝原因
     - 调用 `spRejectWithdrawal`
     - 状态: `pending` → `rejected`

3. **处理中** (系统/手动)
   - 发送资金到客户地址
   - 记录交易哈希
   - 调用 `spCompleteWithdrawal`
   - 状态: `processing` → `completed`

4. **完成**
   - 资金已发送
   - 发送通知邮件
   - 记录完成时间

---

## 页面功能映射 (Page Feature Mapping)

### DepositManagement.html

**主要功能**:

- ✅ 存款列表展示 (`deposits` + `clientUsers` + `paymentMethods`)
- ✅ 搜索和过滤 (`transactionSearchTags`)
- ✅ 状态筛选 (通过 `deposits.status`)
- ✅ 批量操作
  - 批量审批 (`spApproveDeposit`)
  - 批量添加标签 (`depositTagAssignments`)
  - 批量导出 (查询 `vDepositsSummary`)
- ✅ 详细信息展示
  - 交易详情 (`deposits` 表字段)
  - 客户信息 (`clientUsers` 表)
  - 支付详情 (crypto addresses, network info)
  - 交易时间线 (`depositStatusHistory`)
- ✅ 标签管理
  - 查看标签 (`depositTags`)
  - 添加/删除标签 (`depositTagAssignments`)
- ✅ 统计信息
  - 今日总额、待处理数量、成功率等

**忽略的功能**:

- ❌ Assign Reviewer（分配审核员）
- ❌ Send Notification（发送通知 - 页面交互功能）

---

### WithdrawManagement.html

**主要功能**:

- ✅ 提款列表展示 (`withdrawals` + `clientUsers` + `paymentMethods`)
- ✅ 搜索和过滤 (`transactionSearchTags`)
- ✅ 批量操作
  - 批量审批 (`spApproveWithdrawal`)
  - 批量添加标签 (`withdrawalTagAssignments`)
  - 批量导出 (查询 `vWithdrawalsSummary`)
- ✅ 详细信息展示
  - 交易详情
  - 客户信息
  - 提款详情（目标地址、银行信息等）
  - 30天内提款历史
  - 交易时间线 (`withdrawalStatusHistory`)
- ✅ 操作按钮
  - Approve & Process (`spApproveWithdrawal`)
  - Reject (`spRejectWithdrawal` + `withdrawalRejectionReasons`)
  - Need More Documents (`withdrawalDocumentRequests` + `withdrawalDocumentRequestItems`)
- ✅ 拒绝原因系统
  - 预定义原因列表 (`withdrawalRejectionReasons`)
  - 自定义原因
- ✅ 文档请求系统
  - 添加问题（多种类型）
  - 添加文档请求
  - 实时预览

**忽略的功能**:

- ❌ Assign Reviewer
- ❌ Contact Client（页面交互功能）

---

### TransactionSettings.html

**主要功能**:

- ✅ 加密货币存款地址配置
  - BTC, ETH, USDT, USDC地址 (`cryptoDepositAddresses`)
  - 最小存款金额
  - 确认区块数
  - QR码生成（路径存储）
  - 启用/禁用开关
- ✅ AlchemyPay网关配置 (`paymentGatewaySettings`)
  - 环境选择（sandbox/production）
  - App ID, API Key, Secret Key
  - Merchant Name
  - Webhook URL, Return URL
  - 支持的法币和加密货币
- ✅ 交易限额设置 (`transactionLimits`)
  - 最小/最大存款金额
  - 每日/每月存款限额
  - 最小/最大提款金额
  - 每日/每月提款限额
- ✅ 交易费用设置 (`transactionFees`)
  - 加密货币存款费用
  - 法币存款费用
  - 固定费用
  - 费用承担方（客户/平台）
- ✅ 通知设置 (`transactionNotificationSettings`)
  - 客户邮件通知
  - 管理员邮件通知
  - 大额交易提醒
  - 提醒阈值

---

### client-transactions.html

**主要功能**:

- ✅ 账户余额展示
- ✅ 存款功能
  - 选择交易账户
  - 选择支付方式
  - 输入金额
  - 显示加密货币地址和QR码
  - 调用 `spCreateDeposit`
- ✅ 提款功能
  - 选择交易账户
  - 选择提款方式
  - 保存的钱包管理 (`clientSavedWallets`)
    - 查看已保存钱包
    - 添加新钱包
    - 编辑/删除钱包
    - 选择钱包或输入新地址
  - 银行账户信息
  - 输入金额
  - 调用 `spCreateWithdrawal`

---

### FundingReport.html

**主要功能**:

- ✅ 统计卡片
  - 总存款、总提款、净流入 (`spGetTransactionStatistics`)
  - 同比增长率
- ✅ 日期过滤
  - 预设时间段（今天、本周、本月、本季度）
  - 自定义日期范围
- ✅ 交易列表 (`vAllTransactions`)
  - 显示所有存款和提款
  - 客户信息
  - 交易类型
  - 金额（正数/负数）
  - 支付方式
  - 状态
- ✅ 搜索功能
- ✅ 批量导出（CSV, Excel）

---

## API端点建议 (Suggested API Endpoints)

### 存款相关

- `POST /api/deposits` - 创建存款
- `GET /api/deposits` - 获取存款列表
- `GET /api/deposits/{id}` - 获取存款详情
- `PUT /api/deposits/{id}/approve` - 批准存款
- `POST /api/deposits/{id}/tags` - 添加标签
- `DELETE /api/deposits/{id}/tags/{tagId}` - 删除标签

### 提款相关

- `POST /api/withdrawals` - 创建提款
- `GET /api/withdrawals` - 获取提款列表
- `GET /api/withdrawals/{id}` - 获取提款详情
- `PUT /api/withdrawals/{id}/approve` - 批准提款
- `PUT /api/withdrawals/{id}/reject` - 拒绝提款
- `PUT /api/withdrawals/{id}/complete` - 完成提款
- `POST /api/withdrawals/{id}/document-requests` - 创建文档请求
- `POST /api/withdrawals/{id}/tags` - 添加标签

### 配置相关

- `GET /api/payment-methods` - 获取支付方式列表
- `GET /api/crypto-addresses` - 获取加密货币地址
- `PUT /api/crypto-addresses/{id}` - 更新加密货币地址
- `GET /api/transaction-limits` - 获取交易限额
- `PUT /api/transaction-limits` - 更新交易限额
- `GET /api/transaction-fees` - 获取交易费用
- `PUT /api/transaction-fees` - 更新交易费用
- `GET /api/gateway-settings` - 获取网关设置
- `PUT /api/gateway-settings` - 更新网关设置

### 客户钱包

- `GET /api/client/wallets` - 获取保存的钱包
- `POST /api/client/wallets` - 添加钱包
- `PUT /api/client/wallets/{id}` - 更新钱包
- `DELETE /api/client/wallets/{id}` - 删除钱包

### 报告相关

- `GET /api/reports/funding` - 获取资金报告
- `GET /api/reports/statistics` - 获取统计数据
- `POST /api/reports/export` - 导出报告

---

## 安全考虑 (Security Considerations)

1. **敏感数据加密**:
   - API密钥和密码应加密存储
   - 钱包地址应验证格式
   - 银行账户信息需要加密

2. **权限控制**:
   - 只有授权管理员可以批准/拒绝交易
   - 客户只能访问自己的交易记录
   - 配置更改需要高级权限

3. **审计日志**:
   - 所有状态变更都有历史记录
   - 记录操作人和时间戳
   - 记录IP地址和User Agent

4. **交易验证**:
   - 检查余额充足性
   - 验证交易限额
   - 防止重复提交
   - 区块链交易验证

5. **防欺诈**:
   - 追踪30天内提款历史
   - 大额交易提醒
   - 可疑活动标记
   - 地址白名单验证

---

## 性能优化 (Performance Optimization)

### 已添加的索引

- 用户ID索引（快速查询用户的所有交易）
- 状态索引（快速筛选待处理交易）
- 日期索引（报告和统计查询）
- 复合索引（常见的联合查询）

### 查询优化建议

1. 使用视图进行复杂查询（已创建3个视图）
2. 对历史数据进行分区（按年或季度）
3. 定期归档旧交易记录
4. 使用存储过程减少往返次数

---

## 数据维护 (Data Maintenance)

### 定期任务

1. **清理过期数据**:

```sql
-- 删除6个月前的状态历史记录
DELETE FROM depositStatusHistory
WHERE createdAt < DATE_SUB(NOW(), INTERVAL 6 MONTH);

DELETE FROM withdrawalStatusHistory
WHERE createdAt < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

2. **归档旧交易**:

```sql
-- 创建归档表
CREATE TABLE deposits_archive LIKE deposits;
CREATE TABLE withdrawals_archive LIKE withdrawals;

-- 移动1年前的已完成交易到归档表
INSERT INTO deposits_archive
SELECT * FROM deposits
WHERE status = 'completed'
AND completedAt < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

3. **统计数据缓存**:
   建议创建汇总表存储每日统计数据，提高报告查询性能。

---

## 扩展功能建议 (Future Enhancements)

1. **交易审批工作流**:
   - 多级审批
   - 审批权限配置
   - 审批通知

2. **自动化规则**:
   - 自动批准小额存款
   - 基于风险评分的自动审核
   - 自动标签分配

3. **高级报告**:
   - 客户资金流向分析
   - 支付方式使用统计
   - 处理时效性分析
   - 异常交易检测

4. **区块链集成**:
   - 自动监听区块链交易
   - 实时更新确认数
   - 交易验证

5. **合规功能**:
   - AML检查集成
   - 大额交易报告
   - 可疑交易标记

---

## 测试数据 (Test Data)

SQL文件中包含了注释的示例数据，取消注释即可插入测试数据：

- 3笔示例存款（匹配DepositManagement.html）
- 3笔示例提款（匹配WithdrawManagement.html）

---

## 依赖关系 (Dependencies)

本数据库依赖以下现有表：

1. `clientUsers` (from client_portal_database.sql)
2. `tradingAccounts` (from trading_accounts_database.sql)
3. `adminUsers` (from admin_system_database.sql)

**执行顺序**:

1. client_portal_database.sql
2. trading_accounts_database.sql
3. admin_system_database.sql
4. **funding_management_database.sql** ← 当前文件

---

## 更新日志 (Changelog)

### Version 1.0 (2025-11-13)

- 初始版本
- 包含存款和提款的完整功能
- 支持加密货币和法币支付
- 标签系统
- 文档请求系统
- 交易配置和报告
- 客户保存钱包功能

---

## 联系与支持 (Contact & Support)

如有疑问或需要技术支持，请联系开发团队。

---

**Last Updated**: 2025-11-13
**Database Version**: 1.0
**Compatible With**: Utrada CRM v2.1+
