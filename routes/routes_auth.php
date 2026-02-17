<?php
// routes/routes_auth.php

// Determine specific action from URL parts
// URL Structure: /v1/auth/{action}
// $actionName is derived in index.php

require_once __DIR__ . '/../controllers/AuthController.php';

file_put_contents(__DIR__ . '/../logs/route_trace.txt', "Loaded routes_auth.php. Action: $actionName\n", FILE_APPEND);

try {
    $controller = new AuthController();
    file_put_contents(__DIR__ . '/../logs/route_trace.txt', "Controller instantiated.\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/../logs/route_trace.txt', "Controller Init Failed: " . $e->getMessage() . "\n", FILE_APPEND);
    Response::error("SERVER_ERROR", "DB Init Failed");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // FORCE REGISTER CHECK
    if ($actionName === 'register') {
         file_put_contents(__DIR__ . '/../logs/route_trace.txt', "Calling register()...\n", FILE_APPEND);
         $controller->register();
    }
    switch ($actionName) {
        // case 'register': ... removed redundant check 
        case 'login':
            $controller->login();
            break;
        case 'verify-otp':
            $controller->verifyOtp();
            break;
        case 'resend-otp':
            $controller->resendOtp();
            break;
        case 'logout':
            $controller->logout();
            break;
        case 'revoke-all':
            $controller->revokeAllOtherSessions();
            break;
        case 'change-password':
            $controller->changePassword();
            break;
        case 'forgot-password':
            $controller->forgotPassword();
            break;
        case 'reset-password':
            $controller->resetPassword();
            break;
        default:
            Response::error("NOT_FOUND", "Action '$actionName' not found on Auth controller", 404);
            break;
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed for Auth", 405);
}
?>
