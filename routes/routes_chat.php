<?php
// routes/routes_chat.php

require_once __DIR__ . '/../controllers/ChatController.php';

$controller = new ChatController();
$method = $_SERVER['REQUEST_METHOD'];

// Parse Action from URL (v1/chat/{action})
// URI is like /vigilant_backend/v1/chat/message
// The main index.php logic usually sets $actionName from the last part, e.g. "message"

if ($method === 'POST') {
    switch ($actionName) {
        case 'message':
            $controller->sendMessage();
            break;
        default:
            Response::error("NOT_FOUND", "Chat action not found", 404);
            break;
    }
} else {
    Response::error("METHOD_NOT_ALLOWED", "Method not allowed for Chat", 405);
}
?>
