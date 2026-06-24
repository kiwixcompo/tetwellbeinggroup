-- Tet Wellbeing Group Database Schema & Seed Data
-- Database: tetwellb_tetwellbeinggroup

CREATE DATABASE IF NOT EXISTS `tetwellb_tetwellbeinggroup` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tetwellb_tetwellbeinggroup`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) DEFAULT 'client',
    `archetype` VARCHAR(50) DEFAULT NULL,
    `license_no` VARCHAR(100) DEFAULT NULL,
    `bio` TEXT DEFAULT NULL,
    `hourly_rate` DECIMAL(10,2) DEFAULT NULL,
    `is_approved` TINYINT(1) DEFAULT 0,
    `escrow_balance` DECIMAL(10,2) DEFAULT 0.00,
    `clearance_balance` DECIMAL(10,2) DEFAULT 0.00,
    `is_suspended` TINYINT(1) DEFAULT 0,
    `crisis_state` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Daily Checkins Table
CREATE TABLE IF NOT EXISTS `daily_checkins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `mood` VARCHAR(50) NOT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Caregiver Burnout Logs Table
CREATE TABLE IF NOT EXISTS `caregiver_burnout_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `score` INT NOT NULL,
    `level` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Caregiver Respite Breaks Table
CREATE TABLE IF NOT EXISTS `caregiver_respite_breaks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `break_date` DATE NOT NULL,
    `duration_hours` INT NOT NULL,
    `cover_plan` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Teletherapy Bookings Table
CREATE TABLE IF NOT EXISTS `teletherapy_bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `therapist_name` VARCHAR(100) NOT NULL,
    `booking_date` DATE NOT NULL,
    `booking_time` VARCHAR(20) NOT NULL,
    `insurance_provider` VARCHAR(100) DEFAULT NULL,
    `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
    `payment_status` VARCHAR(20) DEFAULT 'escrow',
    `release_date` DATETIME DEFAULT NULL,
    `therapist_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 6. Community Posts Table
CREATE TABLE IF NOT EXISTS `community_posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `author_name` VARCHAR(100) NOT NULL,
    `channel` VARCHAR(50) NOT NULL,
    `content` TEXT NOT NULL,
    `is_anonymous` TINYINT(1) DEFAULT 0,
    `hearts` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 7. Therapist Availability Table
CREATE TABLE IF NOT EXISTS `therapist_availability` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `therapist_name` VARCHAR(100) NOT NULL,
    `day_of_week` VARCHAR(15) NOT NULL,
    `time_slot` VARCHAR(20) NOT NULL,
    UNIQUE KEY `uniq_therapist_day_slot` (`therapist_name`, `day_of_week`, `time_slot`)
) ENGINE=InnoDB;

-- =========================================================
-- SEED DATA
-- =========================================================

-- Insert Admin User (Password: Admin123!)
INSERT INTO `users` (`name`, `email`, `password`, `role`)
SELECT 'System Admin', 'admin@tetwellbeinggroup.com', '$2y$10$tZg/tO.7l0B2n5C1x1g5EuI1Vj4w8R6y.1x99z.9z.7t.5t.9t.5t', 'admin'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@tetwellbeinggroup.com');

-- Insert Demo User Mark (Password: password123)
INSERT INTO `users` (`name`, `email`, `password`, `role`)
SELECT 'Mark', 'mark@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'client'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'mark@tetwellbeing.com');

-- Insert Specialist Dr. Evelyn Carter, PhD (Password: password123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `license_no`, `bio`, `hourly_rate`, `is_approved`)
SELECT 'Dr. Evelyn Carter, PhD', 'evelyn@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'specialist', 'PSY-123456', 'Specializes in neuro-cognitive approaches and behavioral tools to interrupt panic cycles and ease chronic care pressures.', 120.00, 1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'evelyn@tetwellbeing.com');

-- Insert Specialist Marcus Vance, LCSW (Password: password123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `license_no`, `bio`, `hourly_rate`, `is_approved`)
SELECT 'Marcus Vance, LCSW', 'marcus@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'specialist', 'LCSW-654321', 'Dedicated family consultant providing respite counseling, boundary-setting workflows, and emotional tools for family caregivers.', 100.00, 1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'marcus@tetwellbeing.com');

-- Insert Specialist Clara Mendoza, LMFT (Password: password123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `license_no`, `bio`, `hourly_rate`, `is_approved`)
SELECT 'Clara Mendoza, LMFT', 'clara@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'specialist', 'LMFT-987654', 'Guiding individuals through workplace stress, role fatigue, and life transitions using mindful acceptance commitment frameworks.', 110.00, 1
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'clara@tetwellbeing.com');

-- Seed Availability
INSERT IGNORE INTO `therapist_availability` (`therapist_name`, `day_of_week`, `time_slot`) VALUES
('Dr. Evelyn Carter, PhD', 'Monday', '09:00 AM'),
('Dr. Evelyn Carter, PhD', 'Monday', '11:00 AM'),
('Dr. Evelyn Carter, PhD', 'Monday', '02:00 PM'),
('Dr. Evelyn Carter, PhD', 'Wednesday', '09:00 AM'),
('Dr. Evelyn Carter, PhD', 'Wednesday', '02:00 PM'),
('Dr. Evelyn Carter, PhD', 'Wednesday', '04:00 PM'),
('Marcus Vance, LCSW', 'Tuesday', '09:00 AM'),
('Marcus Vance, LCSW', 'Tuesday', '11:00 AM'),
('Marcus Vance, LCSW', 'Thursday', '02:00 PM'),
('Marcus Vance, LCSW', 'Thursday', '04:00 PM'),
('Clara Mendoza, LMFT', 'Monday', '11:00 AM'),
('Clara Mendoza, LMFT', 'Monday', '04:00 PM'),
('Clara Mendoza, LMFT', 'Thursday', '09:00 AM'),
('Clara Mendoza, LMFT', 'Thursday', '02:00 PM');

-- Seed Community Posts
INSERT INTO `community_posts` (`user_id`, `author_name`, `channel`, `content`, `is_anonymous`, `hearts`, `created_at`)
SELECT 1, 'Mark', 'general', 'Hello everyone! Excited to join this community. I am caring for my spouse, and finding this platform very helpful so far.', 0, 4, NOW() - INTERVAL 2 HOUR
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `community_posts` WHERE `content` LIKE 'Hello everyone! Excited%');

INSERT INTO `community_posts` (`user_id`, `author_name`, `channel`, `content`, `is_anonymous`, `hearts`, `created_at`)
SELECT 99, 'Anonymous Peer', 'caregiver-respite', 'It is tough to admit when you are struggling, but taking 15 minutes for myself today made a huge difference. Hang in there everyone.', 1, 12, NOW() - INTERVAL 5 HOUR
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `community_posts` WHERE `content` LIKE 'It is tough to admit%');

INSERT INTO `community_posts` (`user_id`, `author_name`, `channel`, `content`, `is_anonymous`, `hearts`, `created_at`)
SELECT 102, 'Sarah Jenkins', 'mindfulness', 'Highly recommend the 5-Minute Breathing guide in the Caregiver Hub. It is an instant calm down.', 0, 8, NOW() - INTERVAL 1 DAY
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `community_posts` WHERE `content` LIKE 'Highly recommend the 5-Minute%');
