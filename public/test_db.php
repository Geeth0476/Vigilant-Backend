<?php
// public/test_db.php

require_once '../config/db.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        $stmt = $conn->query("SELECT VERSION() as version");
        $row = $stmt->fetch();
        echo json_encode([
            "status" => "success", 
            "message" => "Database Connected Successfully", 
            "version" => $row['version']
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Connection object is null"]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error", 
        "message" => "Connection Failed: " . $e->getMessage()
    ]);
}
?>
