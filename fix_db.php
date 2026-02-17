<?php
require_once __DIR__ . '/config/db.php';

$db = (new Database())->getConnection();

// 1. Trim Users Email
$q = "UPDATE users SET email = TRIM(email)";
$stmt = $db->prepare($q);
if ($stmt->execute()) {
    echo "trimmed emails.\n";
} else {
    echo "failed to trim emails.\n";
}

// 2. Trim Full Name
$q = "UPDATE users SET full_name = TRIM(full_name)";
$stmt = $db->prepare($q);
$stmt->execute();

echo "Database cleaned.\n";
?>
