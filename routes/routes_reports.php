<?php
// routes/routes_reports.php

require_once __DIR__ . '/../controllers/ReportsController.php';

$controller = new ReportsController();
$method = $_SERVER['REQUEST_METHOD'];

// URL: /v1/reports/weekly, /v1/reports/weekly/{id}, /v1/reports/history

if ($method === 'GET') {
    if ($actionName === 'weekly') {
        // Check if ID is in next segment
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        $id = isset($parts[3]) ? $parts[3] : null; // v1/reports/weekly/{id}
        
        if ($id && is_numeric($id)) {
            $controller->getDetail((int)$id);
        } else {
            $controller->getWeekly();
        }
    } elseif ($actionName === 'history') {
        $controller->getHistory();
    } else {
        Response::error("NOT_FOUND", "Reports action not found", 404);
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>
