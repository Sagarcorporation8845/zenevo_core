<?php
require_once 'config/db.php';

// Execute the database updates
$sql_updates = "
-- Add Manager role
INSERT IGNORE INTO `roles` (`id`, `name`) VALUES (5, 'Manager');

-- Add new permissions for managers
INSERT IGNORE INTO `permissions` (`id`, `name`, `description`) VALUES
(10, 'manage_team', 'Manage assigned team members'),
(11, 'create_sprints', 'Create and manage sprints'),
(12, 'assign_tasks', 'Assign tasks to team members'),
(13, 'view_team_reports', 'View team productivity reports'),
(14, 'send_messages', 'Send messages to team members');

-- Assign permissions to Manager role
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5, 10), (5, 11), (5, 12), (5, 13), (5, 14);
";

// Execute basic setup
if ($conn->multi_query($sql_updates)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
}

// Create tables one by one
$tables = [
    "CREATE TABLE IF NOT EXISTS `employee_managers` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `employee_id` INT NOT NULL,
      `manager_id` INT NOT NULL,
      `assigned_by` INT NOT NULL,
      `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `is_active` TINYINT(1) DEFAULT 1,
      KEY `idx_employee` (`employee_id`),
      KEY `idx_manager` (`manager_id`),
      KEY `idx_active` (`is_active`),
      FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `message` TEXT NOT NULL,
      `type` ENUM('info', 'success', 'warning', 'error', 'broadcast') DEFAULT 'info',
      `is_read` TINYINT(1) DEFAULT 0,
      `is_shown` TINYINT(1) DEFAULT 0,
      `created_by` INT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY `idx_user_read` (`user_id`, `is_read`),
      KEY `idx_user_shown` (`user_id`, `is_shown`),
      KEY `idx_type` (`type`),
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS `notification_likes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `notification_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `liked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `unique_like` (`notification_id`, `user_id`),
      FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "CREATE TABLE IF NOT EXISTS `messages` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `from_user_id` INT NOT NULL,
      `to_user_id` INT NULL,
      `message` TEXT NOT NULL,
      `image_base64` LONGTEXT NULL,
      `is_support_mention` TINYINT(1) DEFAULT 0,
      `is_read` TINYINT(1) DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      KEY `idx_from_user` (`from_user_id`),
      KEY `idx_to_user` (`to_user_id`),
      KEY `idx_support` (`is_support_mention`),
      KEY `idx_read` (`is_read`),
      FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($tables as $sql) {
    $conn->query($sql);
}

// Add columns to existing tables
$alterations = [
    "ALTER TABLE `tasks` ADD COLUMN IF NOT EXISTS `deadline` DATE NULL AFTER `description`",
    "ALTER TABLE `tasks` ADD COLUMN IF NOT EXISTS `priority` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium' AFTER `deadline`"
];

foreach ($alterations as $sql) {
    $conn->query($sql);
}

echo "Resource Management database setup completed successfully!";
?>