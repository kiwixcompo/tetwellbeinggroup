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

    echo "📂 Reading schema.sql file...\n";
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Error: schema.sql file not found in " . __DIR__);
    }
    
    $sql = file_get_contents($schema_file);
    // Remove USE statement from SQL to avoid overriding the cPanel database name if different
    $sql = preg_replace('/USE\s+`[^`]+`;/i', '', $sql);
    
    echo "✅ Loaded schema.sql (" . strlen($sql) . " bytes).\n\n";

    echo "⚙️ Executing SQL statements...\n";
    $pdo->exec($sql);
    echo "✅ Tables created, and seed data initialized successfully!\n\n";
    
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
