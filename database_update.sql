-- Database Update SQL File
-- This file contains the missing audit_logs table that needs to be added to the core database

-- Create audit_logs table for security monitoring and compliance
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT,
    `resource` VARCHAR(100),
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_action` (`user_id`, `action`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Insert some sample audit log entries for testing (optional)
INSERT INTO `audit_logs` (`user_id`, `action`, `details`, `resource`, `ip_address`, `user_agent`) VALUES
(1, 'login', 'User logged in successfully', 'auth', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(1, 'view_reports', 'Accessed reports page', 'reports', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
(1, 'create_employee', 'Created new employee record', 'employees', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

-- Database Update Script for OTP-based Password Reset System
-- Run this script to migrate from token-based to OTP-based password reset

-- Drop old password_resets table if it exists
DROP TABLE IF EXISTS password_resets;

-- Create new OTP-based password reset table
CREATE TABLE IF NOT EXISTS password_reset_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    blocked_until DATETIME NULL,
    block_level INT DEFAULT 0, -- 0: none, 1: 5min, 2: 1hour, 3: 1day
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes to audit_logs table for better performance
ALTER TABLE audit_logs ADD INDEX IF NOT EXISTS idx_user_ip_time (user_id, ip_address, created_at);
ALTER TABLE audit_logs ADD INDEX IF NOT EXISTS idx_action_time (action, created_at);

-- Update existing users table to ensure email field exists and is properly indexed
ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL;
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_email_active (email, is_active);

-- Add any missing permissions if needed
INSERT IGNORE INTO permissions (name, description) VALUES 
('manage_password_reset', 'Manage password reset settings'),
('view_audit_logs', 'View security audit logs');

-- Grant password reset permission to admin role (assuming role_id = 1 is admin)
INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions WHERE name = 'manage_password_reset';

-- Grant audit log permission to admin role
INSERT IGNORE INTO role_permissions (role_id, permission_id) 
SELECT 1, id FROM permissions WHERE name = 'view_audit_logs';