<?php
// public/clear_community.php

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Disable foreign key checks to allow truncation
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate the tables
    $conn->exec("TRUNCATE TABLE community_threats");
    $conn->exec("TRUNCATE TABLE community_reports");
    
    // Re-enable foreign keys
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo json_encode(["status" => "success", "message" => "Community data cleared successfully. Tables are now empty and ready for real updates."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to clear data: " . $e->getMessage()]);
}
?>
