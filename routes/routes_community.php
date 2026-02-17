<?php
// routes/routes_community.php

require_once __DIR__ . '/../controllers/CommunityController.php';

$controller = new CommunityController();
$method = $_SERVER['REQUEST_METHOD'];

// URL: /v1/community/{action}/{id}

if ($method === 'GET') {
    if ($actionName === 'threats') {
        // Check if ID is passed as next segment (not properly parsed in index.php primitive router, let's fix logic assumption)
        // index.php parts: v1 / community / threats / {id}
        // $actionName is 'threats'.
        // So checking next part of URL manually or improving router.
        
        // Simple hack for now:
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = explode('/', trim($path, '/'));
        // parts[0]=vigilant_backend ... wait, my index.php router logic handled parts relative to public.
        // Let's assume index.php does $parts = explode('/', trim($path, '/')); where path is relative.
        // v1/community/threats/123
        // [0]=v1, [1]=community, [2]=threats, [3]=123
        $id = isset($parts[3]) ? $parts[3] : null;
        
        if ($id && is_numeric($id)) {
            $controller->getThreatDetail($id);
        } else {
            $controller->getThreats();
        }
    } elseif ($actionName === 'my-reports') {
        $controller->getMyReports();
    }
} elseif ($method === 'POST') {
    if ($actionName === 'reports') {
        $controller->submitReport();
    }
}
?>
