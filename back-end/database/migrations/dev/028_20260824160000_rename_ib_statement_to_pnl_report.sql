SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

START TRANSACTION;

UPDATE `adminPermissions`
SET
    `permissionName` = 'IB P&L Report',
    `permissionDisplayName` = 'IB P&L Report',
    `permissionDisplayNameZh` = 'IB 盈亏报告',
    `updatedAt` = NOW()
WHERE `permissionKey` = 'page_ibstatement';

UPDATE `admin_dictionary_items`
SET
    `label_zh` = 'IB 盈亏报告',
    `label_en` = 'IB P&L Report',
    `updated_at` = UNIX_TIMESTAMP()
WHERE `dict_group` = 'operation_log'
  AND `dict_code` = 'operation_log_sub_module_log_report'
  AND `item_key` = 'ib_statement';

COMMIT;
