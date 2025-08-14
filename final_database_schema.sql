-- Final Complete Database Schema for Core Platform
-- This file consolidates all database migrations and provides the complete schema
-- Run this file to set up the complete database from scratch

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Database: Core Platform
-- Character set: utf8mb4 for better security and emoji support

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `profile_picture` LONGTEXT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `idx_email_active` (`email`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `permissions`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `role_permissions`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `employees`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `attendance`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `clock_in_time` datetime DEFAULT NULL,
  `clock_out_time` datetime DEFAULT NULL,
  `date` date NOT NULL,
  `in_photo_base64` LONGTEXT NULL,
  `out_photo_base64` LONGTEXT NULL,
  `in_lat` DECIMAL(10,7) NULL,
  `in_lng` DECIMAL(10,7) NULL,
  `out_lat` DECIMAL(10,7) NULL,
  `out_lng` DECIMAL(10,7) NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `idx_date` (`date`),
  KEY `idx_employee_date` (`employee_id`, `date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `attendance_config`
-- --------------------------------------------------------

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `attendance_tickets`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `attendance_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `flag` ENUM('Late Entry','Half Day','Early Out','Miss Punch') NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` INT NULL,
  `reviewed_at` TIMESTAMP NULL,
  KEY `idx_employee_date` (`employee_id`, `attendance_date`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `leaves`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `leaves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `idx_dates` (`start_date`, `end_date`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `clients`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `projects`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `invoices`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('Draft','Sent','Paid','Overdue') DEFAULT 'Draft',
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `project_id` (`project_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `invoice_items`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `document_templates`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `document_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `audit_logs`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `resource` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`, `action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_ip_time` (`user_id`, `ip_address`, `created_at`),
  KEY `idx_action_time` (`action`, `created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `password_reset_otp`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `password_reset_otp` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `otp` VARCHAR(6) NOT NULL,
    `attempts` INT DEFAULT 0,
    `max_attempts` INT DEFAULT 3,
    `blocked_until` DATETIME NULL,
    `block_level` INT DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `used_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_email` (`email`),
    KEY `idx_user` (`user_id`),
    KEY `idx_expires` (`expires_at`),
    KEY `idx_blocked` (`blocked_until`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `auth_tokens` (for API access)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_tokens` (
  `token` VARCHAR(64) PRIMARY KEY,
  `user_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `mail_templates`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `mail_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `from_alias` VARCHAR(100) NOT NULL DEFAULT 'support',
  `html` MEDIUMTEXT NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `mail_jobs`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `mail_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `uploaded_filename` VARCHAR(255) NULL,
  `total_recipients` INT NOT NULL DEFAULT 0,
  `sent_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('Queued','Processing','Completed','Failed') DEFAULT 'Queued',
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  FOREIGN KEY (`template_id`) REFERENCES `mail_templates` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `mail_job_recipients`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `mail_job_recipients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `name` VARCHAR(150) NULL,
  `email` VARCHAR(180) NOT NULL,
  `status` ENUM('Pending','Sent','Failed') DEFAULT 'Pending',
  `error` VARCHAR(500) NULL,
  KEY `idx_job` (`job_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`job_id`) REFERENCES `mail_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sprints`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sprints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_dates` (`start_date`, `end_date`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `tasks`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sprint_id` INT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('Todo','In Progress','Blocked','Done') DEFAULT 'Todo',
  `assignee_employee_id` INT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sprint` (`sprint_id`),
  KEY `idx_assignee` (`assignee_employee_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`sprint_id`) REFERENCES `sprints` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assignee_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `finance_settings`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `finance_settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `reminder_days_before` VARCHAR(50) NOT NULL DEFAULT '7,3,1',
  `from_alias` VARCHAR(50) NOT NULL DEFAULT 'billing'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert default data
-- --------------------------------------------------------

-- Insert default roles
INSERT IGNORE INTO `roles` (`id`, `name`) VALUES
(1, 'Admin'),
(2, 'HR Manager'),
(3, 'Finance Manager'),
(4, 'Employee');

-- Insert default permissions
INSERT IGNORE INTO `permissions` (`id`, `name`, `description`) VALUES
(1, 'manage_users', 'Manage user accounts'),
(2, 'manage_employees', 'Manage employee records'),
(3, 'manage_leaves', 'Manage leave requests'),
(4, 'manage_invoices', 'Manage invoices and billing'),
(5, 'view_reports', 'View system reports'),
(6, 'manage_documents', 'Manage document templates'),
(7, 'manage_password_reset', 'Manage password reset settings'),
(8, 'view_audit_logs', 'View security audit logs'),
(9, 'manage_attendance', 'Manage attendance settings and records');

-- Insert default role permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9),
(2, 2), (2, 3), (2, 6), (2, 9),
(3, 4), (3, 5);

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `password`, `role_id`, `is_active`) VALUES
(1, 'System Administrator', 'admin@company.com', '$2y$10$GJRpUr4QGnmuGDehEhE32.VlpkvdZR1ufNMuclRrjDM0VLw95x3OC', 1, 1);

-- Insert default attendance config
INSERT IGNORE INTO `attendance_config` (`id`) VALUES (1);

-- Insert default finance settings
INSERT IGNORE INTO `finance_settings` (`id`) VALUES (1);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;