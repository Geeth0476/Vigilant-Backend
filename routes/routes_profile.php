<?php
// routes/routes_profile.php
require_once __DIR__ . '/../controllers/ProfileController.php';

$controller = new ProfileController();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $controller->getProfile();
} elseif ($method === 'PUT') {
    $controller->updateProfile();
} elseif ($method === 'POST') {
    // Check if feedback action
    // Simple router logic check from index.php -> $actionName might be 'feedback' if URL is /v1/profile/feedback
    // OR if we designated a separate route file.
    // Based on previous files, $actionName is derived.
    if (isset($actionName) && $actionName === 'feedback') {
        $controller->submitFeedback();
    } else {
        // Default POST might be update or something else, but here update is PUT.
        // Let's assume Profile update can be POST for compatibility or strict PUT.
        $controller->updateProfile();
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed", 405);
}
?>
