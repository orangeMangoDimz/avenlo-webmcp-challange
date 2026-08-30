-- ============================================================
-- Custom Reports: seed data source for vAllTransactions
-- View: vAllTransactions
-- Fields: client identity, amounts, payment/account, timestamps
-- ============================================================
-- Created: 2026-08-12
-- Environment: dev
-- Closest migration: 015_20260811092500_seed_report_data_source_client_kyc_status.sql
-- Existing source: 002_20260720104614_seed_vAllTransactions_data_source.sql
-- Existing fields: 016_20260811095300_alter_report_widgets_add_view_config.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: uses fixed UUID and INSERT ... ON DUPLICATE KEY UPDATE.
-- Reuses data source id a1000000-0000-4000-8000-000000000001 (do not duplicate).
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Data source: vAllTransactions
INSERT INTO `report_data_sources` (
  `id`, `display_name`, `source_type`, `schema_name`, `object_name`, `query_handler`, `created_at`
) VALUES (
  'a1000000-0000-4000-8000-000000000001',
  'All Transactions',
  'view',
  NULL,
  'vAllTransactions',
  NULL,
  CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `source_type`  = VALUES(`source_type`),
  `schema_name`  = VALUES(`schema_name`),
  `object_name`  = VALUES(`object_name`);

-- Fields for vAllTransactions
-- Dimension: identity / categorical columns
-- Datetime: timestamp columns
-- Measure: numeric columns
-- 101-110: ids from 012
-- 111-130: remaining view columns from all_views_260613.sql
INSERT INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`) VALUES
  ('a1000000-0000-4000-8000-000000000111', 'a1000000-0000-4000-8000-000000000001', 'ID',                      'id',                       'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000110', 'a1000000-0000-4000-8000-000000000001', 'Transaction ID',          'transactionId',            'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000109', 'a1000000-0000-4000-8000-000000000001', 'User ID',                 'userId',                   'integer',   'dimension'),
  ('a1000000-0000-4000-8000-000000000102', 'a1000000-0000-4000-8000-000000000001', 'First Name',              'firstName',                'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000103', 'a1000000-0000-4000-8000-000000000001', 'Last Name',               'lastName',                 'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000104', 'a1000000-0000-4000-8000-000000000001', 'Email',                   'email',                    'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000105', 'a1000000-0000-4000-8000-000000000001', 'Type',                    'transactionType',          'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000108', 'a1000000-0000-4000-8000-000000000001', 'Status',                  'status',                   'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000107', 'a1000000-0000-4000-8000-000000000001', 'Payment Method',          'paymentMethod',            'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000112', 'a1000000-0000-4000-8000-000000000001', 'Payment Type',            'paymentType',              'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000116', 'a1000000-0000-4000-8000-000000000001', 'Approved By',             'approvedBy',               'integer',   'dimension'),
  ('a1000000-0000-4000-8000-000000000118', 'a1000000-0000-4000-8000-000000000001', 'Rejected By',             'rejectedBy',               'integer',   'dimension'),
  ('a1000000-0000-4000-8000-000000000119', 'a1000000-0000-4000-8000-000000000001', 'From Trading Account',    'fromTradingAccountId',     'integer',   'dimension'),
  ('a1000000-0000-4000-8000-000000000120', 'a1000000-0000-4000-8000-000000000001', 'Target Trading Account',  'targetTradingAccountId',   'integer',   'dimension'),
  ('a1000000-0000-4000-8000-000000000121', 'a1000000-0000-4000-8000-000000000001', 'Target Platform Account', 'targetPlatformAccountId',  'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000122', 'a1000000-0000-4000-8000-000000000001', 'From Type',               'fromType',                 'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000123', 'a1000000-0000-4000-8000-000000000001', 'From Account Number',     'fromAccountNumber',        'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000124', 'a1000000-0000-4000-8000-000000000001', 'From Account Nickname',   'fromAccountNickname',      'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000125', 'a1000000-0000-4000-8000-000000000001', 'Target Account Number',   'targetAccountNumber',      'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000126', 'a1000000-0000-4000-8000-000000000001', 'Target Account Nickname', 'targetAccountNickname',    'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000127', 'a1000000-0000-4000-8000-000000000001', 'Target Platform Key',     'targetPlatformKey',        'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000128', 'a1000000-0000-4000-8000-000000000001', 'Target Platform Name',    'targetPlatformName',       'string',    'dimension'),
  ('a1000000-0000-4000-8000-000000000101', 'a1000000-0000-4000-8000-000000000001', 'Date / Time',             'requestedAt',              'datetime',  'datetime'),
  ('a1000000-0000-4000-8000-000000000114', 'a1000000-0000-4000-8000-000000000001', 'Approved At',             'approvedAt',               'datetime',  'datetime'),
  ('a1000000-0000-4000-8000-000000000115', 'a1000000-0000-4000-8000-000000000001', 'Completed At',            'completedAt',              'datetime',  'datetime'),
  ('a1000000-0000-4000-8000-000000000117', 'a1000000-0000-4000-8000-000000000001', 'Rejected At',             'rejectedAt',               'datetime',  'datetime'),
  ('a1000000-0000-4000-8000-000000000129', 'a1000000-0000-4000-8000-000000000001', 'Created At',              'createdAt',                'datetime',  'datetime'),
  ('a1000000-0000-4000-8000-000000000130', 'a1000000-0000-4000-8000-000000000001', 'Updated At',              'updatedAt',                'datetime',  'datetime'),
  ('a1000000-0000-4000-8000-000000000106', 'a1000000-0000-4000-8000-000000000001', 'Amount',                  'amount',                   'decimal',   'measure'),
  ('a1000000-0000-4000-8000-000000000113', 'a1000000-0000-4000-8000-000000000001', 'Quoted Amount',           'quotedAmount',             'decimal',   'measure')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `data_type`    = VALUES(`data_type`),
  `field_role`   = VALUES(`field_role`);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DELETE FROM `report_data_source_fields`
--   WHERE `data_source_id` = 'a1000000-0000-4000-8000-000000000001'
--     AND `id` IN (
--       'a1000000-0000-4000-8000-000000000111',
--       'a1000000-0000-4000-8000-000000000112',
--       'a1000000-0000-4000-8000-000000000113',
--       'a1000000-0000-4000-8000-000000000114',
--       'a1000000-0000-4000-8000-000000000115',
--       'a1000000-0000-4000-8000-000000000116',
--       'a1000000-0000-4000-8000-000000000117',
--       'a1000000-0000-4000-8000-000000000118',
--       'a1000000-0000-4000-8000-000000000119',
--       'a1000000-0000-4000-8000-000000000120',
--       'a1000000-0000-4000-8000-000000000121',
--       'a1000000-0000-4000-8000-000000000122',
--       'a1000000-0000-4000-8000-000000000123',
--       'a1000000-0000-4000-8000-000000000124',
--       'a1000000-0000-4000-8000-000000000125',
--       'a1000000-0000-4000-8000-000000000126',
--       'a1000000-0000-4000-8000-000000000127',
--       'a1000000-0000-4000-8000-000000000128',
--       'a1000000-0000-4000-8000-000000000129',
--       'a1000000-0000-4000-8000-000000000130'
--     );
-- COMMIT;
-- Do not delete the data source or 012 fields (101-110); those belong to 002/012.
