<?php
// routes/routes_settings.php

require_once __DIR__ . '/../controllers/SettingsController.php';

$controller = new SettingsController();
$method = $_SERVER['REQUEST_METHOD'];

// URL: /v1/settings/scan, /v1/settings/alerts, /v1/settings/privacy

if ($method === 'GET') {
    switch ($actionName) {
        case 'scan':
            $controller->getScanSettings();
            break;
        case 'alerts':
            $controller->getAlertRules();
            break;
        case 'privacy':
            $controller->getPrivacySettings();
            break;
        default:
            Response::error("NOT_FOUND", "Settings action not found", 404);
            break;
    }
} elseif ($method === 'PUT' || $method === 'POST') {
    switch ($actionName) {
        case 'scan':
            $controller->updateScanSettings();
            break;
        case 'alerts':
            $controller->updateAlertRules();
            break;
        case 'privacy':
            $controller->updatePrivacySettings();
            break;
        default:
            Response::error("NOT_FOUND", "Settings action not found", 404);
            break;
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>
