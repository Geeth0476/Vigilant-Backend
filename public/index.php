<?php
// public/index.php

// 1. Setup Environment
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/validation.php';
require_once __DIR__ . '/../core/auth.php';

// 2. Setup Logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api.log');

// DEBUG: Log all requests
file_put_contents(__DIR__ . '/../logs/requests.log', "[" . date('Y-m-d H:i:s') . "] " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . " - IP: " . ($_SERVER['REMOTE_ADDR']??'Unknown') . "\n", FILE_APPEND);

// 2.1 Basic CORS (useful for local testing / tools)
// NOTE: For production, lock this down to your app/domain.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 3. Simple Router
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = $scriptDir; // typically: /vigilant_backend/public
$path = parse_url($requestUri, PHP_URL_PATH);
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Route Routing
// Format: /v1/resource/action
// e.g. /v1/auth/register

$parts = explode('/', trim($path, '/'));
$version = isset($parts[0]) ? $parts[0] : null;
$controllerName = isset($parts[1]) ? $parts[1] : null;
$actionName = isset($parts[2]) ? $parts[2] : null;

file_put_contents(__DIR__ . '/../logs/debug_routing.txt', "Path: $path\nParts: " . print_r($parts, true) . "\nController: $controllerName\nAction: $actionName\n", FILE_APPEND);

// Check Version
if ($version !== API_VERSION) {
    // For now, if no version or wrong version, simple error or default
    if (empty($version)) {
        Response::success(["message" => "Vigilant Backend API is running."]);
    }
}

// 4. Dispatch to Route Files
// We define logic based on the 'controllerName' (which acts as a group here)

switch ($controllerName) {
    case 'auth':
        require_once __DIR__ . '/../routes/routes_auth.php';
        break;
    case 'profile':
        require_once __DIR__ . '/../routes/routes_profile.php';
        break;
    case 'devices':
        require_once __DIR__ . '/../routes/routes_devices.php';
        break;
    case 'scan':
        require_once __DIR__ . '/../routes/routes_scan.php';
        break;
    case 'apps':
        require_once __DIR__ . '/../routes/routes_apps.php';
        break;
    case 'alerts':
        require_once __DIR__ . '/../routes/routes_alerts.php';
        break;
    case 'community':
        require_once __DIR__ . '/../routes/routes_community.php';
        break;
    case 'settings':
        require_once __DIR__ . '/../routes/routes_settings.php';
        break;
    case 'reports':
        require_once __DIR__ . '/../routes/routes_reports.php';
        break;
    case 'chat':
        require_once __DIR__ . '/../routes/routes_chat.php';
        break;
    default:
        Response::error("NOT_FOUND", "Endpoint not found: $controllerName", 404);
        break;
}
?>
