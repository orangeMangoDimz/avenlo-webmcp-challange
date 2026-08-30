-- ============================================
-- Account Verification Tables Migration
-- Description: Create tables for withdrawal account verification system
-- Date: 2024-01-15
-- ============================================

-- 1. Create account_verifications table
CREATE TABLE IF NOT EXISTS account_verifications (
  id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  client_id INT UNSIGNED NOT NULL COMMENT '客户ID',
  payment_method_id INT UNSIGNED NOT NULL COMMENT '支付方式ID',
  account_type ENUM('bank', 'crypto') NOT NULL COMMENT '账户类型：银行或加密货币',
  account_name VARCHAR(255) NOT NULL COMMENT '账户名称（客户自定义）',

  -- Bank specific fields
  bank_name VARCHAR(255) NULL COMMENT '银行名称',
  account_number VARCHAR(255) NULL COMMENT '银行账号（加密存储）',
  account_holder_name VARCHAR(255) NULL COMMENT '账户持有人姓名',
  swift_code VARCHAR(50) NULL COMMENT 'SWIFT/BIC代码',

  -- Crypto specific fields
  wallet_address VARCHAR(500) NULL COMMENT '钱包地址',
  wallet_network VARCHAR(50) NULL COMMENT '网络类型（如 ERC20, TRC20）',

  -- Verification status
  verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT '验证状态',

  -- Notes and review
  client_notes TEXT NULL COMMENT '客户备注',
  review_notes TEXT NULL COMMENT '审核备注',
  rejection_reason TEXT NULL COMMENT '拒绝原因',

  -- Review information
  reviewed_by BIGINT(20) UNSIGNED NULL COMMENT '审核人ID',
  reviewed_at TIMESTAMP NULL COMMENT '审核时间',

  -- Timestamps
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',

  -- Indexes
  INDEX idx_client_id (client_id),
  INDEX idx_client_status (client_id, verification_status),
  INDEX idx_status (verification_status),
  INDEX idx_status_submitted (verification_status, submitted_at DESC),
  INDEX idx_payment_method (payment_method_id),

  -- Foreign keys
  CONSTRAINT fk_account_verif_client FOREIGN KEY (client_id) REFERENCES clientUsers(id) ON DELETE CASCADE,
  CONSTRAINT fk_account_verif_payment FOREIGN KEY (payment_method_id) REFERENCES paymentMethods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账户验证申请表';

-- 2. Create account_verification_files table
CREATE TABLE IF NOT EXISTS account_verification_files (
  id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  verification_id BIGINT(20) UNSIGNED NOT NULL COMMENT '验证申请ID',
  file_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
  file_path VARCHAR(500) NOT NULL COMMENT '文件存储路径',
  file_type VARCHAR(100) NOT NULL COMMENT 'MIME类型',
  file_size INT NOT NULL COMMENT '文件大小（字节）',
  file_category ENUM('bank_statement', 'wallet_screenshot', 'other') DEFAULT 'other' COMMENT '文件类别',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',

  -- Index
  INDEX idx_verification (verification_id),

  -- Foreign key
  CONSTRAINT fk_verif_files_verification FOREIGN KEY (verification_id) REFERENCES account_verifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账户验证文件表';

-- 3. Add verification settings to transactionSecuritySettings table (reuse existing table)
-- Note: This uses the existing transactionSecuritySettings table from main database

-- 4. Insert default verification settings into transactionSecuritySettings
INSERT INTO transactionSecuritySettings (settingKey, settingValue, settingType, description) VALUES
('requireWithdrawalVerification', '0', 'boolean', 'Require clients to verify their withdrawal accounts before first withdrawal'),
('verificationMaxFileSize', '5', 'number', 'Maximum file size for verification documents in MB (1-20)'),
('autoRejectUnverified', '0', 'boolean', 'Automatically reject withdrawal requests from unverified accounts')
ON DUPLICATE KEY UPDATE
  settingKey = VALUES(settingKey);

-- 5. Create verification activity log table
CREATE TABLE IF NOT EXISTS account_verification_logs (
  id BIGINT(20) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  verification_id BIGINT(20) UNSIGNED NOT NULL COMMENT '验证申请ID',
  action_type ENUM('created', 'submitted', 'approved', 'rejected', 'updated', 'file_uploaded') NOT NULL COMMENT '操作类型',
  action_by BIGINT(20) UNSIGNED NULL COMMENT '操作人ID（客户或管理员）',
  action_details TEXT NULL COMMENT '操作详情（JSON）',
  ip_address VARCHAR(45) NULL COMMENT 'IP地址',
  user_agent TEXT NULL COMMENT '用户代理',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',

  INDEX idx_verification (verification_id),
  INDEX idx_action_type (action_type),
  INDEX idx_created_at (created_at DESC),

  CONSTRAINT fk_verif_logs_verification FOREIGN KEY (verification_id) REFERENCES account_verifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账户验证操作日志表';

-- 6. Update withdrawals table to link verified accounts (if column doesn't exist)
-- Add column to track which verified account was used for withdrawal
SET @dbname = DATABASE();
SET @tablename = 'withdrawals';
SET @columnname = 'verifiedAccountId';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE (table_name = @tablename) AND (table_schema = @dbname)
   AND (column_name = @columnname)) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " BIGINT(20) UNSIGNED NULL COMMENT '使用的已验证账户ID' AFTER destinationLabel,
  ADD INDEX idx_verified_account (", @columnname, "),
  ADD CONSTRAINT fk_withdrawals_verified_account FOREIGN KEY (", @columnname, ") REFERENCES account_verifications(id) ON DELETE SET NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 7. Create trigger to log verification status changes
DROP TRIGGER IF EXISTS after_verification_status_update;

DELIMITER $$

CREATE TRIGGER after_verification_status_update
AFTER UPDATE ON account_verifications
FOR EACH ROW
BEGIN
  IF OLD.verification_status != NEW.verification_status THEN
    INSERT INTO account_verification_logs (
      verification_id,
      action_type,
      action_by,
      action_details
    ) VALUES (
      NEW.id,
      CASE NEW.verification_status
        WHEN 'approved' THEN 'approved'
        WHEN 'rejected' THEN 'rejected'
        ELSE 'updated'
      END,
      NEW.reviewed_by,
      JSON_OBJECT(
        'old_status', OLD.verification_status,
        'new_status', NEW.verification_status,
        'review_notes', NEW.review_notes
      )
    );
  END IF;
END$$

DELIMITER ;

-- 8. Grant permissions (adjust as needed for your user)
-- GRANT SELECT, INSERT, UPDATE ON account_verifications TO 'your_app_user'@'localhost';
-- GRANT SELECT, INSERT ON account_verification_files TO 'your_app_user'@'localhost';
-- GRANT SELECT, INSERT ON account_verification_logs TO 'your_app_user'@'localhost';

-- ============================================
-- Migration Complete
-- ============================================
-- Tables Created:
-- 1. account_verifications - Main verification requests table
-- 2. account_verification_files - Uploaded document files
-- 3. account_verification_logs - Audit log for verification activities
--
-- Settings Added to transactionSecuritySettings:
-- - requireWithdrawalVerification
-- - verificationMaxFileSize
-- - autoRejectUnverified
--
-- Triggers Created:
-- - after_verification_status_update (logs status changes)
--
-- ============================================
-- Rollback Instructions (if needed)
-- ============================================
/*
-- To rollback this migration, execute the following in order:

-- 1. Drop trigger
DROP TRIGGER IF EXISTS after_verification_status_update;

-- 2. Remove foreign key and column from withdrawals
ALTER TABLE withdrawals DROP FOREIGN KEY IF EXISTS fk_withdrawals_verified_account;
ALTER TABLE withdrawals DROP COLUMN IF EXISTS verifiedAccountId;

-- 3. Drop tables
DROP TABLE IF EXISTS account_verification_logs;
DROP TABLE IF EXISTS account_verification_files;
DROP TABLE IF EXISTS account_verifications;

-- 4. Remove settings
DELETE FROM transactionSecuritySettings WHERE settingKey IN (
  'requireWithdrawalVerification',
  'verificationMaxFileSize',
  'autoRejectUnverified'
);
*/

-- ============================================
-- Verification Statistics Query (Run after data exists)
-- ============================================
/*
SELECT
  verification_status,
  COUNT(*) as count,
  MIN(submitted_at) as earliest_submission,
  MAX(submitted_at) as latest_submission
FROM account_verifications
GROUP BY verification_status;
*/
