<?php
// routes/routes_alerts.php

require_once __DIR__ . '/../controllers/AlertsController.php';

$controller = new AlertsController();
$method = $_SERVER['REQUEST_METHOD'];

// URL: /v1/alerts/recent, /v1/alerts/{public_id}, /v1/alerts/ack

if ($method === 'GET') {
    if ($actionName === 'recent') {
        $controller->getRecent();
    } else {
        // Treat as public_id for detail
        if ($actionName) {
            $controller->getDetail($actionName);
        } else {
            Response::error("NOT_FOUND", "Alert action not found", 404);
        }
    }
} elseif ($method === 'POST') {
    if ($actionName === 'ack') {
        $controller->acknowledge();
    } else {
        Response::error("NOT_FOUND", "Alert action not found", 404);
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>
