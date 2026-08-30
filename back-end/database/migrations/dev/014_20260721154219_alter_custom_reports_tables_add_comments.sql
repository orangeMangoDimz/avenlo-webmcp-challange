-- ============================================================
-- Custom Reports: add MySQL table comments
-- Tables: custom_reports, report_data_sources,
--         report_data_source_fields, report_widgets
-- ============================================================
-- Created: 2026-07-21
-- Environment: dev
-- Closest migration: 001_20260720104614_create_custom_reports_tables.sql
-- ============================================================
-- BACKUP FIRST: run scripts/backup.sh before applying.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

ALTER TABLE `custom_reports`
  COMMENT='Admin custom report definitions (report containers owned by admins)';

ALTER TABLE `report_data_sources`
  COMMENT='Report data source catalog. Seeded via migration/seeder only; cannot be created or managed from admin UI';

ALTER TABLE `report_data_source_fields`
  COMMENT='Queryable column metadata per report data source (dimension, measure, datetime)';

ALTER TABLE `report_widgets`
  COMMENT='Custom report widgets bound to a data source (table widgets under a report)';

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- ALTER TABLE `custom_reports` COMMENT='';
-- ALTER TABLE `report_data_sources` COMMENT='';
-- ALTER TABLE `report_data_source_fields` COMMENT='';
-- ALTER TABLE `report_widgets` COMMENT='';
-- COMMIT;
