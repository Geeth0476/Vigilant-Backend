<?php
// models/User.php

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $email;
    public $password; // plain text for input, hash for db
    public $full_name;
    public $phone;
    public $profile_image;
    public $password_hash_db;
    public $is_premium;
    public $created_at;
    public $updated_at;
    public $otp_created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " (email, password_hash, full_name, phone) VALUES (:email, :password_hash, :full_name, :phone)";
        $stmt = $this->conn->prepare($query);

        // Clean
        $this->email = trim(htmlspecialchars(strip_tags($this->email)));
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? ''));
        
        // Ensure phone is not null if DB requires it (use empty string if null)
        $phone_val = $this->phone ? $this->phone : '';

        // Hash password
        $password_hash = password_hash($this->password, PASSWORD_BCRYPT);

        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':full_name', $this->full_name);
        $stmt->bindParam(':phone', $phone_val);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function emailExists() {
        $query = "SELECT id, password_hash, full_name, phone, profile_image, is_premium, is_verified FROM " . $this->table . " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->password_hash_db = $row['password_hash'];
            $this->full_name = $row['full_name'];
            $this->phone = $row['phone'];
            $this->profile_image = $row['profile_image'];
            $this->is_premium = $row['is_premium'];
            $this->is_verified = $row['is_verified'] ?? 0; // Default to 0 if column missing
            return true;
        }
        return false;
    }

    public function canResendOtp() {
        // limit 2 minutes (120 seconds)
        $query = "SELECT otp_created_at FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $createdAt = $row['otp_created_at'];
            
            if ($createdAt) {
                $createdTime = strtotime($createdAt);
                $timeDiff = time() - $createdTime;
                
                if ($timeDiff < 120) {
                     return 120 - $timeDiff; // Seconds remaining
                }
            }
        }
        return true; // Can resend
    }

    public function saveOtp($otp) {
        $query = "UPDATE " . $this->table . " SET otp_code = :otp, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE), otp_created_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':otp', $otp);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    public function verifyOtp($otp) {
        // MASTER OTP FOR DEV: 123456
        if ($otp === '123456') {
            $update = "UPDATE " . $this->table . " SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = :id";
            $ux = $this->conn->prepare($update);
            $ux->bindParam(':id', $this->id);
            $ux->execute();
            return true;
        }

        // Debugging: Log the attempt
        // Debugging logs removed
        // error_log("Verifying ID: " . $this->id);
        
        // 1. Get the real OTP from DB
        $query = "SELECT otp_code, otp_expires_at, NOW() as db_time, (otp_expires_at > NOW()) as is_valid_time FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            // error_log("User ID not found");
            return false;
        }
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbOtp = $row['otp_code'];
        $expiry = $row['otp_expires_at'];
        $dbTime = $row['db_time'];
        $isValidTime = $row['is_valid_time'];



        if ($dbOtp !== $otp) {

             return false;
        }

        if (!$isValidTime) {

             return false;
        }



        // Success, mark verified and clear OTP
        $update = "UPDATE " . $this->table . " SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = :id";
        $ux = $this->conn->prepare($update);
        $ux->bindParam(':id', $this->id);
        $ux->execute();
        return true;
    }
    
    // Create Session
    public function createSession($token, $device_id = null, $userAgent = null, $ip = null) {
        // Handle potentially null device_id (if table allows NULL, this is fine; if not, we need a default or fallback)
        // Assuming user_sessions.device_id allows NULL or 0.
        
        $query = "INSERT INTO user_sessions (user_id, device_id, access_token, user_agent, ip_address, expires_at) VALUES (:user_id, :device_id, :token, :ua, :ip, DATE_ADD(NOW(), INTERVAL 30 DAY))";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->id);
        $stmt->bindValue(':device_id', $device_id, is_null($device_id) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':ua', $userAgent);
        $stmt->bindParam(':ip', $ip);
        
        return $stmt->execute();
    }
    // Update unverified user details during re-registration
    public function updateUnverifiedUser($password, $full_name, $phone) {
        $query = "UPDATE " . $this->table . " SET password_hash = :pwd, full_name = :name, phone = :phone WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $phone_val = $phone ? $phone : '';

        $stmt->bindParam(':pwd', $password_hash);
        $stmt->bindParam(':name', $full_name);
        $stmt->bindParam(':phone', $phone_val);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }
    // Verify password for change password mostly
    public function verifyPassword($password) {
        $query = "SELECT password_hash FROM " . $this->table . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
             $row = $stmt->fetch(PDO::FETCH_ASSOC);
             return password_verify($password, $row['password_hash']);
        }
        return false;
    }

    public function updatePassword($newPassword) {
        $query = "UPDATE " . $this->table . " SET password_hash = :pwd, otp_code = NULL, otp_expires_at = NULL WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $password_hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $stmt->bindParam(':pwd', $password_hash);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>
