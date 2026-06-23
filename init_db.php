<?php
/**
 * Tet Wellbeing Group - Database Setup & Initialization Script
 * Connects to MySQL and executes the schema.sql script to build and seed the database.
 */

header('Content-Type: text/plain; charset=utf-8');

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'tet_wellbeing';

echo "========================================================\n";
echo "       TET WELLBEING GROUP - DATABASE SETUP SCRIPT      \n";
echo "========================================================\n\n";

try {
    echo "🔌 Connecting to MySQL Server at '$db_host'...\n";
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "✅ Connected to MySQL successfully.\n\n";

    echo "📂 Reading schema.sql file...\n";
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Error: schema.sql file not found in " . __DIR__);
    }
    
    $sql = file_get_contents($schema_file);
    echo "✅ Loaded schema.sql (" . strlen($sql) . " bytes).\n\n";

    echo "⚙️ Executing SQL statements...\n";
    // We can execute multiple statements at once using exec
    $pdo->exec($sql);
    echo "✅ Database and tables created, and seed data initialized successfully!\n\n";
    
    echo "🎉 Database Name: $db_name\n";
    echo "🎉 Credentials configured:\n";
    echo "   - Host: $db_host\n";
    echo "   - User: $db_user\n";
    echo "   - Pass: (empty)\n\n";
    echo "💡 You can now log in using:\n";
    echo "   - Client Demo User: mark@tetwellbeing.com (Password: password123)\n";
    echo "   - Specialist User: evelyn@tetwellbeing.com (Password: password123)\n";
    echo "   - Admin User: admin@tetwellbeinggroup.com (Password: Admin123!)\n\n";

} catch (Exception $e) {
    echo "❌ Error setting up database:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "💡 Please check if MySQL is running and your connection details in db.php match.\n";
}
echo "========================================================\n";
?>
