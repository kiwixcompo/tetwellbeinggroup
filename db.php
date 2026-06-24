<?php
/**
 * Tet Wellbeing Group - Database & Session Helper
 * Auto-initializes the MySQL database and schema, with robust session-based mock fallback.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'EmotionalHealthService.php';

$db_host = 'localhost';
$db_user = 'tetwellb_tetwellbeinggroup';
$db_pass = ',!PX7{%zy1-vgh.7';
$db_name = 'tetwellb_tetwellbeinggroup';

$pdo = null;
$db_connected = false;
$db_error = '';

// 1. ATTEMPT AUTO-INITIALIZATION OF MYSQL DATABASE AND SCHEMA
try {
    // Connect to MySQL server (without specifying DB, as it might not exist yet)
    $temp_pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Create database if not exists
    $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $db_connected = true;

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Add Phase 2 columns to users table dynamically if not exists
    $cols_to_add = [
        "role VARCHAR(20) DEFAULT 'client'",
        "archetype VARCHAR(50) DEFAULT NULL",
        "license_no VARCHAR(100) DEFAULT NULL",
        "bio TEXT DEFAULT NULL",
        "hourly_rate DECIMAL(10,2) DEFAULT NULL",
        "is_approved TINYINT(1) DEFAULT 0",
        "escrow_balance DECIMAL(10,2) DEFAULT 0.00",
        "clearance_balance DECIMAL(10,2) DEFAULT 0.00",
        "is_suspended TINYINT(1) DEFAULT 0",
        "crisis_state TINYINT(1) DEFAULT 0"
    ];
    foreach ($cols_to_add as $col) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col");
        } catch (PDOException $ex) {}
    }

    // Create daily checkins table
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_checkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mood VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create caregiver burnout logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS caregiver_burnout_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        score INT NOT NULL,
        level VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create caregiver respite breaks table
    $pdo->exec("CREATE TABLE IF NOT EXISTS caregiver_respite_breaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        break_date DATE NOT NULL,
        duration_hours INT NOT NULL,
        cover_plan TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create teletherapy bookings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS teletherapy_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        therapist_name VARCHAR(100) NOT NULL,
        booking_date DATE NOT NULL,
        booking_time VARCHAR(20) NOT NULL,
        insurance_provider VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Add Phase 2 columns to teletherapy_bookings table dynamically
    $booking_cols_to_add = [
        "amount_paid DECIMAL(10,2) DEFAULT 0.00",
        "payment_status VARCHAR(20) DEFAULT 'escrow'",
        "release_date DATETIME DEFAULT NULL",
        "therapist_id INT DEFAULT NULL"
    ];
    foreach ($booking_cols_to_add as $col) {
        try {
            $pdo->exec("ALTER TABLE teletherapy_bookings ADD COLUMN $col");
        } catch (PDOException $ex) {}
    }

    // Create community posts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        author_name VARCHAR(100) NOT NULL,
        channel VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        is_anonymous TINYINT(1) DEFAULT 0,
        hearts INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create therapist availability table
    $pdo->exec("CREATE TABLE IF NOT EXISTS therapist_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        therapist_name VARCHAR(100) NOT NULL,
        day_of_week VARCHAR(15) NOT NULL,
        time_slot VARCHAR(20) NOT NULL,
        UNIQUE KEY uniq_therapist_day_slot (therapist_name, day_of_week, time_slot)
    ) ENGINE=InnoDB");

    // Create AI Chat Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_chat_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sender ENUM('user', 'ai') NOT NULL,
        message TEXT NOT NULL,
        language VARCHAR(10) DEFAULT 'en',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create Caregiver Resilience Plans table
    $pdo->exec("CREATE TABLE IF NOT EXISTS caregiver_resilience_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        stressors TEXT,
        daily_buffers TEXT,
        coping_strategies TEXT,
        signs_of_burnout TEXT,
        backup_support TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create Caregiver Daily Goals table
    $pdo->exec("CREATE TABLE IF NOT EXISTS caregiver_daily_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        goal_text VARCHAR(255) NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        created_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create Caregiver Connections table
    $pdo->exec("CREATE TABLE IF NOT EXISTS caregiver_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requester_id INT NOT NULL,
        receiver_id INT NOT NULL,
        status VARCHAR(20) DEFAULT 'connected',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_connection (requester_id, receiver_id)
    ) ENGINE=InnoDB");

    // Create Caregiver Academy Progress table
    $pdo->exec("CREATE TABLE IF NOT EXISTS caregiver_academy_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id VARCHAR(50) NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        score INT DEFAULT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_course (user_id, course_id)
    ) ENGINE=InnoDB");

    // Create User Telemetry Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_telemetry_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        log_date DATE NOT NULL,
        sleep_hours DECIMAL(4,2) DEFAULT 0.00,
        sleep_quality INT DEFAULT 0,
        steps INT DEFAULT 0,
        active_minutes INT DEFAULT 0,
        hrv INT DEFAULT 0,
        resting_hr INT DEFAULT 0,
        social_interaction INT DEFAULT 5,
        voice_stress_score INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_date (user_id, log_date)
    ) ENGINE=InnoDB");

    // Create Digital Twin Profiles table
    $pdo->exec("CREATE TABLE IF NOT EXISTS digital_twin_profiles (
        user_id INT PRIMARY KEY,
        learned_triggers TEXT,
        coping_styles TEXT,
        anxiety_resilience INT DEFAULT 50,
        depression_resistance INT DEFAULT 50,
        burnout_buffer INT DEFAULT 50,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create VR Practice Logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS vr_practice_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        simulation_id VARCHAR(50) NOT NULL,
        practice_date DATE NOT NULL,
        duration_seconds INT NOT NULL,
        mood_improvement INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Alter users table to add department_id if missing
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN department_id INT DEFAULT NULL");
    } catch (PDOException $e) {
        // column already exists
    }

    // Create workplace safety tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS workplace_departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workplace_survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        q1_raise_issues INT NOT NULL,
        q2_team_mistakes INT NOT NULL,
        q3_supportive_env INT NOT NULL,
        q4_respect_others INT NOT NULL,
        q5_burnout_level INT NOT NULL,
        feedback TEXT DEFAULT NULL,
        submitted_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workplace_user_survey_status (
        user_id INT NOT NULL,
        survey_period VARCHAR(7) NOT NULL,
        PRIMARY KEY (user_id, survey_period)
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS workplace_conflicts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT NOT NULL,
        description TEXT NOT NULL,
        severity ENUM('low', 'medium', 'high') NOT NULL,
        status ENUM('open', 'investigating', 'resolved') DEFAULT 'open',
        ai_mitigation_plan TEXT DEFAULT NULL,
        logged_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Seed departments & update Mark if empty
    $dept_count = $pdo->query("SELECT COUNT(*) FROM workplace_departments")->fetchColumn();
    if ($dept_count == 0) {
        $pdo->exec("INSERT INTO workplace_departments (id, name) VALUES 
            (1, 'Nursing'),
            (2, 'ICU & ER'),
            (3, 'Social Work'),
            (4, 'Caregiver Administration')");
        $pdo->exec("UPDATE users SET department_id = 1 WHERE id = 1");
    }

    // Seed survey responses if empty
    $resp_count = $pdo->query("SELECT COUNT(*) FROM workplace_survey_responses")->fetchColumn();
    if ($resp_count == 0) {
        $pdo->exec("INSERT INTO workplace_survey_responses (department_id, q1_raise_issues, q2_team_mistakes, q3_supportive_env, q4_respect_others, q5_burnout_level, feedback, submitted_date) VALUES
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
            (4, 5, 4, 5, 4, 2, 'Management is responsive to issues.', '2026-06-22')");
    }

    // Seed survey status if empty
    $status_count = $pdo->query("SELECT COUNT(*) FROM workplace_user_survey_status")->fetchColumn();
    if ($status_count == 0) {
        $pdo->exec("INSERT INTO workplace_user_survey_status (user_id, survey_period) VALUES
            (1, '2026-04'),
            (1, '2026-05')");
    }

    // Seed conflicts if empty
    $conflict_count = $pdo->query("SELECT COUNT(*) FROM workplace_conflicts")->fetchColumn();
    if ($conflict_count == 0) {
        $pdo->exec("INSERT INTO workplace_conflicts (id, department_id, description, severity, status, ai_mitigation_plan, logged_date) VALUES
            (1, 1, 'Communication friction during shift handovers causing stress spikes and transcription errors.', 'medium', 'resolved', 'Implement 10-minute structured SBAR templates for cross-shift handovers. Conduct a brief team training.', '2026-06-10'),
            (2, 2, 'Inter-disciplinary tension between junior residents and senior nursing supervisors regarding safety protocol overrides in ER.', 'high', 'investigating', 'Conduct a facilitated joint debriefing session led by the clinical specialist. Formally document nursing override authority pathways to clarify boundary rights.', '2026-06-18'),
            (3, 3, 'Case distribution inequality friction leading to feelings of isolation and overload among social workers.', 'medium', 'open', 'Utilize the Peer Matching index to optimize case sharing. Redesign workload distribution templates to include active caregiver metrics.', '2026-06-23')");
    }

    // Seed default availability if empty
    $avail_count = $pdo->query("SELECT COUNT(*) FROM therapist_availability")->fetchColumn();
    if ($avail_count == 0) {
        $insert_avail = $pdo->prepare("INSERT INTO therapist_availability (therapist_name, day_of_week, time_slot) VALUES (?, ?, ?)");
        
        // Dr. Evelyn Carter, PhD
        $insert_avail->execute(['Dr. Evelyn Carter, PhD', 'Monday', '09:00 AM']);
        $insert_avail->execute(['Dr. Evelyn Carter, PhD', 'Monday', '11:00 AM']);
        $insert_avail->execute(['Dr. Evelyn Carter, PhD', 'Monday', '02:00 PM']);
        $insert_avail->execute(['Dr. Evelyn Carter, PhD', 'Wednesday', '09:00 AM']);
        $insert_avail->execute(['Dr. Evelyn Carter, PhD', 'Wednesday', '02:00 PM']);
        $insert_avail->execute(['Dr. Evelyn Carter, PhD', 'Wednesday', '04:00 PM']);

        // Marcus Vance, LCSW
        $insert_avail->execute(['Marcus Vance, LCSW', 'Tuesday', '09:00 AM']);
        $insert_avail->execute(['Marcus Vance, LCSW', 'Tuesday', '11:00 AM']);
        $insert_avail->execute(['Marcus Vance, LCSW', 'Thursday', '02:00 PM']);
        $insert_avail->execute(['Marcus Vance, LCSW', 'Thursday', '04:00 PM']);

        // Clara Mendoza, LMFT
        $insert_avail->execute(['Clara Mendoza, LMFT', 'Monday', '11:00 AM']);
        $insert_avail->execute(['Clara Mendoza, LMFT', 'Monday', '04:00 PM']);
        $insert_avail->execute(['Clara Mendoza, LMFT', 'Thursday', '09:00 AM']);
        $insert_avail->execute(['Clara Mendoza, LMFT', 'Thursday', '02:00 PM']);
    }

    // Insert admin user if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@tetwellbeinggroup.com']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('Admin123!', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $insert->execute(['System Admin', 'admin@tetwellbeinggroup.com', $hashed_password]);
    }

    // Insert demo user Mark if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['mark@tetwellbeing.com']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'client')");
        $insert->execute(['Mark', 'mark@tetwellbeing.com', $hashed_password]);
    }

    // Seed default approved specialists
    $specialists_data = [
        [
            'name' => 'Dr. Evelyn Carter, PhD',
            'email' => 'evelyn@tetwellbeing.com',
            'license' => 'PSY-123456',
            'bio' => 'Specializes in neuro-cognitive approaches and behavioral tools to interrupt panic cycles and ease chronic care pressures.',
            'rate' => 120.00
        ],
        [
            'name' => 'Marcus Vance, LCSW',
            'email' => 'marcus@tetwellbeing.com',
            'license' => 'LCSW-654321',
            'bio' => 'Dedicated family consultant providing respite counseling, boundary-setting workflows, and emotional tools for family caregivers.',
            'rate' => 100.00
        ],
        [
            'name' => 'Clara Mendoza, LMFT',
            'email' => 'clara@tetwellbeing.com',
            'license' => 'LMFT-987654',
            'bio' => 'Guiding individuals through workplace stress, role fatigue, and life transitions using mindful acceptance commitment frameworks.',
            'rate' => 110.00
        ]
    ];
    foreach ($specialists_data as $spec) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$spec['email']]);
        if ($stmt->fetchColumn() == 0) {
            $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, license_no, bio, hourly_rate, is_approved) VALUES (?, ?, ?, 'specialist', ?, ?, ?, 1)");
            $insert->execute([$spec['name'], $spec['email'], $hashed_password, $spec['license'], $spec['bio'], $spec['rate']]);
        }
    }

    // Seed dummy posts if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM community_posts")->fetchColumn();
    if ($count == 0) {
        $seed = $pdo->prepare("INSERT INTO community_posts (user_id, author_name, channel, content, is_anonymous, hearts, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $seed->execute([1, 'Mark', 'general', 'Hello everyone! Excited to join this community. I am caring for my spouse, and finding this platform very helpful so far.', 0, 4, date('Y-m-d H:i:s', strtotime('-2 hours'))]);
        $seed->execute([99, 'Anonymous Peer', 'caregiver-respite', 'It is tough to admit when you are struggling, but taking 15 minutes for myself today made a huge difference. Hang in there everyone.', 1, 12, date('Y-m-d H:i:s', strtotime('-5 hours'))]);
        $seed->execute([102, 'Sarah Jenkins', 'mindfulness', 'Highly recommend the 5-Minute Breathing guide in the Caregiver Hub. It is an instant calm down.', 0, 8, date('Y-m-d H:i:s', strtotime('-1 day'))]);
    }

} catch (PDOException $e) {
    $db_connected = false;
    $db_error = "Database offline / skipped: " . $e->getMessage();
}

// 2. INITIALIZE SESSION-BASED MOCK STORAGE (Fallback fallback mechanism)
if (!isset($_SESSION['mock_users'])) {
    $_SESSION['mock_users'] = [
        'admin@tetwellbeinggroup.com' => [
            'id' => 99,
            'name' => 'System Admin',
            'email' => 'admin@tetwellbeinggroup.com',
            'password' => password_hash('Admin123!', PASSWORD_DEFAULT),
            'role' => 'admin',
            'is_suspended' => 0,
            'archetype' => NULL
        ],
        'mark@tetwellbeing.com' => [
            'id' => 1,
            'name' => 'Mark',
            'email' => 'mark@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'client',
            'is_suspended' => 0,
            'archetype' => NULL,
            'department_id' => 1
        ],
        'evelyn@tetwellbeing.com' => [
            'id' => 2,
            'name' => 'Dr. Evelyn Carter, PhD',
            'email' => 'evelyn@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'specialist',
            'license_no' => 'PSY-123456',
            'bio' => 'Specializes in neuro-cognitive approaches and behavioral tools to interrupt panic cycles and ease chronic care pressures.',
            'hourly_rate' => 120.00,
            'is_approved' => 1,
            'is_suspended' => 0,
            'escrow_balance' => 0.00,
            'clearance_balance' => 0.00,
            'archetype' => NULL
        ],
        'marcus@tetwellbeing.com' => [
            'id' => 3,
            'name' => 'Marcus Vance, LCSW',
            'email' => 'marcus@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'specialist',
            'license_no' => 'LCSW-654321',
            'bio' => 'Dedicated family consultant providing respite counseling, boundary-setting workflows, and emotional tools for family caregivers.',
            'hourly_rate' => 100.00,
            'is_approved' => 1,
            'is_suspended' => 0,
            'escrow_balance' => 0.00,
            'clearance_balance' => 0.00,
            'archetype' => NULL
        ],
        'clara@tetwellbeing.com' => [
            'id' => 4,
            'name' => 'Clara Mendoza, LMFT',
            'email' => 'clara@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'specialist',
            'license_no' => 'LMFT-987654',
            'bio' => 'Guiding individuals through workplace stress, role fatigue, and life transitions using mindful acceptance commitment frameworks.',
            'hourly_rate' => 110.00,
            'is_approved' => 1,
            'is_suspended' => 0,
            'escrow_balance' => 0.00,
            'clearance_balance' => 0.00,
            'archetype' => NULL
        ],
        'sarah@tetwellbeing.com' => [
            'id' => 101,
            'name' => 'Sarah Jenkins',
            'email' => 'sarah@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'client',
            'is_suspended' => 0,
            'archetype' => 'dementia_carer'
        ],
        'david@tetwellbeing.com' => [
            'id' => 102,
            'name' => 'David Chen',
            'email' => 'david@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'client',
            'is_suspended' => 0,
            'archetype' => 'stressed_student'
        ],
        'john@tetwellbeing.com' => [
            'id' => 103,
            'name' => 'John Obi',
            'email' => 'john@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'client',
            'is_suspended' => 0,
            'archetype' => 'dementia_carer'
        ],
        'lisa@tetwellbeing.com' => [
            'id' => 104,
            'name' => 'Lisa Vance',
            'email' => 'lisa@tetwellbeing.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'client',
            'is_suspended' => 0,
            'archetype' => 'general_wellbeing'
        ]
    ];
}


if (!isset($_SESSION['mock_checkins'])) {
    $_SESSION['mock_checkins'] = [];
}

if (!isset($_SESSION['mock_burnout_logs'])) {
    $_SESSION['mock_burnout_logs'] = [];
}

if (!isset($_SESSION['mock_respite_breaks'])) {
    $_SESSION['mock_respite_breaks'] = [];
}

if (!isset($_SESSION['mock_community_posts'])) {
    $_SESSION['mock_community_posts'] = [
        [
            'id' => 1,
            'user_id' => 1,
            'author_name' => 'Mark',
            'channel' => 'general',
            'content' => 'Hello everyone! Excited to join this community. I am caring for my spouse, and finding this platform very helpful so far.',
            'is_anonymous' => 0,
            'hearts' => 4,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'id' => 2,
            'user_id' => 99,
            'author_name' => 'Anonymous Peer',
            'channel' => 'caregiver-respite',
            'content' => 'It is tough to admit when you are struggling, but taking 15 minutes for myself today made a huge difference. Hang in there everyone.',
            'is_anonymous' => 1,
            'hearts' => 12,
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
        ],
        [
            'id' => 3,
            'user_id' => 102,
            'author_name' => 'Sarah Jenkins',
            'channel' => 'mindfulness',
            'content' => 'Highly recommend the 5-Minute Breathing guide in the Caregiver Hub. It is an instant calm down.',
            'is_anonymous' => 0,
            'hearts' => 8,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]
    ];
}
if (!isset($_SESSION['mock_bookings'])) {
    $_SESSION['mock_bookings'] = [];
}

if (!isset($_SESSION['mock_ai_chats'])) {
    $_SESSION['mock_ai_chats'] = [];
}

if (!isset($_SESSION['mock_availability'])) {
    $_SESSION['mock_availability'] = [
        ['therapist_name' => 'Dr. Evelyn Carter, PhD', 'day_of_week' => 'Monday', 'time_slot' => '09:00 AM'],
        ['therapist_name' => 'Dr. Evelyn Carter, PhD', 'day_of_week' => 'Monday', 'time_slot' => '11:00 AM'],
        ['therapist_name' => 'Dr. Evelyn Carter, PhD', 'day_of_week' => 'Monday', 'time_slot' => '02:00 PM'],
        ['therapist_name' => 'Dr. Evelyn Carter, PhD', 'day_of_week' => 'Wednesday', 'time_slot' => '09:00 AM'],
        ['therapist_name' => 'Dr. Evelyn Carter, PhD', 'day_of_week' => 'Wednesday', 'time_slot' => '02:00 PM'],
        ['therapist_name' => 'Dr. Evelyn Carter, PhD', 'day_of_week' => 'Wednesday', 'time_slot' => '04:00 PM'],

        ['therapist_name' => 'Marcus Vance, LCSW', 'day_of_week' => 'Tuesday', 'time_slot' => '09:00 AM'],
        ['therapist_name' => 'Marcus Vance, LCSW', 'day_of_week' => 'Tuesday', 'time_slot' => '11:00 AM'],
        ['therapist_name' => 'Marcus Vance, LCSW', 'day_of_week' => 'Thursday', 'time_slot' => '02:00 PM'],
        ['therapist_name' => 'Marcus Vance, LCSW', 'day_of_week' => 'Thursday', 'time_slot' => '04:00 PM'],

        ['therapist_name' => 'Clara Mendoza, LMFT', 'day_of_week' => 'Monday', 'time_slot' => '11:00 AM'],
        ['therapist_name' => 'Clara Mendoza, LMFT', 'day_of_week' => 'Monday', 'time_slot' => '04:00 PM'],
        ['therapist_name' => 'Clara Mendoza, LMFT', 'day_of_week' => 'Thursday', 'time_slot' => '09:00 AM'],
        ['therapist_name' => 'Clara Mendoza, LMFT', 'day_of_week' => 'Thursday', 'time_slot' => '02:00 PM']
    ];
}

if (!isset($_SESSION['mock_resilience_plans'])) {
    $_SESSION['mock_resilience_plans'] = [];
}
if (!isset($_SESSION['mock_daily_goals'])) {
    $_SESSION['mock_daily_goals'] = [];
}
if (!isset($_SESSION['mock_connections'])) {
    $_SESSION['mock_connections'] = [];
}
if (!isset($_SESSION['mock_academy_progress'])) {
    $_SESSION['mock_academy_progress'] = [];
}
if (!isset($_SESSION['mock_telemetry_logs'])) {
    $_SESSION['mock_telemetry_logs'] = [
        ['user_id' => 1, 'log_date' => '2026-06-18', 'sleep_hours' => 7.5, 'sleep_quality' => 82, 'steps' => 8500, 'active_minutes' => 45, 'hrv' => 55, 'resting_hr' => 62, 'social_interaction' => 7, 'voice_stress_score' => 25, 'created_at' => '2026-06-18 12:00:00'],
        ['user_id' => 1, 'log_date' => '2026-06-19', 'sleep_hours' => 6.8, 'sleep_quality' => 78, 'steps' => 6200, 'active_minutes' => 30, 'hrv' => 48, 'resting_hr' => 65, 'social_interaction' => 6, 'voice_stress_score' => 32, 'created_at' => '2026-06-19 12:00:00'],
        ['user_id' => 1, 'log_date' => '2026-06-20', 'sleep_hours' => 5.5, 'sleep_quality' => 60, 'steps' => 2800, 'active_minutes' => 15, 'hrv' => 38, 'resting_hr' => 72, 'social_interaction' => 4, 'voice_stress_score' => 48, 'created_at' => '2026-06-20 12:00:00'],
        ['user_id' => 1, 'log_date' => '2026-06-21', 'sleep_hours' => 5.0, 'sleep_quality' => 55, 'steps' => 1800, 'active_minutes' => 10, 'hrv' => 32, 'resting_hr' => 78, 'social_interaction' => 2, 'voice_stress_score' => 60, 'created_at' => '2026-06-21 12:00:00'],
        ['user_id' => 1, 'log_date' => '2026-06-22', 'sleep_hours' => 4.8, 'sleep_quality' => 52, 'steps' => 1200, 'active_minutes' => 5,  'hrv' => 28, 'resting_hr' => 84, 'social_interaction' => 1, 'voice_stress_score' => 75, 'created_at' => '2026-06-22 12:00:00'],
        ['user_id' => 1, 'log_date' => '2026-06-23', 'sleep_hours' => 5.2, 'sleep_quality' => 58, 'steps' => 2100, 'active_minutes' => 12, 'hrv' => 30, 'resting_hr' => 82, 'social_interaction' => 3, 'voice_stress_score' => 65, 'created_at' => '2026-06-23 12:00:00'],
        ['user_id' => 1, 'log_date' => '2026-06-24', 'sleep_hours' => 6.2, 'sleep_quality' => 70, 'steps' => 4100, 'active_minutes' => 25, 'hrv' => 45, 'resting_hr' => 68, 'social_interaction' => 5, 'voice_stress_score' => 40, 'created_at' => '2026-06-24 12:00:00']
    ];
}
if (!isset($_SESSION['mock_digital_twin_profiles'])) {
    $_SESSION['mock_digital_twin_profiles'] = [
        1 => [
            'user_id' => 1,
            'learned_triggers' => '["Low sleep duration (<6 hours)","Sedentary routines (<3000 steps)","Elevated resting heart rate (>80 bpm)"]',
            'coping_styles' => '["Sensory Grounding (5-4-3-2-1)","Stretching Exercises","Teletherapy Checkins"]',
            'anxiety_resilience' => 65,
            'depression_resistance' => 58,
            'burnout_buffer' => 48,
            'updated_at' => '2026-06-24 12:00:00'
        ]
    ];
}
if (!isset($_SESSION['mock_vr_practice_logs'])) {
    $_SESSION['mock_vr_practice_logs'] = [
        ['id' => 1, 'user_id' => 1, 'simulation_id' => 'forest', 'practice_date' => '2026-06-22', 'duration_seconds' => 180, 'mood_improvement' => 2, 'created_at' => '2026-06-22 10:00:00'],
        ['id' => 2, 'user_id' => 1, 'simulation_id' => 'auditorium', 'practice_date' => '2026-06-23', 'duration_seconds' => 300, 'mood_improvement' => 3, 'created_at' => '2026-06-23 15:00:00'],
        ['id' => 3, 'user_id' => 1, 'simulation_id' => 'forest', 'practice_date' => '2026-06-24', 'duration_seconds' => 240, 'mood_improvement' => 1, 'created_at' => '2026-06-24 09:00:00']
    ];
}
if (!isset($_SESSION['mock_workplace_departments'])) {
    $_SESSION['mock_workplace_departments'] = [
        ['id' => 1, 'name' => 'Nursing'],
        ['id' => 2, 'name' => 'ICU & ER'],
        ['id' => 3, 'name' => 'Social Work'],
        ['id' => 4, 'name' => 'Caregiver Administration']
    ];
}
if (!isset($_SESSION['mock_workplace_survey_responses'])) {
    $_SESSION['mock_workplace_survey_responses'] = [
        ['id' => 1, 'department_id' => 1, 'q1_raise_issues' => 4, 'q2_team_mistakes' => 3, 'q3_supportive_env' => 5, 'q4_respect_others' => 4, 'q5_burnout_level' => 2, 'feedback' => 'Great teamwork, but shift handovers are sometimes chaotic.', 'submitted_date' => '2026-04-15'],
        ['id' => 2, 'department_id' => 1, 'q1_raise_issues' => 5, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 4, 'q4_respect_others' => 5, 'q5_burnout_level' => 1, 'feedback' => 'Nursing staff is very supportive of mistakes.', 'submitted_date' => '2026-04-18'],
        ['id' => 3, 'department_id' => 2, 'q1_raise_issues' => 2, 'q2_team_mistakes' => 2, 'q3_supportive_env' => 3, 'q4_respect_others' => 3, 'q5_burnout_level' => 4, 'feedback' => 'High burnout in the ICU due to scheduling.', 'submitted_date' => '2026-04-20'],
        ['id' => 4, 'department_id' => 2, 'q1_raise_issues' => 3, 'q2_team_mistakes' => 2, 'q3_supportive_env' => 4, 'q4_respect_others' => 3, 'q5_burnout_level' => 4, 'feedback' => 'Friction between residents and senior staff.', 'submitted_date' => '2026-04-22'],
        ['id' => 5, 'department_id' => 3, 'q1_raise_issues' => 4, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 4, 'q4_respect_others' => 4, 'q5_burnout_level' => 3, 'feedback' => 'Heavy caseloads make it hard to sync.', 'submitted_date' => '2026-04-25'],
        ['id' => 6, 'department_id' => 1, 'q1_raise_issues' => 3, 'q2_team_mistakes' => 3, 'q3_supportive_env' => 4, 'q4_respect_others' => 4, 'q5_burnout_level' => 3, 'feedback' => 'Feeling slight burnout from overtime.', 'submitted_date' => '2026-05-12'],
        ['id' => 7, 'department_id' => 1, 'q1_raise_issues' => 4, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 5, 'q4_respect_others' => 5, 'q5_burnout_level' => 2, 'feedback' => 'Team is cohesive and respects boundaries.', 'submitted_date' => '2026-05-15'],
        ['id' => 8, 'department_id' => 2, 'q1_raise_issues' => 2, 'q2_team_mistakes' => 1, 'q3_supportive_env' => 2, 'q4_respect_others' => 3, 'q5_burnout_level' => 5, 'feedback' => 'Extremely short staffed. Need structural intervention.', 'submitted_date' => '2026-05-18'],
        ['id' => 9, 'department_id' => 2, 'q1_raise_issues' => 3, 'q2_team_mistakes' => 2, 'q3_supportive_env' => 3, 'q4_respect_others' => 4, 'q5_burnout_level' => 4, 'feedback' => 'Under immense pressure.', 'submitted_date' => '2026-05-20'],
        ['id' => 10, 'department_id' => 3, 'q1_raise_issues' => 5, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 5, 'q4_respect_others' => 5, 'q5_burnout_level' => 2, 'feedback' => 'Great environment.', 'submitted_date' => '2026-05-22'],
        ['id' => 11, 'department_id' => 4, 'q1_raise_issues' => 4, 'q2_team_mistakes' => 5, 'q3_supportive_env' => 4, 'q4_respect_others' => 4, 'q5_burnout_level' => 2, 'feedback' => 'Administrative workload is high but manageable.', 'submitted_date' => '2026-05-28'],
        ['id' => 12, 'department_id' => 1, 'q1_raise_issues' => 4, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 4, 'q4_respect_others' => 4, 'q5_burnout_level' => 2, 'feedback' => 'Decent support this month.', 'submitted_date' => '2026-06-10'],
        ['id' => 13, 'department_id' => 1, 'q1_raise_issues' => 5, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 5, 'q4_respect_others' => 5, 'q5_burnout_level' => 1, 'feedback' => 'Love this team.', 'submitted_date' => '2026-06-12'],
        ['id' => 14, 'department_id' => 2, 'q1_raise_issues' => 3, 'q2_team_mistakes' => 2, 'q3_supportive_env' => 3, 'q4_respect_others' => 3, 'q5_burnout_level' => 4, 'feedback' => 'Friction is improving slowly but stress remains high.', 'submitted_date' => '2026-06-15'],
        ['id' => 15, 'department_id' => 2, 'q1_raise_issues' => 1, 'q2_team_mistakes' => 2, 'q3_supportive_env' => 2, 'q4_respect_others' => 2, 'q5_burnout_level' => 5, 'feedback' => 'ICU team is overwhelmed, high turnover.', 'submitted_date' => '2026-06-18'],
        ['id' => 16, 'department_id' => 3, 'q1_raise_issues' => 4, 'q2_team_mistakes' => 3, 'q3_supportive_env' => 4, 'q4_respect_others' => 4, 'q5_burnout_level' => 3, 'feedback' => 'Anonymity makes it easy to share concerns.', 'submitted_date' => '2026-06-20'],
        ['id' => 17, 'department_id' => 4, 'q1_raise_issues' => 5, 'q2_team_mistakes' => 4, 'q3_supportive_env' => 5, 'q4_respect_others' => 4, 'q5_burnout_level' => 2, 'feedback' => 'Management is responsive to issues.', 'submitted_date' => '2026-06-22']
    ];
}
if (!isset($_SESSION['mock_workplace_user_survey_status'])) {
    $_SESSION['mock_workplace_user_survey_status'] = [
        ['user_id' => 1, 'survey_period' => '2026-04'],
        ['user_id' => 1, 'survey_period' => '2026-05']
    ];
}
if (!isset($_SESSION['mock_workplace_conflicts'])) {
    $_SESSION['mock_workplace_conflicts'] = [
        [
            'id' => 1,
            'department_id' => 1,
            'description' => 'Communication friction during shift handovers causing stress spikes and transcription errors.',
            'severity' => 'medium',
            'status' => 'resolved',
            'ai_mitigation_plan' => 'Implement 10-minute structured SBAR (Situation, Background, Assessment, Recommendation) templates for cross-shift handovers. Conduct a brief team training.',
            'logged_date' => '2026-06-10'
        ],
        [
            'id' => 2,
            'department_id' => 2,
            'description' => 'Inter-disciplinary tension between junior residents and senior nursing supervisors regarding safety protocol overrides in ER.',
            'severity' => 'high',
            'status' => 'investigating',
            'ai_mitigation_plan' => 'Conduct a facilitated joint debriefing session led by the clinical specialist. Formally document nursing override authority pathways to clarify boundary rights.',
            'logged_date' => '2026-06-18'
        ],
        [
            'id' => 3,
            'department_id' => 3,
            'description' => 'Case distribution inequality friction leading to feelings of isolation and overload among social workers.',
            'severity' => 'medium',
            'status' => 'open',
            'ai_mitigation_plan' => 'Utilize the Peer Matching index to optimize case sharing. Redesign workload distribution templates to include active caregiver metrics.',
            'logged_date' => '2026-06-23'
        ]
    ];
}

// Global suspension check and crisis state synchronization
$current_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['user_id']) && !in_array($current_page, ['login.php', 'signup.php', 'logout.php'])) {
    $user_id = $_SESSION['user_id'];
    $is_currently_suspended = false;
    $db_crisis_state = 0;

    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT is_suspended, crisis_state FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $u_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u_info) {
                if ($u_info['is_suspended'] == 1) {
                    $is_currently_suspended = true;
                }
                $db_crisis_state = (int)$u_info['crisis_state'];
            }
        } catch (PDOException $ex) {}
    } else {
        $user_email = $_SESSION['user_email'] ?? '';
        if (isset($_SESSION['mock_users'][$user_email])) {
            $mu = $_SESSION['mock_users'][$user_email];
            if (($mu['is_suspended'] ?? 0) == 1) {
                $is_currently_suspended = true;
            }
            $db_crisis_state = (int)($mu['crisis_state'] ?? 0);
        }
    }

    if ($is_currently_suspended) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        session_start();
        header('Location: login.php?suspended=1');
        exit;
    }

    // Sync crisis state
    $_SESSION['crisis_state'] = $db_crisis_state;
}
?>
