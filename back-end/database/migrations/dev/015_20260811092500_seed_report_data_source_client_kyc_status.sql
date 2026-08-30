-- ============================================================
-- Custom Reports: seed data source for vw_client_kyc_status
-- View: ut-crm-db.vw_client_kyc_status
-- Fields: client identity, KYC submission status, progress metrics
-- ============================================================
-- Created: 2026-08-11
-- Environment: dev
-- Closest migration: 001_20260720104614_create_custom_reports_tables.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- Idempotent: uses fixed UUID and INSERT ... ON DUPLICATE KEY UPDATE.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Data source: vw_client_kyc_status
INSERT INTO `report_data_sources` (
  `id`, `display_name`, `source_type`, `schema_name`, `object_name`, `query_handler`, `created_at`
) VALUES (
  'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a',
  'Client KYC Status',
  'view',
  NULL,
  'vw_client_kyc_status',
  NULL,
  CURRENT_TIMESTAMP
)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `source_type` = VALUES(`source_type`),
  `schema_name` = VALUES(`schema_name`),
  `object_name` = VALUES(`object_name`);

-- Fields for vw_client_kyc_status
-- Dimension: identity / categorical columns
-- Measure: numeric / progress columns
-- Datetime: timestamp columns
INSERT INTO `report_data_source_fields` (`id`, `data_source_id`, `display_name`, `column_name`, `data_type`, `field_role`) VALUES
  ('f5c1a7e2-0001-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Client ID',       'clientId',           'string',    'dimension'),
  ('f5c1a7e2-0002-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Email',           'email',              'string',    'dimension'),
  ('f5c1a7e2-0003-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'First Name',      'firstName',          'string',    'dimension'),
  ('f5c1a7e2-0004-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Last Name',       'lastName',           'string',    'dimension'),
  ('f5c1a7e2-0005-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Country',         'country',            'string',    'dimension'),
  ('f5c1a7e2-0006-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Submission ID',   'submissionId',       'string',    'dimension'),
  ('f5c1a7e2-0007-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Template ID',     'templateId',        'string',    'dimension'),
  ('f5c1a7e2-0008-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Template Name',   'templateName',       'string',    'dimension'),
  ('f5c1a7e2-0009-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Submission Status','submissionStatus', 'string',    'dimension'),
  ('f5c1a7e2-0010-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Rejection Reason','rejectionReason',   'string',    'dimension'),
  ('f5c1a7e2-0011-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Submitted At',    'submittedAt',        'datetime',  'datetime'),
  ('f5c1a7e2-0012-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Reviewed At',     'reviewedAt',         'datetime',  'datetime'),
  ('f5c1a7e2-0013-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Created At',      'createdAt',          'datetime',  'datetime'),
  ('f5c1a7e2-0014-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Updated At',      'updatedAt',          'datetime',  'datetime'),
  ('f5c1a7e2-0015-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Answered Questions','answeredQuestions','integer',  'measure'),
  ('f5c1a7e2-0016-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Total Questions', 'totalQuestions',     'integer',   'measure'),
  ('f5c1a7e2-0017-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Progress %',      'progressPercentage', 'decimal',  'measure'),
  ('f5c1a7e2-0018-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Signed Documents','signedDocuments',    'integer',   'measure'),
  ('f5c1a7e2-0019-4d3e-9f4a-5b6c7d8e9f0a', 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a', 'Required Documents','requiredDocuments','integer', 'measure')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `data_type`    = VALUES(`data_type`),
  `field_role`   = VALUES(`field_role`);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DELETE FROM `report_data_source_fields` WHERE `data_source_id` = 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a';
-- DELETE FROM `report_data_sources` WHERE `id` = 'd5c1a7e2-1b2c-4d3e-9f4a-5b6c7d8e9f0a';
-- COMMIT;
