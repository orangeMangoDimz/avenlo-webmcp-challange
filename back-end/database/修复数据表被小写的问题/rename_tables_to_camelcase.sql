-- ============================================================
-- 表名重命名脚本：将小写表名转换为驼峰命名
-- 数据库：utrada_crm
-- 说明：由于线上服务器 lower_case_table_names=1 导致表名变成小写
--       此脚本将所有表名恢复为驼峰命名法
-- ============================================================
-- 执行前请先备份数据库！
-- ============================================================

-- 账户验证相关
ALTER TABLE `accountverificationfiles` RENAME TO `accountVerificationFiles`;
ALTER TABLE `accountverificationlogs` RENAME TO `accountVerificationLogs`;
ALTER TABLE `accountverifications` RENAME TO `accountVerifications`;

-- 管理员相关
ALTER TABLE `adminloginlogs` RENAME TO `adminLoginLogs`;
ALTER TABLE `adminnotificationdeliveries` RENAME TO `adminNotificationDeliveries`;
ALTER TABLE `adminnotifications` RENAME TO `adminNotifications`;
ALTER TABLE `adminpasswordhistory` RENAME TO `adminPasswordHistory`;
ALTER TABLE `adminpasswordresets` RENAME TO `adminPasswordResets`;
ALTER TABLE `adminpermissions` RENAME TO `adminPermissions`;
ALTER TABLE `adminrolepermissions` RENAME TO `adminRolePermissions`;
ALTER TABLE `adminroles` RENAME TO `adminRoles`;
ALTER TABLE `adminsessions` RENAME TO `adminSessions`;
ALTER TABLE `adminsystemnotifications` RENAME TO `adminSystemNotifications`;
ALTER TABLE `adminsystemsettings` RENAME TO `adminSystemSettings`;
ALTER TABLE `adminuserpermissions` RENAME TO `adminUserPermissions`;
ALTER TABLE `adminuserprofiles` RENAME TO `adminUserProfiles`;
ALTER TABLE `adminusers` RENAME TO `adminUsers`;

-- 分配和审批相关
ALTER TABLE `assignmentreminders` RENAME TO `assignmentReminders`;
ALTER TABLE `autoapprovallog` RENAME TO `autoApprovalLog`;
ALTER TABLE `autoapprovalrules` RENAME TO `autoApprovalRules`;
ALTER TABLE `bulkassignmentoperations` RENAME TO `bulkAssignmentOperations`;

-- 客户相关
ALTER TABLE `clientactivitylog` RENAME TO `clientActivityLog`;
ALTER TABLE `clientemailtemplates` RENAME TO `clientEmailTemplates`;
ALTER TABLE `clientemailverifications` RENAME TO `clientEmailVerifications`;
ALTER TABLE `clientinterfacetexts` RENAME TO `clientInterfaceTexts`;
ALTER TABLE `clientkycanswers` RENAME TO `clientKycAnswers`;
ALTER TABLE `clientkycdocumentsignatures` RENAME TO `clientKycDocumentSignatures`;
ALTER TABLE `clientkycsubmissions` RENAME TO `clientKycSubmissions`;
ALTER TABLE `clientnotificationdeliveries` RENAME TO `clientNotificationDeliveries`;
ALTER TABLE `clientnotifications` RENAME TO `clientNotifications`;
ALTER TABLE `clientpasswordresets` RENAME TO `clientPasswordResets`;
ALTER TABLE `clientsavedwallets` RENAME TO `clientSavedWallets`;
ALTER TABLE `clientsessions` RENAME TO `clientSessions`;
ALTER TABLE `clientsystemnotifications` RENAME TO `clientSystemNotifications`;
ALTER TABLE `clienttickets` RENAME TO `clientTickets`;
ALTER TABLE `clientusers` RENAME TO `clientUsers`;

-- 基础数据
-- countries 表名已经是正确的（首字母小写的复数形式），无需重命名
ALTER TABLE `countrylist` RENAME TO `countryList`;

-- 加密货币和存款相关
ALTER TABLE `cryptodepositaddresses` RENAME TO `cryptoDepositAddresses`;
ALTER TABLE `currencyexchangerates` RENAME TO `currencyExchangeRates`;
ALTER TABLE `depositnotes` RENAME TO `depositNotes`;
-- deposits 表名已经是正确的（首字母小写的复数形式），无需重命名
ALTER TABLE `depositstatushistory` RENAME TO `depositStatusHistory`;
ALTER TABLE `deposittagassignments` RENAME TO `depositTagAssignments`;
ALTER TABLE `deposittags` RENAME TO `depositTags`;

