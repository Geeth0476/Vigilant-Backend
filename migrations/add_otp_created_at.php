<?php
require_once __DIR__ . '/../core/Database.php';
$db = (new Database())->getConnection();

try {
    // Check if column exists first
    $check = $db->query("SHOW COLUMNS FROM users LIKE 'otp_created_at'");
    if ($check->rowCount() == 0) {
        $db->exec("ALTER TABLE users ADD COLUMN otp_created_at TIMESTAMP NULL DEFAULT NULL AFTER otp_expires_at");
        echo "Column otp_created_at added successfully.\n";
    } else {
        echo "Column otp_created_at already exists.\n";
    }
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
?>
