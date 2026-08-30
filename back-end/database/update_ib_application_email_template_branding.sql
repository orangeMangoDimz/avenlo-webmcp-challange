-- ============================================================
-- 更新 IB Application 邮件模板，将 "Utrada CRM Team" 改为使用品牌变量
-- ============================================================
-- 将邮件模板中的硬编码品牌名称替换为 {{teamName}} 变量

UPDATE `emailTemplates`
SET
  `emailBody` = REPLACE(
    `emailBody`,
    'Utrada CRM Team',
    '{{teamName}}'
  ),
  `updatedAt` = NOW()
WHERE `templateKey` = 'ib_application_approved'
  AND `emailBody` LIKE '%Utrada CRM Team%';

-- 如果模板中还有其他可能的品牌名称变体，也可以一并更新
UPDATE `emailTemplates`
SET
  `emailBody` = REPLACE(
    REPLACE(
      REPLACE(
        `emailBody`,
        'Utrada CRM Team',
        '{{teamName}}'
      ),
      'Utrada Team',
      '{{teamName}}'
    ),
    'CRM Team',
    '{{teamName}}'
  ),
  `updatedAt` = NOW()
WHERE `templateKey` = 'ib_application_approved'
  AND (
    `emailBody` LIKE '%Utrada CRM Team%'
    OR `emailBody` LIKE '%Utrada Team%'
    OR `emailBody` LIKE '%CRM Team%'
  );
