<?php
// routes/routes_apps.php

require_once __DIR__ . '/../controllers/AppsController.php';

$controller = new AppsController();
$method = $_SERVER['REQUEST_METHOD'];

// URL: /v1/apps OR /v1/apps/{package_name}

if ($method === 'GET') {
    // Check if package name is in URL
    // $actionName is the part AFTER 'apps'. 
    // If /v1/apps, $actionName is empty/null (depending on index.php)
    
    // index.php logic: $parts[2] is actionName.
    // If URL is /v1/apps, parts are [v1, apps]. actionName is null.
    // If URL is /v1/apps/com.foo.bar, actionName is com.foo.bar.
    
    if (!empty($actionName)) {
        $controller->getApp($actionName);
    } else {
        $controller->listApps();
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>
