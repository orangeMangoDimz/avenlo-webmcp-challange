-- Rebrand system-owned default colors without overwriting custom values.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

ALTER TABLE `kycNoticeSettings`
  MODIFY `backgroundColor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '#e3efec' COMMENT 'Card background color',
  MODIFY `borderColor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '#174f46' COMMENT 'Card border color';

START TRANSACTION;

UPDATE `kycNoticeSettings`
SET `backgroundColor` = '#e3efec'
WHERE `backgroundColor` = '#eef2ff';

UPDATE `kycNoticeSettings`
SET `borderColor` = '#174f46'
WHERE `borderColor` = '#667eea';

UPDATE `adminUsers`
SET `avatarColor` = 'linear-gradient(135deg, #174f46 0%, #b98d3f 100%)'
WHERE `avatarColor` = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';

UPDATE `ibDocumentTemplates`
SET `iconGradient` = 'linear-gradient(135deg, #174f46 0%, #b98d3f 100%)'
WHERE `iconGradient` = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';

UPDATE `emailTemplates`
SET `emailBody` = REPLACE(
  REPLACE(
    REPLACE(
      REPLACE(`emailBody`, '#667eea', '#174f46'),
      '#764ba2', '#b98d3f'
    ),
    '#5568d3', '#103b35'
  ),
  '#eef2ff', '#e3efec'
)
WHERE `templateKey` IN ('ib_invitation', 'email_verification_code')
  AND (
    `emailBody` LIKE '%#667eea%'
    OR `emailBody` LIKE '%#764ba2%'
    OR `emailBody` LIKE '%#5568d3%'
    OR `emailBody` LIKE '%#eef2ff%'
  );

COMMIT;

-- Rollback (only reverses exact Atlantic defaults):
-- ALTER TABLE `kycNoticeSettings`
--   MODIFY `backgroundColor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '#eef2ff' COMMENT 'Card background color',
--   MODIFY `borderColor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '#667eea' COMMENT 'Card border color';
-- START TRANSACTION;
-- UPDATE `kycNoticeSettings` SET `backgroundColor` = '#eef2ff' WHERE `backgroundColor` = '#e3efec';
-- UPDATE `kycNoticeSettings` SET `borderColor` = '#667eea' WHERE `borderColor` = '#174f46';
-- UPDATE `adminUsers` SET `avatarColor` = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
--   WHERE `avatarColor` = 'linear-gradient(135deg, #174f46 0%, #b98d3f 100%)';
-- UPDATE `ibDocumentTemplates` SET `iconGradient` = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
--   WHERE `iconGradient` = 'linear-gradient(135deg, #174f46 0%, #b98d3f 100%)';
-- COMMIT;
