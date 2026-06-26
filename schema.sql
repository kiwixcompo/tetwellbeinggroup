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
    `is_champion` TINYINT(1) DEFAULT 0,
    `department_id` INT DEFAULT NULL,
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
    `client_feedback` TEXT DEFAULT NULL,
    `consultant_feedback` TEXT DEFAULT NULL,
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
    `is_pinned` TINYINT(1) DEFAULT 0,
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

-- 8. AI Chat Logs Table
CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `sender` ENUM('user', 'ai') NOT NULL,
    `message` TEXT NOT NULL,
    `language` VARCHAR(10) DEFAULT 'en',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'info',
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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

-- 9. Caregiver Resilience Plans Table
CREATE TABLE IF NOT EXISTS `caregiver_resilience_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `stressors` TEXT,
    `daily_buffers` TEXT,
    `coping_strategies` TEXT,
    `signs_of_burnout` TEXT,
    `backup_support` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Caregiver Daily Goals Table
CREATE TABLE IF NOT EXISTS `caregiver_daily_goals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `goal_text` VARCHAR(255) NOT NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `created_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. Caregiver Peer Connections Table
CREATE TABLE IF NOT EXISTS `caregiver_connections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `requester_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `status` VARCHAR(20) DEFAULT 'connected',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_connection` (`requester_id`, `receiver_id`)
) ENGINE=InnoDB;

-- 12. Caregiver Academy Progress Table
CREATE TABLE IF NOT EXISTS `caregiver_academy_progress` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `course_id` VARCHAR(50) NOT NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `score` INT DEFAULT NULL,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_user_course` (`user_id`, `course_id`)
) ENGINE=InnoDB;

-- Seed extra client users for peer matching
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `archetype`)
SELECT 101, 'Sarah Jenkins', 'sarah@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'client', 'dementia_carer'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'sarah@tetwellbeing.com');

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `archetype`)
SELECT 102, 'David Chen', 'david@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'client', 'stressed_student'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'david@tetwellbeing.com');

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `archetype`)
SELECT 103, 'John Obi', 'john@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'client', 'dementia_carer'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'john@tetwellbeing.com');

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `archetype`)
SELECT 104, 'Lisa Vance', 'lisa@tetwellbeing.com', '$2y$10$vK3zY0.5q4bS51o4U5b1x.1F9Vj5y7R.y.2z.2z.5t.7t.5t.3t.1t', 'client', 'general_wellbeing'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'lisa@tetwellbeing.com');

-- 13. User Telemetry Logs Table
CREATE TABLE IF NOT EXISTS `user_telemetry_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `log_date` DATE NOT NULL,
    `sleep_hours` DECIMAL(4,2) DEFAULT 0.00,
    `sleep_quality` INT DEFAULT 0, -- 0-100%
    `steps` INT DEFAULT 0,
    `active_minutes` INT DEFAULT 0,
    `hrv` INT DEFAULT 0, -- heart rate variability in ms
    `resting_hr` INT DEFAULT 0, -- resting heart rate in bpm
    `social_interaction` INT DEFAULT 5, -- 1-10 scale
    `voice_stress_score` INT DEFAULT NULL, -- 0-100%
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_user_date` (`user_id`, `log_date`)
) ENGINE=InnoDB;

-- 14. Digital Twin Profiles Table
CREATE TABLE IF NOT EXISTS `digital_twin_profiles` (
    `user_id` INT PRIMARY KEY,
    `learned_triggers` TEXT, -- JSON array of strings
    `coping_styles` TEXT, -- JSON array of strings
    `anxiety_resilience` INT DEFAULT 50, -- 0-100
    `depression_resistance` INT DEFAULT 50, -- 0-100
    `burnout_buffer` INT DEFAULT 50, -- 0-100
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default user_telemetry_logs (7 days) for Mark (user_id = 1)
-- Today is 2026-06-24. We seed from 2026-06-18 to 2026-06-24
INSERT INTO `user_telemetry_logs` (`user_id`, `log_date`, `sleep_hours`, `sleep_quality`, `steps`, `active_minutes`, `hrv`, `resting_hr`, `social_interaction`, `voice_stress_score`) VALUES
(1, '2026-06-18', 7.5, 82, 8500, 45, 55, 62, 7, 25),
(1, '2026-06-19', 6.8, 78, 6200, 30, 48, 65, 6, 32),
(1, '2026-06-20', 5.5, 60, 2800, 15, 38, 72, 4, 48),
(1, '2026-06-21', 5.0, 55, 1800, 10, 32, 78, 2, 60),
(1, '2026-06-22', 4.8, 52, 1200, 5,  28, 84, 1, 75),
(1, '2026-06-23', 5.2, 58, 2100, 12, 30, 82, 3, 65),
(1, '2026-06-24', 6.2, 70, 4100, 25, 45, 68, 5, 40)
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;

-- Seed default digital_twin_profile for Mark
INSERT INTO `digital_twin_profiles` (`user_id`, `learned_triggers`, `coping_styles`, `anxiety_resilience`, `depression_resistance`, `burnout_buffer`) VALUES
(1, '["Low sleep duration (<6 hours)","Sedentary routines (<3000 steps)","Elevated resting heart rate (>80 bpm)"]', '["Sensory Grounding (5-4-3-2-1)","Stretching Exercises","Teletherapy Checkins"]', 65, 58, 48)
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;

-- 15. VR Practice Logs Table
CREATE TABLE IF NOT EXISTS `vr_practice_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `simulation_id` VARCHAR(50) NOT NULL,
    `practice_date` DATE NOT NULL,
    `duration_seconds` INT NOT NULL,
    `mood_improvement` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default VR practice logs for Mark (user_id = 1)