-- 邮件模板相关
ALTER TABLE `emailtemplates` RENAME TO `emailTemplates`;
ALTER TABLE `emailtemplatesectionsettings` RENAME TO `emailTemplateSectionSettings`;
ALTER TABLE `emailverificationsettings` RENAME TO `emailVerificationSettings`;

-- IB (Introducing Broker) 相关
ALTER TABLE `ibactivitylog` RENAME TO `ibActivityLog`;
ALTER TABLE `ibapplicationdocumentacknowledgements` RENAME TO `ibApplicationDocumentAcknowledgements`;
ALTER TABLE `ibapplicationproductfocus` RENAME TO `ibApplicationProductFocus`;
ALTER TABLE `ibapplicationruleassignments` RENAME TO `ibApplicationRuleAssignments`;
ALTER TABLE `ibapplications` RENAME TO `ibApplications`;
ALTER TABLE `ibapplicationstatushistory` RENAME TO `ibApplicationStatusHistory`;
ALTER TABLE `ibclientrelationships` RENAME TO `ibClientRelationships`;
ALTER TABLE `ibcommissionhistory` RENAME TO `ibCommissionHistory`;
ALTER TABLE `ibcommissionrules` RENAME TO `ibCommissionRules`;
ALTER TABLE `ibcustomsecurities` RENAME TO `ibCustomSecurities`;
ALTER TABLE `ibcustomsymbols` RENAME TO `ibCustomSymbols`;
ALTER TABLE `ibdocumenttemplates` RENAME TO `ibDocumentTemplates`;
ALTER TABLE `ibinvitations` RENAME TO `ibInvitations`;
ALTER TABLE `ibnetworkhierarchy` RENAME TO `ibNetworkHierarchy`;
ALTER TABLE `ibpartnerdocumentacknowledgements` RENAME TO `ibPartnerDocumentAcknowledgements`;
ALTER TABLE `ibpartnerdocuments` RENAME TO `ibPartnerDocuments`;
ALTER TABLE `ibpartnerpaymentsettings` RENAME TO `ibPartnerPaymentSettings`;
ALTER TABLE `ibpartnerruleassignments` RENAME TO `ibPartnerRuleAssignments`;
ALTER TABLE `ibpartners` RENAME TO `ibPartners`;
ALTER TABLE `ibperformancemetrics` RENAME TO `ibPerformanceMetrics`;
ALTER TABLE `ibprogramsettings` RENAME TO `ibProgramSettings`;
ALTER TABLE `ibreferralcodes` RENAME TO `ibReferralCodes`;
ALTER TABLE `ibruleadditionalrules` RENAME TO `ibRuleAdditionalRules`;
ALTER TABLE `ibrulecommissiontiers` RENAME TO `ibRuleCommissionTiers`;
ALTER TABLE `ibruleproducts` RENAME TO `ibRuleProducts`;
ALTER TABLE `ibstatisticssummary` RENAME TO `ibStatisticsSummary`;
ALTER TABLE `ibtierlevels` RENAME TO `ibTierLevels`;

-- 内部转账相关
ALTER TABLE `internaltransfernotes` RENAME TO `internalTransferNotes`;
ALTER TABLE `internaltransfers` RENAME TO `internalTransfers`;
ALTER TABLE `internaltransferstatushistory` RENAME TO `internalTransferStatusHistory`;
ALTER TABLE `internaltransfertagassignments` RENAME TO `internalTransferTagAssignments`;

-- IP语言检测
ALTER TABLE `iplanguagedetectionsettings` RENAME TO `ipLanguageDetectionSettings`;

-- KYC 相关
ALTER TABLE `kycconditionalrules` RENAME TO `kycConditionalRules`;
ALTER TABLE `kycemailnotificationtemplates` RENAME TO `kycEmailNotificationTemplates`;
ALTER TABLE `kycnoticesettings` RENAME TO `kycNoticeSettings`;
ALTER TABLE `kycquestioncategories` RENAME TO `kycQuestionCategories`;
ALTER TABLE `kycquestiondocumenttypes` RENAME TO `kycQuestionDocumentTypes`;
ALTER TABLE `kycquestionoptions` RENAME TO `kycQuestionOptions`;
ALTER TABLE `kycquestions` RENAME TO `kycQuestions`;
ALTER TABLE `kycrequirementitems` RENAME TO `kycRequirementItems`;
ALTER TABLE `kycresubmitanswers` RENAME TO `kycResubmitAnswers`;
ALTER TABLE `kycresubmitrequests` RENAME TO `kycResubmitRequests`;
ALTER TABLE `kycsectionapprovals` RENAME TO `kycSectionApprovals`;
ALTER TABLE `kycsettingschangelog` RENAME TO `kycSettingsChangeLog`;
ALTER TABLE `kycstatusmessagetemplates` RENAME TO `kycStatusMessageTemplates`;
ALTER TABLE `kycsubmissionactivitylog` RENAME TO `kycSubmissionActivityLog`;
ALTER TABLE `kyctemplatecountries` RENAME TO `kycTemplateCountries`;
ALTER TABLE `kyctemplatedocuments` RENAME TO `kycTemplateDocuments`;
ALTER TABLE `kyctemplateedithistory` RENAME TO `kycTemplateEditHistory`;
ALTER TABLE `kyctemplates` RENAME TO `kycTemplates`;
ALTER TABLE `kyctimeline` RENAME TO `kycTimeline`;

