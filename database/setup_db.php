<?php
// setup_db.php
// Re-implementation script for Vigilant V2 Database
// Run this to reset the database to the schema described in the analysis.

$host = 'localhost';
$user = 'root'; // Default XAMPP user
$pass = '';     // Default XAMPP password
$dbname = 'vigilant_db';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to MySQL server.\n";

    // Read the SQL file
    $sqlFile = __DIR__ . '/vigilant_db_v2.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found at " . $sqlFile . "\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    echo "Importing V2 Schema...\n";
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "Database '$dbname' has been successfully re-implemented and imported.\n";
    echo "This fixes the data insertion issues by using the new tolerant schema.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>
