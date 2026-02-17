<?php
// routes/routes_devices.php

require_once __DIR__ . '/../controllers/DevicesController.php';

$controller = new DevicesController();
$method = $_SERVER['REQUEST_METHOD'];

// URL: /v1/devices/register, /v1/devices (list), /v1/devices/{device_uuid}/revoke
if ($method === 'POST') {
    switch ($actionName) {
        case 'register':
            $controller->register();
            break;
        default:
            // treat /v1/devices/{uuid}/revoke
            $parts = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
            $maybeUuid = $parts[2] ?? null;   // v1/devices/{uuid}/revoke
            $maybeAction = $parts[3] ?? null; // revoke
            if ($maybeUuid && $maybeAction === 'revoke') {
                $controller->revoke($maybeUuid);
                break;
            }
            Response::error("NOT_FOUND", "Devices action not found", 404);
            break;
    }
} elseif ($method === 'GET') {
    // /v1/devices
    $controller->list();
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>

