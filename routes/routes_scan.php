<?php
// routes/routes_scan.php

require_once __DIR__ . '/../controllers/ScanController.php';

$controller = new ScanController();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    switch ($actionName) {
        case 'start':
            $controller->start();
            break;
        case 'complete':
            $controller->complete();
            break;
        default:
            // Check if it's /v1/scan/{id}/progress
            $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
            $maybeScanId = $parts[2] ?? null;   // v1/scan/{id}/progress
            $maybeAction = $parts[3] ?? null;   // progress
            if ($maybeScanId && is_numeric($maybeScanId) && $maybeAction === 'progress') {
                $controller->updateProgress((int)$maybeScanId);
                break;
            }
            Response::error("NOT_FOUND", "Scan action not found", 404);
            break;
    }
} elseif ($method === 'GET') {
    switch ($actionName) {
        case 'latest':
            $controller->latest();
            break;
        case 'status':
            $controller->status();
            break;
        case 'active':
            $controller->getActiveScan();
            break;
        case 'dashboard':
            $controller->getDashboardData();
            break;
        default:
            Response::error("NOT_FOUND", "Scan GET action not found", 404);
            break;
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>
