<?php
// core/auth.php

class Auth {
    // Determine user from Bearer Token
    public static function check() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!isset($headers['Authorization'])) {
            // Try different case
             $headers = array_change_key_case($headers, CASE_LOWER);
             if (!isset($headers['authorization'])) {
                 return null;
             }
        }
        $authHeader = $headers['Authorization'] ?? $headers['authorization'];
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            return self::validateToken($token);
        }
        return null; // No token found
    }

    public static function user() {
        return self::check();
    }

    public static function requireLogin($allowUnverified = false) {
        $user = self::check();
        if (!$user) {
            Response::error("UNAUTHORIZED", "Authentication required", 401);
        }
        
        // Security Flaw Fix: Block unverified users from accessing API features
        // Unless explicitly allowed (e.g., for verifying OTP)
        if (!$allowUnverified && isset($user['is_verified']) && (int)$user['is_verified'] !== 1) {
             Response::error("UNVERIFIED", "Please verify your email address first.", 403);
        }
        
        return $user;
    }

    private static function validateToken($token) {
        // Validate against user_sessions table
        $db = (new Database())->getConnection();
        $query = "SELECT u.*, s.device_id as current_device_id 
                  FROM user_sessions s 
                  JOIN users u ON u.id = s.user_id 
                  WHERE s.access_token = :token AND (s.expires_at IS NULL OR s.expires_at > NOW()) AND s.revoked_at IS NULL";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }

    public static function generateToken() {
        return bin2hex(random_bytes(32)); // Simple opaque token for now. For JWT, use utils/jwt.php
    }
}
?>
