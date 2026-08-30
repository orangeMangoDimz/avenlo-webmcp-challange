SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

START TRANSACTION;

INSERT INTO `adminSystemSettings`
(`settingKey`, `settingValue`, `settingType`, `category`, `displayName`, `description`, `isPublic`, `isEditable`, `sortOrder`, `createdAt`)
SELECT
    'developerMt4SyncEnabled', '0', 'boolean', 'developer', 'MT4 Sync', 'Inbound MT4 order and balance sync', 0, 1, 1, NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `adminSystemSettings` WHERE `settingKey` = 'developerMt4SyncEnabled'
);

INSERT INTO `adminSystemSettings`
(`settingKey`, `settingValue`, `settingType`, `category`, `displayName`, `description`, `isPublic`, `isEditable`, `sortOrder`, `createdAt`)
SELECT
    'developerMt5SyncEnabled', '0', 'boolean', 'developer', 'MT5 Sync', 'Inbound MT5 order and balance sync', 0, 1, 2, NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `adminSystemSettings` WHERE `settingKey` = 'developerMt5SyncEnabled'
);

INSERT INTO `adminSystemSettings`
(`settingKey`, `settingValue`, `settingType`, `category`, `displayName`, `description`, `isPublic`, `isEditable`, `sortOrder`, `createdAt`)
SELECT
    'developerEmailSendingEnabled', '0', 'boolean', 'developer', 'Email sending', 'Outbound email delivery', 0, 1, 3, NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `adminSystemSettings` WHERE `settingKey` = 'developerEmailSendingEnabled'
);

COMMIT;
