-- ============================================================
-- Seed report_data_sources: vAllTransactions
-- ============================================================
-- Created: 2026-07-17
-- Environment: dev
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `report_data_sources` (
  `id`,
  `display_name`,
  `source_type`,
  `schema_name`,
  `object_name`,
  `query_handler`,
  `created_at`
)
SELECT
  'a1000000-0000-4000-8000-000000000001',
  'All Transactions',
  'view',
  NULL,
  'vAllTransactions',
  NULL,
  CURRENT_TIMESTAMP
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM `report_data_sources`
  WHERE `object_name` = 'vAllTransactions'
     OR `id` = 'a1000000-0000-4000-8000-000000000001'
);

COMMIT;
