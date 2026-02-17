<?php
// public/update_db_otp.php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Add columns if they don't exist
    $columns = [
        "ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0",
        "ADD COLUMN otp_code VARCHAR(6) NULL",
        "ADD COLUMN otp_expires_at TIMESTAMP NULL"
    ];
    
    foreach ($columns as $col) {
        try {
            $conn->exec("ALTER TABLE users " . $col);
        } catch (PDOException $e) {
            // Ignore if column exists
        }
    }
    
    echo json_encode(["status" => "success", "message" => "Database updated for OTP support"]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
