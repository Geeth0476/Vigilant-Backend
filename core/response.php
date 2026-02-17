<?php
// core/response.php

class Response {
    public static function json($data, $success = true, $statusCode = 200, $error = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            "success" => $success,
            "data" => $data,
            "error" => $error
        ];

        echo json_encode($response);
        exit;
    }

    public static function error($code, $message, $statusCode = 400) {
        $log = "[" . date('Y-m-d H:i:s') . "] Error Response ($statusCode): $code - " . json_encode($message) . "\n";
        file_put_contents(__DIR__ . '/../logs/response_errors.txt', $log, FILE_APPEND);
        
        self::json(null, false, $statusCode, [
            "code" => $code,
            "message" => $message
        ]);
    }

    public static function success($data = null, $statusCode = 200) {
        self::json($data, true, $statusCode, null);
    }
}
?>
