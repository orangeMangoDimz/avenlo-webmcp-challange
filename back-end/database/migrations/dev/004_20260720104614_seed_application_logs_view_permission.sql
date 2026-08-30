-- ============================================================
-- Seed permission: application_logs.view
-- ============================================================
-- Created: 2026-07-20
-- Environment: dev
-- Parent group: group_journal (falls back to group_systemsetting)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `adminPermissions` (
  `permissionKey`,
  `permissionName`,
  `permissionDisplayName`,
  `permissionDisplayNameZh`,
  `description`,
  `module`,
  `route`,
  `is_menu`,
  `parentId`,
  `sortOrder`,
  `isActive`,
  `createdAt`,
  `updatedAt`
)
SELECT
  'application_logs.view',
  'View Application Logs',
  'View Application Logs',
  '查看应用日志',
  'View database application logs',
  NULL,
  NULL,
  0,
  COALESCE(
    (SELECT id FROM adminPermissions WHERE permissionKey = 'group_journal' LIMIT 1),
    (SELECT id FROM adminPermissions WHERE permissionKey = 'group_systemsetting' LIMIT 1)
  ),
  40,
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM adminPermissions WHERE permissionKey = 'application_logs.view'
);

COMMIT;

-- ============================================================
-- Rollback
-- ============================================================
-- START TRANSACTION;
-- DELETE FROM adminPermissions WHERE permissionKey = 'application_logs.view';
-- COMMIT;
