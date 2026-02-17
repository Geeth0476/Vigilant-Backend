<?php
// fix_db.php
// Script to add missing revoked_at column to user_sessions

require_once __DIR__ . '/../config/db.php';

try {
    $db = (new Database())->getConnection();
    echo "Connected...<br>";
    
    // add column if not exists
    $sql = "ALTER TABLE user_sessions ADD COLUMN revoked_at TIMESTAMP NULL DEFAULT NULL AFTER expires_at";
    
    try {
        $db->exec($sql);
        echo "SUCCESS: Added 'revoked_at' column.<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate column") !== false) {
             echo "Column 'revoked_at' already exists.<br>";
        } else {
             echo "Error adding column: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Database Fix Complete.";
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
?>
