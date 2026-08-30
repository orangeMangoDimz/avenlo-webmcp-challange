SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT INTO `admin_dictionary_items`
  (`dict_group`, `dict_code`, `item_key`, `label_key`, `label_zh`, `label_en`, `sort_order`, `is_active`, `created_at`, `updated_at`)
VALUES
  (
    'operation_log',
    'operation_log_sub_module_log_report',
    'ib_statement',
    'operation_log_sm_log_report_ib_statement',
    'IB 盈亏报告',
    'IB P&L Report',
    50,
    1,
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
  )
ON DUPLICATE KEY UPDATE
  `label_key` = VALUES(`label_key`),
  `label_zh` = VALUES(`label_zh`),
  `label_en` = VALUES(`label_en`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`),
  `updated_at` = UNIX_TIMESTAMP();

COMMIT;
