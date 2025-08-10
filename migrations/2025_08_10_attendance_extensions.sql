-- Attendance API schema extensions

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `token` VARCHAR(64) PRIMARY KEY,
  `user_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `attendance_config` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `office_lat` DECIMAL(10,7) NOT NULL DEFAULT 0,
  `office_lng` DECIMAL(10,7) NOT NULL DEFAULT 0,
  `radius_meters` INT NOT NULL DEFAULT 50,
  `in_start` TIME NOT NULL DEFAULT '09:30:00',
  `in_end` TIME NOT NULL DEFAULT '09:45:00',
  `out_start` TIME NOT NULL DEFAULT '17:30:00',
  `out_end` TIME NOT NULL DEFAULT '17:45:00',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT IGNORE INTO `attendance_config` (`id`) VALUES (1);

ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `in_photo_base64` LONGTEXT NULL;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `out_photo_base64` LONGTEXT NULL;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `in_lat` DECIMAL(10,7) NULL;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `in_lng` DECIMAL(10,7) NULL;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `out_lat` DECIMAL(10,7) NULL;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `out_lng` DECIMAL(10,7) NULL;