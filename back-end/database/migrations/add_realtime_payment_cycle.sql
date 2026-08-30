-- ============================================================
-- Migration: Add 'realtime' option to paymentCycle enum
-- ============================================================
-- Created: 2024-11-13
-- Description: Adds 'realtime' option to ibCommissionRules.paymentCycle field
-- ============================================================

-- Modify paymentCycle column to include 'realtime' option
ALTER TABLE `ibCommissionRules`
MODIFY COLUMN `paymentCycle` enum('realtime','daily','weekly','biweekly','monthly','quarterly') NOT NULL DEFAULT 'monthly'
COMMENT 'Payment cycle: realtime, daily, weekly, biweekly, monthly, quarterly';

-- Verify the change
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'ibCommissionRules'
  AND COLUMN_NAME = 'paymentCycle';

-- Success message
SELECT 'paymentCycle field updated successfully with realtime option' AS status;