INSERT INTO `vr_practice_logs` (`user_id`, `simulation_id`, `practice_date`, `duration_seconds`, `mood_improvement`) VALUES
(1, 'forest', '2026-06-22', 180, 2),
(1, 'auditorium', '2026-06-23', 300, 3),
(1, 'forest', '2026-06-24', 240, 1)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 16. Workplace Departments Table
CREATE TABLE IF NOT EXISTS `workplace_departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default departments
INSERT INTO `workplace_departments` (`id`, `name`) VALUES
(1, 'Nursing'),
(2, 'ICU & ER'),
(3, 'Social Work'),
(4, 'Caregiver Administration')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Update Mark (user_id = 1) to be in Nursing (department_id = 1)
UPDATE `users` SET `department_id` = 1 WHERE `id` = 1;

-- 17. Workplace Survey Responses Table (Anonymous responses)
CREATE TABLE IF NOT EXISTS `workplace_survey_responses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `q1_raise_issues` INT NOT NULL,
    `q2_team_mistakes` INT NOT NULL,
    `q3_supportive_env` INT NOT NULL,
    `q4_respect_others` INT NOT NULL,
    `q5_burnout_level` INT NOT NULL,
    `feedback` TEXT DEFAULT NULL,
    `submitted_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `workplace_departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed historical survey responses (Nursing = 1, ICU = 2, Social Work = 3, Admin = 4)
