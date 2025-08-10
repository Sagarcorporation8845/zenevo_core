-- Mail templates for HR/Finance
CREATE TABLE IF NOT EXISTS `mail_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `from_alias` VARCHAR(100) NOT NULL DEFAULT 'support',
  `html` MEDIUMTEXT NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Bulk mail jobs log
CREATE TABLE IF NOT EXISTS `mail_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `uploaded_filename` VARCHAR(255) NULL,
  `total_recipients` INT NOT NULL DEFAULT 0,
  `sent_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('Queued','Processing','Completed','Failed') DEFAULT 'Queued',
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `mail_job_recipients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT NOT NULL,
  `name` VARCHAR(150) NULL,
  `email` VARCHAR(180) NOT NULL,
  `status` ENUM('Pending','Sent','Failed') DEFAULT 'Pending',
  `error` VARCHAR(500) NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Attendance tickets for yellow-flag days
CREATE TABLE IF NOT EXISTS `attendance_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `flag` ENUM('Late Entry','Half Day','Early Out','Miss Punch') NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` INT NULL,
  `reviewed_at` TIMESTAMP NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- DevOps/Collaboration
CREATE TABLE IF NOT EXISTS `sprints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sprint_id` INT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('Todo','In Progress','Blocked','Done') DEFAULT 'Todo',
  `assignee_employee_id` INT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Finance settings for reminders
CREATE TABLE IF NOT EXISTS `finance_settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `reminder_days_before` VARCHAR(50) NOT NULL DEFAULT '7,3,1',
  `from_alias` VARCHAR(50) NOT NULL DEFAULT 'billing'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT IGNORE INTO finance_settings (id) VALUES (1);