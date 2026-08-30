-- ============================================================
-- Migration: Add updatedBy field to ibApplications table
-- ============================================================
-- Created: 2024-11-13
-- Description: Adds missing updatedBy field to ibApplications table
--              to support the trigger trg_ibApplications_afterUpdate
-- ============================================================

-- Add updatedBy column to ibApplications table
ALTER TABLE `ibApplications`
ADD COLUMN `updatedBy` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Admin user ID who last updated'
AFTER `updatedAt`;

-- Verify the column was added
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'ibApplications'
  AND COLUMN_NAME = 'updatedBy';

-- Success message
SELECT 'updatedBy field added successfully to ibApplications table' AS status;