-- 语言包
ALTER TABLE `languagepacks` RENAME TO `languagePacks`;

-- Leads 相关
ALTER TABLE `leadactivitylog` RENAME TO `leadActivityLog`;
ALTER TABLE `leadassignmenthistory` RENAME TO `leadAssignmentHistory`;
ALTER TABLE `leadassignmentnotes` RENAME TO `leadAssignmentNotes`;
ALTER TABLE `leadassignments` RENAME TO `leadAssignments`;
ALTER TABLE `leadbulkoperations` RENAME TO `leadBulkOperations`;
ALTER TABLE `leadedithistory` RENAME TO `leadEditHistory`;
ALTER TABLE `leadkycstatus` RENAME TO `leadKycStatus`;
ALTER TABLE `leadstatushistory` RENAME TO `leadStatusHistory`;
ALTER TABLE `leadtagassignments` RENAME TO `leadTagAssignments`;
ALTER TABLE `leadtags` RENAME TO `leadTags`;

-- 法律文档
ALTER TABLE `legaldocuments` RENAME TO `legalDocuments`;
ALTER TABLE `legaldocumentsignatures` RENAME TO `legalDocumentSignatures`;

-- 登录页面相关
ALTER TABLE `loginpagebranding` RENAME TO `loginPageBranding`;
ALTER TABLE `loginsettingschangelog` RENAME TO `loginSettingsChangeLog`;

-- 密码强度设置
ALTER TABLE `passwordstrengthsettings` RENAME TO `passwordStrengthSettings`;

-- 支付相关
ALTER TABLE `paymentgatewaysettings` RENAME TO `paymentGatewaySettings`;
ALTER TABLE `paymentmethods` RENAME TO `paymentMethods`;

-- 注册表单
ALTER TABLE `registrationformfields` RENAME TO `registrationFormFields`;

-- 销售代表相关
ALTER TABLE `salesrepperformance` RENAME TO `salesRepPerformance`;
ALTER TABLE `salesrepresentatives` RENAME TO `salesRepresentatives`;

-- 搜索标签
ALTER TABLE `searchtags` RENAME TO `searchTags`;

-- 交易账户相关
ALTER TABLE `tradingaccountexternalaccounts` RENAME TO `tradingAccountExternalAccounts`;
ALTER TABLE `tradingaccounts` RENAME TO `tradingAccounts`;
ALTER TABLE `tradingplatformleverages` RENAME TO `tradingPlatformLeverages`;
ALTER TABLE `tradingplatforms` RENAME TO `tradingPlatforms`;

-- 交易相关
ALTER TABLE `transactionfees` RENAME TO `transactionFees`;
ALTER TABLE `transactionlimits` RENAME TO `transactionLimits`;
ALTER TABLE `transactionnotificationsettings` RENAME TO `transactionNotificationSettings`;
ALTER TABLE `transactionsearchtags` RENAME TO `transactionSearchTags`;
ALTER TABLE `transactionsecuritysettings` RENAME TO `transactionSecuritySettings`;

-- 提款相关
ALTER TABLE `withdrawaldocumentrequestitems` RENAME TO `withdrawalDocumentRequestItems`;
ALTER TABLE `withdrawaldocumentrequests` RENAME TO `withdrawalDocumentRequests`;
ALTER TABLE `withdrawalotpverifications` RENAME TO `withdrawalOtpVerifications`;
ALTER TABLE `withdrawalrejectionreasons` RENAME TO `withdrawalRejectionReasons`;
-- withdrawals 表名已经是正确的（首字母小写的复数形式），无需重命名
ALTER TABLE `withdrawalstatushistory` RENAME TO `withdrawalStatusHistory`;
ALTER TABLE `withdrawaltagassignments` RENAME TO `withdrawalTagAssignments`;
ALTER TABLE `withdrawaltags` RENAME TO `withdrawalTags`;

-- ============================================================
-- 重命名完成
-- ============================================================
-- 注意：
-- 1. 重命名表后，需要更新所有视图、触发器、存储过程中的表名引用
-- 2. 建议在执行此脚本前，先执行视图、触发器、存储过程的导出脚本
-- 3. 重命名完成后，重新导入更新后的视图、触发器、存储过程
-- ============================================================