-- We will seed a distribution of scores from April, May, and June 2026
INSERT INTO `workplace_survey_responses` (`department_id`, `q1_raise_issues`, `q2_team_mistakes`, `q3_supportive_env`, `q4_respect_others`, `q5_burnout_level`, `feedback`, `submitted_date`) VALUES
(1, 4, 3, 5, 4, 2, 'Great teamwork, but shift handovers are sometimes chaotic.', '2026-04-15'),
(1, 5, 4, 4, 5, 1, 'Nursing staff is very supportive of mistakes.', '2026-04-18'),
(2, 2, 2, 3, 3, 4, 'High burnout in the ICU due to scheduling.', '2026-04-20'),
(2, 3, 2, 4, 3, 4, 'Friction between residents and senior staff.', '2026-04-22'),
(3, 4, 4, 4, 4, 3, 'Heavy caseloads make it hard to sync.', '2026-04-25'),
(1, 3, 3, 4, 4, 3, 'Feeling slight burnout from overtime.', '2026-05-12'),
(1, 4, 4, 5, 5, 2, 'Team is cohesive and respects boundaries.', '2026-05-15'),
(2, 2, 1, 2, 3, 5, 'Extremely short staffed. Need structural intervention.', '2026-05-18'),
(2, 3, 2, 3, 4, 4, 'Under immense pressure.', '2026-05-20'),
(3, 5, 4, 5, 5, 2, 'Great environment.', '2026-05-22'),
(4, 4, 5, 4, 4, 2, 'Administrative workload is high but manageable.', '2026-05-28'),
(1, 4, 4, 4, 4, 2, 'Decent support this month.', '2026-06-10'),
(1, 5, 4, 5, 5, 1, 'Love this team.', '2026-06-12'),
(2, 3, 2, 3, 3, 4, 'Friction is improving slowly but stress remains high.', '2026-06-15'),
(2, 1, 2, 2, 2, 5, 'ICU team is overwhelmed, high turnover.', '2026-06-18'),
(3, 4, 3, 4, 4, 3, 'Anonymity makes it easy to share concerns.', '2026-06-20'),
(4, 5, 4, 5, 4, 2, 'Management is responsive to issues.', '2026-06-22')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 18. Workplace User Survey Status Table (To prevent duplicate submissions)
CREATE TABLE IF NOT EXISTS `workplace_user_survey_status` (
    `user_id` INT NOT NULL,
    `survey_period` VARCHAR(7) NOT NULL, -- Format: YYYY-MM (e.g. '2026-06')
    PRIMARY KEY (`user_id`, `survey_period`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed that Mark has already completed his April and May surveys, but NOT his June 2026 survey
INSERT INTO `workplace_user_survey_status` (`user_id`, `survey_period`) VALUES
(1, '2026-04'),
(1, '2026-05')
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;

-- 19. Workplace Conflicts Table
CREATE TABLE IF NOT EXISTS `workplace_conflicts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `description` TEXT NOT NULL,
    `severity` ENUM('low', 'medium', 'high') NOT NULL,
    `status` ENUM('open', 'investigating', 'resolved') DEFAULT 'open',
    `ai_mitigation_plan` TEXT DEFAULT NULL,
    `logged_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `workplace_departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default conflicts
INSERT INTO `workplace_conflicts` (`id`, `department_id`, `description`, `severity`, `status`, `ai_mitigation_plan`, `logged_date`) VALUES
(1, 1, 'Communication friction during shift handovers causing stress spikes and transcription errors.', 'medium', 'resolved', 'Implement 10-minute structured SBAR (Situation, Background, Assessment, Recommendation) templates for cross-shift handovers. Conduct a brief team training.', '2026-06-10'),
(2, 2, 'Inter-disciplinary tension between junior residents and senior nursing supervisors regarding safety protocol overrides in ER.', 'high', 'investigating', 'Conduct a facilitated joint debriefing session led by the clinical specialist. Formally document nursing override authority pathways to clarify boundary rights.', '2026-06-18'),
(3, 3, 'Case distribution inequality friction leading to feelings of isolation and overload among social workers.', 'medium', 'open', 'Utilize the Peer Matching index to optimize case sharing. Redesign workload distribution templates to include active caregiver metrics.', '2026-06-23')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- 20. Streaming Categories Table
CREATE TABLE IF NOT EXISTS `streaming_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `icon_emoji` VARCHAR(20) NOT NULL,
    `description` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default categories
INSERT INTO `streaming_categories` (`id`, `name`, `icon_emoji`, `description`) VALUES
(1, 'Guided Meditation', '🧘', 'Centering practices to ground your focus and restore calm.'),
(2, 'Sleep Therapy', '💤', 'Deep sleep soundscapes, sleep stories, and ambient noise.'),
(3, 'Caregiver Wellbeing', '❤️', 'Coping skills and respite guides built specifically for active caregivers.'),
(4, 'Parenting de-escalation', '👶', 'Practical boundaries, child crisis management, and emotional support.'),
(5, 'Anxiety Management', '🌬️', 'Exposure scenarios, box breathing, and panic cycle interrupters.'),
(6, 'Trauma Recovery', '🩹', 'Gentle mindfulness sequences and recovery strategies.'),
(7, 'Grief Support', '🕊️', 'Coping tools for grieving process and loss adaptation.')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- 21. Streaming Content Table
CREATE TABLE IF NOT EXISTS `streaming_content` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `duration_seconds` INT NOT NULL,
    `plays_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `streaming_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default content library
INSERT INTO `streaming_content` (`id`, `category_id`, `title`, `description`, `duration_seconds`, `plays_count`) VALUES
(1, 1, '5-Minute Morning Grounding', 'A quick, restorative guided meditation to align your thoughts for the day.', 300, 142),
(2, 2, 'Deep Ocean Dreams sleepscape', 'Soft ocean swell recordings mixed with binaural sleep tones.', 1800, 480),
(3, 3, 'Navigating Compassion Fatigue', 'A therapeutic talk by Dr. Evelyn Carter on clinical boundary setting.', 600, 95),
(4, 4, 'Calming Toddler Tantrum de-escalation', 'De-escalation pathways to handle emotional storms with patience.', 420, 68),
(5, 5, 'Overcoming Sudden Panic Spikes', 'A rapid box breathing audio pacemaker to lower active heart rates.', 240, 215),
(6, 6, 'Somatic Release for Held Stress', 'Simple physical check-in prompts designed to identify bodily triggers.', 480, 54),
(7, 7, 'Grief: Honoring the Memory', 'A quiet guidance session on adapting to caregiver loss.', 900, 37),
(8, 2, 'Rainfall over Forest Sanctuary', 'Natural rain sounds layered with standing wind noise to mask thoughts.', 1200, 310)
ON DUPLICATE KEY UPDATE `title` = `title`;

-- 22. Research Studies Table
CREATE TABLE IF NOT EXISTS `research_studies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('active', 'completed', 'draft') DEFAULT 'active',
    `target_participants` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default studies
INSERT INTO `research_studies` (`id`, `title`, `description`, `status`, `target_participants`) VALUES
(1, 'VR Resilience Efficacy Trial', 'Clinical study analyzing the physiological and emotional effects of VR mindfulness simulations on active frontline care coordinators.', 'active', 50),
(2, 'Wearable Biometrics Stress Correlation', 'Anonymized analysis matching daily active minutes and sleep depth against weekly self-reported caregiver burnout scores.', 'active', 100),
(3, 'Shift Transition Fatigue Study', 'Evaluating cognitive load and stress levels before and after structured clinical handover procedures in critical care units.', 'active', 35)
ON DUPLICATE KEY UPDATE `title` = `title`;

-- 23. Research Participants Table
CREATE TABLE IF NOT EXISTS `research_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `study_id` INT NOT NULL,
    `consented_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_user_study` (`user_id`, `study_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`study_id`) REFERENCES `research_studies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default consent participation (Mark is participant in VR Resilience Trial)
INSERT INTO `research_participants` (`id`, `user_id`, `study_id`) VALUES
(1, 1, 1)
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;

-- Seed a default teletherapy booking for Mark (user_id = 1) with Dr. Evelyn Carter (therapist_id = 2) for tomorrow
INSERT INTO `teletherapy_bookings` (`id`, `user_id`, `therapist_name`, `therapist_id`, `booking_date`, `booking_time`, `insurance_provider`, `amount_paid`, `payment_status`, `release_date`) VALUES
(1, 1, 'Dr. Evelyn Carter, PhD', 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00 AM', 'Blue Shield', 120.00, 'escrow', DATE_ADD(NOW(), INTERVAL 8 DAY))
ON DUPLICATE KEY UPDATE `therapist_name` = `therapist_name`;

-- 24. Teletherapy Chat Logs Table
CREATE TABLE IF NOT EXISTS `teletherapy_chat_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `teletherapy_bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default chat messages between Mark and Dr. Carter
INSERT INTO `teletherapy_chat_logs` (`id`, `booking_id`, `sender_id`, `message`) VALUES
(1, 1, 1, 'Hi Dr. Carter, I am looking forward to our session tomorrow. I wanted to ask if we will cover boundary setting coping strategies?'),
(2, 1, 2, 'Hi Mark, yes absolutely! We will dedicate a portion of our session to boundary management techniques. Please review the 10-minute compassion fatigue guide in the hub if you have a moment.')
ON DUPLICATE KEY UPDATE `message` = `message`;


-- 25. Community Circles Table
CREATE TABLE IF NOT EXISTS `community_circles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `champion_name` VARCHAR(100) DEFAULT NULL,
    `champion_avatar` VARCHAR(10) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default circles
INSERT INTO `community_circles` (`id`, `slug`, `name`, `description`, `category`, `champion_name`, `champion_avatar`) VALUES
(1, 'general', 'General Wellbeing Circle', 'A welcoming space for general discussions about mindfulness, work-life balance, and self-care.', 'general', 'Marcus Vance, LCSW', 'M'),
(2, 'caregiver-respite', 'Respite & Carer Circle', 'For family and professional caregivers to share struggles, respite tips, and recovery strategies.', 'caregiver', 'Clara Green', 'C'),
(3, 'mindfulness', 'Meditation & Peace Circle', 'Practice positive grounding, share breathing scripts, and explore daily mindfulness exercises.', 'mindfulness', 'Marcus Vance, LCSW', 'M'),
(4, 'daily-wins', 'Positivity & Gratitude Log', 'Focus on the bright side. Post your daily micro-successes, small wins, and gratitude notes.', 'positivity', 'Clara Green', 'C'),
(5, 'dementia-support', 'Dementia Carers Support Circle', 'Specialized safe space for clinical and home caregivers supporting individuals with dementia.', 'caregiver', 'Marcus Vance, LCSW', 'M'),
(6, 'student-stress', 'Student Anxiety Resolution Circle', 'Peer support circle for nursing and clinical students coping with shift pressures and caseload stress.', 'student', 'Marcus Vance, LCSW', 'M')
ON DUPLICATE KEY UPDATE `slug` = `slug`;

-- 26. Community Circle Members Table (Seeded for Mark)
CREATE TABLE IF NOT EXISTS `community_circle_members` (
    `user_id` INT NOT NULL,
    `circle_id` INT NOT NULL,
    PRIMARY KEY (`user_id`, `circle_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`circle_id`) REFERENCES `community_circles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO `community_circle_members` (`user_id`, `circle_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4)
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;

-- 27. Community Replies Table
CREATE TABLE IF NOT EXISTS `community_replies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `author_name` VARCHAR(100) NOT NULL,
    `content` TEXT NOT NULL,
    `is_anonymous` TINYINT(1) DEFAULT 0,
    `is_champion` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`post_id`) REFERENCES `community_posts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed default comments/replies
INSERT INTO `community_replies` (`id`, `post_id`, `user_id`, `author_name`, `content`, `is_anonymous`, `is_champion`) VALUES
(1, 1, 3, 'Marcus Vance, LCSW', 'Welcome, Mark! So glad to have you. Remember that taking even 5 minutes of mindful silence can recharge your clinical batteries.', 0, 1),
(2, 2, 1, 'Mark', 'Thank you for sharing this. Seeing other caregivers prioritize respite helps ease my own guilt about taking a break.', 0, 0)
ON DUPLICATE KEY UPDATE `content` = `content`;


-- ============================================================
-- PHASE 10: CORPORATE WELLNESS, SUBSCRIPTIONS & COMMISSIONS
-- ============================================================
-- PHASE 12: SECURE EMAIL VERIFICATION SYSTEM
-- ============================================================
-- (Note: ALTER TABLE statements handled gracefully in init_db.php)

-- ============================================================

-- 28. Add subscription_plan + corporate_org_id columns to users (ignore errors if already exist)
-- (Note: ALTER TABLE statements handled gracefully in init_db.php)


-- 29. Subscription Plans (catalogue)
CREATE TABLE IF NOT EXISTS `subscription_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(30) UNIQUE NOT NULL,
    `name` VARCHAR(80) NOT NULL,
    `price_monthly` DECIMAL(10,2) DEFAULT 0.00,
    `features` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `subscription_plans` (`slug`, `name`, `price_monthly`, `features`) VALUES
('free',         'Free Starter',        0.00,  'AI Companion,Community Hub,1 Journal Entry/day,3 Streaming Tracks'),
('professional', 'Professional',        29.00, 'Everything in Free,Unlimited Journal Entries,Full Streaming Library,Teletherapy Booking,Predictive Health Alerts,Digital Twin Access,Priority Support'),
('corporate',    'Corporate Wellness',  199.00,'Everything in Professional,Corporate HR Portal,Bulk Session Credits (50),Staff Roster Management,Org Wellbeing Analytics,Dedicated Account Manager,White-label Reports')
ON DUPLICATE KEY UPDATE `price_monthly` = VALUES(`price_monthly`);

-- 30. Subscription Invoices
CREATE TABLE IF NOT EXISTS `subscription_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `plan_slug` VARCHAR(30) NOT NULL,
    `amount` DECIMAL(10,2) DEFAULT 0.00,
    `status` ENUM('pending','paid','cancelled') DEFAULT 'paid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `subscription_invoices` (`id`, `user_id`, `plan_slug`, `amount`, `status`) VALUES
(1, 1, 'professional', 29.00, 'paid')
ON DUPLICATE KEY UPDATE `status` = `status`;

-- 31. Corporate Organisations
CREATE TABLE IF NOT EXISTS `corporate_organisations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `contact_email` VARCHAR(150),
    `plan_credits` INT DEFAULT 50,
    `used_credits` INT DEFAULT 0,
    `hr_user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `corporate_organisations` (`id`, `name`, `contact_email`, `plan_credits`, `used_credits`, `hr_user_id`) VALUES
(1, 'NHS North Trust', 'hr@nhsnorthtrust.org', 50, 12, 99)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 32. Corporate Staff Roster
CREATE TABLE IF NOT EXISTS `corporate_staff` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `org_id` INT NOT NULL,
    `user_email` VARCHAR(150) NOT NULL,
    `user_id` INT DEFAULT NULL,
    `invited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('invited','active','removed') DEFAULT 'invited',
    UNIQUE KEY `uniq_org_email` (`org_id`,`user_email`)
) ENGINE=InnoDB;

INSERT INTO `corporate_staff` (`id`, `org_id`, `user_email`, `user_id`, `status`) VALUES
(1, 1, 'mark@tetwellbeing.com',   1,   'active'),
(2, 1, 'sarah@tetwellbeing.com',  101, 'active'),
(3, 1, 'james@nhsnorthtrust.org', NULL,'invited')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 33. Platform Commission Logs
CREATE TABLE IF NOT EXISTS `platform_commission_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `specialist_id` INT NOT NULL,
    `specialist_name` VARCHAR(150) NOT NULL,
    `gross_amount` DECIMAL(10,2) NOT NULL,
    `commission_rate` DECIMAL(5,2) DEFAULT 15.00,
    `commission_amount` DECIMAL(10,2) NOT NULL,
    `net_payout` DECIMAL(10,2) NOT NULL,
    `logged_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `platform_commission_logs` (`id`, `booking_id`, `specialist_id`, `specialist_name`, `gross_amount`, `commission_rate`, `commission_amount`, `net_payout`) VALUES
(1, 1, 2, 'Dr. Evelyn Carter, PhD', 120.00, 15.00, 18.00, 102.00)
ON DUPLICATE KEY UPDATE `gross_amount` = VALUES(`gross_amount`);






