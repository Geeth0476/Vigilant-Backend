<?php
// public/seed_data.php

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Connected. Seeding data...\n";
    
    // 1. Seed Community Threats
    $threats = [
        [
            'app_name' => 'SpyTracker Pro',
            'package_name' => 'com.spy.tracker.pro',
            'category' => 'STALKERWARE',
            'risk_level' => 'CRITICAL',
            'report_count' => 154,
            'first_seen_at' => date('Y-m-d', strtotime('-30 days')),
            'description' => 'A known stalkerware app that hides its icon and forwards SMS to a remote server.',
            'behaviors' => json_encode(['Hides Icon', 'Reads SMS', 'Tracks Location'])
        ],
        [
            'app_name' => 'Flashlight Brightest',
            'package_name' => 'com.flashlight.brightest.super',
            'category' => 'PERMISSION_ABUSE',
            'risk_level' => 'HIGH',
            'report_count' => 42,
            'first_seen_at' => date('Y-m-d', strtotime('-10 days')),
            'description' => 'Requests unnecessary contacts and location permissions. Sends data to offshore ad servers.',
            'behaviors' => json_encode(['Excessive Ads', 'Reads Contacts', 'Background Data'])
        ],
        [
            'app_name' => 'Social Downloader',
            'package_name' => 'net.video.downloader.social',
            'category' => 'ADWARE',
            'risk_level' => 'MEDIUM',
            'report_count' => 89,
            'first_seen_at' => date('Y-m-d', strtotime('-5 days')),
            'description' => 'Displays full-screen ads outside the app context.',
            'behaviors' => json_encode(['Pop-up Ads', 'Battery Drain'])
        ],
        [
            'app_name' => 'Fake Bank Login',
            'package_name' => 'com.finance.login.secure',
            'category' => 'DATA_THEFT',
            'risk_level' => 'CRITICAL',
            'report_count' => 12,
            'first_seen_at' => date('Y-m-d', strtotime('-2 days')),
            'description' => 'Phishing app mimicking major bank login screens.',
            'behaviors' => json_encode(['Phishing', 'Overlay Attack'])
        ]
    ];
    
    $stmt = $conn->prepare("INSERT INTO community_threats (app_name, package_name, category, risk_level, report_count, first_seen_at, description, behaviors) VALUES (:name, :pkg, :cat, :risk, :count, :seen, :desc, :behav)");
    
    foreach ($threats as $t) {
        // Check if exists
        $check = $conn->prepare("SELECT id FROM community_threats WHERE package_name = :pkg");
        $check->execute([':pkg' => $t['package_name']]);
        if ($check->rowCount() == 0) {
            $stmt->execute([
                ':name' => $t['app_name'],
                ':pkg'  => $t['package_name'],
                ':cat'  => $t['category'],
                ':risk' => $t['risk_level'],
                ':count' => $t['report_count'],
                ':seen' => $t['first_seen_at'],
                ':desc' => $t['description'],
                ':behav' => $t['behaviors']
            ]);
            echo "Inserted threat: " . $t['app_name'] . "\n";
        } else {
            echo "Skipped existing threat: " . $t['app_name'] . "\n";
        }
    }
    
    echo "Seeding completed successfully.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
