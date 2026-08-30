-- ============================================================
-- Custom Reports schema
-- Tables: custom_reports, report_data_sources,
--         report_data_source_fields, report_widgets
-- ============================================================
-- Created: 2026-07-17
-- Environment: dev
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `custom_reports` (
  `id` CHAR(36) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_by` CHAR(36) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_data_sources` (
  `id` CHAR(36) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `source_type` VARCHAR(30) NOT NULL,
  `schema_name` VARCHAR(100) DEFAULT NULL,
  `object_name` VARCHAR(255) DEFAULT NULL,
  `query_handler` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_report_data_sources_source_type`
    CHECK (`source_type` IN ('table', 'view', 'query_handler', 'api'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_data_source_fields` (
  `id` CHAR(36) NOT NULL,
  `data_source_id` CHAR(36) NOT NULL,
  `display_name` VARCHAR(255) NOT NULL,
  `column_name` VARCHAR(255) NOT NULL,
  `data_type` VARCHAR(30) NOT NULL,
  `field_role` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_data_source_fields_source_column` (`data_source_id`, `column_name`),
  CONSTRAINT `chk_report_data_source_fields_field_role`
    CHECK (`field_role` IN ('dimension', 'measure', 'datetime')),
  CONSTRAINT `fk_report_data_source_fields_data_source`
    FOREIGN KEY (`data_source_id`) REFERENCES `report_data_sources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_widgets` (
  `id` CHAR(36) NOT NULL,
  `report_id` CHAR(36) NOT NULL,
  `data_source_id` CHAR(36) NOT NULL,
  `widget_type` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_widgets_report_id` (`report_id`),
  KEY `idx_report_widgets_data_source_id` (`data_source_id`),
  CONSTRAINT `fk_report_widgets_report`
    FOREIGN KEY (`report_id`) REFERENCES `custom_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_widgets_data_source`
    FOREIGN KEY (`data_source_id`) REFERENCES `report_data_sources` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
