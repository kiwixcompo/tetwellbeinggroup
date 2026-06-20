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
$db_user = 'root';
$db_pass = '';
$db_name = 'tet_wellbeing';

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
        "is_suspended TINYINT(1) DEFAULT 0"
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
    $stmt->execute(['admin@tetwellbeing.com']);
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $insert->execute(['System Admin', 'admin@tetwellbeing.com', $hashed_password]);
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
        'admin@tetwellbeing.com' => [
            'id' => 99,
            'name' => 'System Admin',
            'email' => 'admin@tetwellbeing.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
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
            'archetype' => NULL
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

// Global suspension check
$current_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['user_id']) && !in_array($current_page, ['login.php', 'signup.php', 'logout.php'])) {
    $user_id = $_SESSION['user_id'];
    $is_currently_suspended = false;
    if ($db_connected && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT is_suspended FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $susp = $stmt->fetchColumn();
            if ($susp == 1) {
                $is_currently_suspended = true;
            }
        } catch (PDOException $ex) {}
    } else {
        $user_email = $_SESSION['user_email'] ?? '';
        if (isset($_SESSION['mock_users'][$user_email]['is_suspended']) && $_SESSION['mock_users'][$user_email]['is_suspended'] == 1) {
            $is_currently_suspended = true;
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
}
?>
