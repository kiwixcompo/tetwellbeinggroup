<?php
/**
 * Tet Wellbeing Group - Database Setup & Initialization Script
 * Connects to MySQL and executes the schema.sql script to build and seed the database.
 */

header('Content-Type: text/plain; charset=utf-8');

// Include db.php config if available to read variables dynamically
if (file_exists(__DIR__ . '/db.php')) {
    // We wrap it in a function context or read it to avoid side effects of db.php's auto-init
    $db_code = file_get_contents(__DIR__ . '/db.php');
    
    // Parse variables out using regex to avoid executing db.php's active PDO connection
    if (preg_match('/\$db_host\s*=\s*[\'"]([^\'"]+)[\'"]/', $db_code, $matches)) {
        $db_host = $matches[1];
    } else {
        $db_host = 'localhost';
    }
    
    if (preg_match('/\$db_user\s*=\s*[\'"]([^\'"]*)[\'"]/', $db_code, $matches)) {
        $db_user = $matches[1];
    } else {
        $db_user = 'root';
    }
    
    if (preg_match('/\$db_pass\s*=\s*[\'"]([^\'"]*)[\'"]/', $db_code, $matches)) {
        $db_pass = $matches[1];
    } else {
        $db_pass = '';
    }
    
    if (preg_match('/\$db_name\s*=\s*[\'"]([^\'"]+)[\'"]/', $db_code, $matches)) {
        $db_name = $matches[1];
    } else {
        $db_name = 'tet_wellbeing';
    }
} else {
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'tet_wellbeing';
}

echo "========================================================\n";
echo "       TET WELLBEING GROUP - DATABASE SETUP SCRIPT      \n";
echo "========================================================\n\n";

try {
    $masked_pass = empty($db_pass) ? '(none)' : str_repeat('*', strlen($db_pass));
    echo "⚙️ Configuration loaded from db.php:\n";
    echo "   - Host:     $db_host\n";
    echo "   - Database: $db_name\n";
    echo "   - User:     $db_user\n";
    echo "   - Password: $masked_pass\n\n";

    echo "🔌 Connecting to MySQL Server...\n";
    
    $pdo = null;
    try {
        // Try connecting directly to the database first (essential for cPanel where DB is pre-created)
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        echo "✅ Connected to existing database '$db_name' successfully.\n\n";
    } catch (PDOException $e) {
        // Fallback for local WAMP (access denied error code 1045)
        if ($e->getCode() == 1045 || strpos($e->getMessage(), 'Access denied') !== false) {
            try {
                echo "ℹ️ Connection failed with db.php credentials. Attempting local WAMP 'root' fallback...\n";
                $db_user = 'root';
                $db_pass = '';
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                echo "✅ Connected to database '$db_name' using local WAMP root credentials.\n\n";
            } catch (PDOException $e2) {
                if ($e2->getCode() == 1049 || strpos($e2->getMessage(), 'Unknown database') !== false) {
                    $e = $e2;
                } else {
                    throw $e2;
                }
            }
        }
        
        if ($pdo === null) {
            // If database doesn't exist, try connecting to host only and creating it (local development setup)
            if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
                echo "ℹ️ Database '$db_name' not found. Attempting to create it...\n";
                $temp_pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "✅ Database '$db_name' created.\n";
                
                // Reconnect with database selected
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                echo "✅ Connected to newly created database.\n\n";
            } else {
                throw $e;
            }
        }
    }

    echo "📂 Reading schema.sql file...\n";
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Error: schema.sql file not found in " . __DIR__);
    }
    
    $sql = file_get_contents($schema_file);
    // Remove USE statement from SQL to avoid overriding the cPanel database name if different
    $sql = preg_replace('/USE\s+`[^`]+`;/i', '', $sql);
    
    echo "✅ Loaded schema.sql (" . strlen($sql) . " bytes).\n\n";

    // Ensure department_id column exists in users table before executing schema.sql
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `department_id` INT DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    echo "⚙️ Executing SQL statements...\n";
    $pdo->exec($sql);
    echo "✅ Tables created, and seed data initialized successfully!\n\n";
    
    echo "🧹 Cleaning up double-escaped HTML entities in existing tables...\n";
    $cleanup_tables = [
        'users' => ['name', 'bio', 'license_no'],
        'daily_checkins' => ['notes'],
        'caregiver_respite_breaks' => ['cover_plan'],
        'teletherapy_bookings' => ['therapist_name', 'insurance_provider'],
        'community_posts' => ['author_name', 'content'],
        'ai_chat_logs' => ['message']
    ];

    foreach ($cleanup_tables as $table => $columns) {
        $table_check = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount();
        if ($table_check > 0) {
            foreach ($columns as $column) {
                $pdo->exec("UPDATE `$table` SET `$column` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`$column`, '&amp;', '&'), '&#039;', '\''), '&quot;', '\"'), '&lt;', '<'), '&gt;', '>') WHERE `$column` LIKE '%&%'");
            }
        }
    }
    echo "✅ Existing records cleaned.\n\n";
    
    echo "💡 You can now log in using:\n";
    echo "   - Client Demo User: mark@tetwellbeing.com (Password: password123)\n";
    echo "   - Specialist User: evelyn@tetwellbeing.com (Password: password123)\n";
    echo "   - Admin User: admin@tetwellbeinggroup.com (Password: Admin123!)\n\n";

} catch (Exception $e) {
    echo "❌ Error setting up database:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "💡 Recommendation for cPanel / Live Server:\n";
    echo "   1. Go to cPanel -> MySQL Database Wizard.\n";
    echo "   2. Create a database, a user, and a strong password.\n";
    echo "   3. Assign the user to the database with ALL PRIVILEGES.\n";
    echo "   4. Update db.php variables (\$db_host, \$db_user, \$db_pass, \$db_name) with these credentials.\n";
    echo "   5. Refresh this setup page to complete table creation and seeding.\n";
}
echo "========================================================\n";
?>
