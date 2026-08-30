-- Countries table
-- 2025-11-12
CREATE TABLE IF NOT EXISTS `countries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(10) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `isActive` TINYINT(1) NOT NULL DEFAULT 1,
    `displayOrder` INT NOT NULL DEFAULT 100,
    `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` DATETIME NULL DEFAULT NULL,
    INDEX (`isActive`),
    INDEX (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `countries` (`code`, `name`, `isActive`, `displayOrder`) VALUES
('ALL', 'All Countries', 1, 0),
('US', 'United States', 1, 10),
('UK', 'United Kingdom', 1, 20),
('CA', 'Canada', 1, 30),
('AU', 'Australia', 1, 40),
('DE', 'Germany', 1, 50),
('FR', 'France', 1, 60),
('SG', 'Singapore', 1, 70),
('HK', 'Hong Kong', 1, 80),
('JP', 'Japan', 1, 90),
('IT', 'Italy', 1, 100),
('ES', 'Spain', 1, 110),
('CN', 'China', 1, 120),
('IN', 'India', 1, 130),
('BR', 'Brazil', 1, 140),
('MX', 'Mexico', 1, 150)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `isActive` = VALUES(`isActive`),
    `displayOrder` = VALUES(`displayOrder`),
    `updatedAt` = CURRENT_TIMESTAMP;
