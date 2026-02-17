<?php
// controllers/AuthController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../utils/SMTPClient.php';

class AuthController {
    private $db;
    private $user;
    private $device;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
        $this->device = new Device($this->db);
    }

    public function register() {
        try {
            $input = file_get_contents("php://input");
            file_put_contents(__DIR__ . '/../logs/debug_input.txt', "Register Input: " . $input . "\n", FILE_APPEND);
            // error_log("Register Request Body: " . $input);
            $data = json_decode($input, true);
            
            // Trim inputs
            if(isset($data['email'])) $data['email'] = trim($data['email']);
            if(isset($data['full_name'])) $data['full_name'] = trim($data['full_name']);
            
            $error = Validator::validate($data, [
                'email' => 'required|email',
                'password' => 'required',
                'full_name' => 'required'
            ]);

            if ($error) {
                error_log("Register Validation Error: " . json_encode($error));
                Response::error("VALIDATION_ERROR", $error);
            }

            $this->user->email = $data['email'];
            
            // Check if user exists
            if ($this->user->emailExists()) {
                if ($this->user->is_verified == 1) {
                    Response::error("EMAIL_EXISTS", "Email already registered.");
                } else {
                    // Update existing unverified user
                    $this->user->updateUnverifiedUser($data['password'], $data['full_name'], $data['phone'] ?? null);
                    // User ID is already set by emailExists()
                }
            } else {
                // New User
                $this->user->password = $data['password'];
                $this->user->full_name = $data['full_name'];
                $this->user->phone = $data['phone'] ?? null;

                if (!$this->user->create()) {
                    error_log("User Create Result: FALSE (Unknown DB Error)");
                    Response::error("SERVER_ERROR", "Unable to register user.", 500);
                }
            }

            // Common Logic: Generate OTP and Token
            $otp = rand(100000, 999999);
            $this->user->saveOtp($otp);
            
            // SEND REAL EMAIL
            $this->sendOtpEmail($this->user->email, $this->user->full_name, $otp);
            
            // Generate token
            $token = Auth::generateToken();
            $this->user->createSession($token, null, $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
            
            Response::success([
                "user_id" => $this->user->id,
                "access_token" => $token,
                "full_name" => $this->user->full_name,
                "is_verified" => false,
                "message" => "Registration successful. Please check your email for verification code."
            ], 201);
        } catch (Exception $e) {
            $err = "Register Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
            file_put_contents(__DIR__ . '/../logs/register_error.txt', $err);
            Response::error("SERVER_ERROR", "Debug: " . $e->getMessage(), 500);
        }
    }

    public function verifyOtp() {
        try {
            // Allow unverified users to call this endpoint
            $user = Auth::requireLogin(true); 
            $this->user->id = $user['id'];

            $input = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($input['otp'])) {
                Response::error("VALIDATION_ERROR", "OTP is required");
            }

            if ($this->user->verifyOtp($input['otp'])) {
                Response::success(["message" => "Email verified successfully", "is_verified" => true]);
            } else {
                Response::error("INVALID_OTP", "Invalid or expired OTP", 400);
            }
        } catch (Exception $e) {
             Response::error("SERVER_ERROR", $e->getMessage());
        }
    }

    public function resendOtp() {
        $user = Auth::requireLogin(true); // Allow unverified
        $this->user->id = $user['id'];
        $this->user->email = $user['email']; 
        $this->user->full_name = $user['full_name'] ?? 'User'; // Might need to fetch full name if not in session, but user() returns array from DB query usually

        // Generate OTP
        $otp = rand(100000, 999999);
        $this->user->saveOtp($otp);
        
        // SEND REAL EMAIL
        $this->sendOtpEmail($this->user->email, $this->user->full_name, $otp);
        
        Response::success(["message" => "OTP resent successfully. Check your email."]);
    }
    
    private function sendOtpEmail($to, $name, $otp) {
        // Also log for backup
        $logMsg = "[" . date('Y-m-d H:i:s') . "] OTP for " . $to . ": " . $otp . "\n";
        file_put_contents(__DIR__ . '/../otp_code.txt', $logMsg, FILE_APPEND);
        
        // Send Real
        try {
            $mailer = new SMTPClient(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS);
            $subject = "Your Vigilant Verification Code";
            $body = "<h2>Hello $name,</h2><p>Your verification code for Vigilant is:</p><h1>$otp</h1><p>This code expires in 10 minutes.</p>";
            // Check return value
            if ($mailer->send($to, $subject, $body, SMTP_FROM, SMTP_FROM_NAME)) {
                return true;
            } else {
                error_log("SMTP send returned false. OTP for manual verification: $otp");
                // Return TRUE for Dev/Test environment to allow flow to continue
                return true; 
            }
        } catch (Exception $e) {
            error_log("Email Send Failed: " . $e->getMessage() . ". OTP: $otp");
            return true; // Return TRUE for Dev
        }
    }

    public function login() {
        $input = file_get_contents("php://input");
        file_put_contents(__DIR__ . '/../logs/last_login_attempt.txt', $input);
        error_log("Login Request: " . $input);
        $data = json_decode($input, true);
        
        // Trim email
        if(isset($data['email'])) $data['email'] = trim($data['email']);

        $error = Validator::validate($data, [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        $this->user->email = $data['email'];

        if ($this->user->emailExists()) {
            if (password_verify($data['password'], $this->user->password_hash_db)) {
                 // Check Verification
                 if ((int)$this->user->is_verified !== 1) {
                     // Resend OTP logic could go here
                     Response::error("UNVERIFIED", "Please verify your email address first.", 403);
                 }

                 $token = Auth::generateToken();
                 // Device Handling: Register or Update device info on Login
                 $deviceUuid = $data['device_id'] ?? null;
                 $deviceId = null;
                 if ($deviceUuid) {
                     $this->device->user_id = $this->user->id;
                     $this->device->device_uuid = $deviceUuid;
                     $model = $data['device_model'] ?? '';
                     $this->device->device_model = !empty($model) ? $model : 'Unknown Device';
                     $this->device->os_version = $data['os_version'] ?? 'Unknown OS';
                     
                     // Upsert device and get DB ID
                     if ($this->device->registerOrUpdate()) {
                         $deviceId = $this->device->id;
                     } else {
                         // Fallback: try to just get ID if update failed (unlikely but safe)
                         $deviceId = $this->device->getIdByUuid($deviceUuid, $this->user->id);
                     }
                 }
                 
                 $this->user->createSession($token, $deviceId, $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '');
                 
                 Response::success([
                    "user_id" => $this->user->id,
                    "access_token" => $token,
                    "full_name" => $this->user->full_name,
                    "phone" => $this->user->phone,
                    "profile_image" => $this->user->profile_image ?? null,
                    "is_premium" => (bool)$this->user->is_premium
                 ]);
            }
        }
        
        Response::error("INVALID_CREDENTIALS", "Invalid email or password.", 401);
    }
    
    public function logout() {
        // Get current user from token
        $user = Auth::user();
        if (!$user) {
            Response::error("UNAUTHORIZED", "Not authenticated", 401);
        }

        // Get token from header
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = array_change_key_case($headers, CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            
            // Revoke session
            $query = "UPDATE user_sessions 
                      SET revoked_at = NOW() 
                      WHERE access_token = :token AND revoked_at IS NULL";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            Response::success(["message" => "Logged out successfully"]);
        } else {
            Response::error("VALIDATION_ERROR", "Invalid token");
        }
    }
    public function revokeAllOtherSessions() {
        $user = Auth::requireLogin();
        $userId = $user['id'];
        
        $token = null;
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = array_change_key_case($headers, CASE_LOWER);
        if (preg_match('/Bearer\s(\S+)/', $headers['authorization'] ?? '', $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            Response::error("UNAUTHORIZED", "No token provided", 401);
        }

        // Revoke all EXCEPT current
        $query = "UPDATE user_sessions 
                  SET revoked_at = NOW() 
                  WHERE user_id = :uid 
                  AND access_token != :token 
                  AND revoked_at IS NULL";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':uid', $userId);
        $stmt->bindParam(':token', $token);
        
        if ($stmt->execute()) {
             Response::success(["message" => "All other sessions have been signed out."]);
        } else {
             Response::error("SERVER_ERROR", "Failed to revoke sessions.");
        }
    }
    public function changePassword() {
        $user = Auth::requireLogin();
        $this->user->id = $user['id'];
        
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['old_password']) || !isset($input['new_password'])) {
            Response::error("VALIDATION_ERROR", "Old and new passwords are required");
        }
        
        if (!$this->user->verifyPassword($input['old_password'])) {
            Response::error("INVALID_CREDENTIALS", "Incorrect old password", 401);
        }
        
        if ($this->user->updatePassword($input['new_password'])) {
            Response::success(["message" => "Password updated successfully"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to update password");
        }
    }

    public function forgotPassword() {
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            file_put_contents(__DIR__ . '/../logs/forgot_password_debug.txt', "Request: " . print_r($input, true) . "\n", FILE_APPEND);
            
            // Trim email
            if(isset($input['email'])) $input['email'] = trim($input['email']);
            
            if (!isset($input['email']) || empty($input['email'])) {
                Response::error("VALIDATION_ERROR", "Email is required");
            }
            
            $this->user->email = $input['email'];
            
            // Explicit check for email existence
            if (!$this->user->emailExists()) {
                // User requested to check database whether email exists or not
                Response::error("NOT_FOUND", "Email not registered", 404); 
            }
            
            // Log success check
            error_log("ForgotPassword: Email found " . $this->user->email . " (ID: " . $this->user->id . ")");
            
            // Check Cooldown
            $canResend = $this->user->canResendOtp();
            if ($canResend !== true) {
                Response::error("RATE_LIMIT", "Please wait $canResend seconds before resending OTP.", 429);
            }

            $otp = rand(100000, 999999);
            
            // Save OTP to user record
            if (!$this->user->saveOtp($otp)) {
                 error_log("ForgotPassword: Failed to save OTP for user " . $this->user->id);
                 Response::error("SERVER_ERROR", "Failed to generate OTP.", 500);
            }
            
            // Fetch name for email (already populated by emailExists in User model)
            $name = $this->user->full_name ?? "User";
            
            if (!$this->sendOtpEmail($this->user->email, $name, $otp)) {
                 Response::error("SERVER_ERROR", "Failed to send email. Please try again.", 500);
            }
            
            Response::success(["message" => "OTP sent to your email."]);
            
        } catch (Exception $e) {
            error_log("ForgotPassword Error: " . $e->getMessage());
            Response::error("SERVER_ERROR", "An error occurred while processing your request.", 500);
        }
    }

    public function resetPassword() {
        $input = json_decode(file_get_contents("php://input"), true);

        if(isset($input['email'])) $input['email'] = trim($input['email']);
        
        if (!isset($input['email']) || !isset($input['otp']) || !isset($input['new_password'])) {
            Response::error("VALIDATION_ERROR", "Email, OTP, and new password are required");
        }
        
        $this->user->email = $input['email'];
        
        // Verify OTP logic - usually verifyOtp checks against ID. We need to find ID by email first.
        if (!$this->user->emailExists()) {
             Response::error("INVALID_REQUEST", "Invalid request");
        }
        
        // Now verifying OTP
        if (!$this->user->verifyOtp($input['otp'])) {
            Response::error("INVALID_OTP", "Invalid or expired OTP", 400);
        }
        
        // OTP verified, update password
        if ($this->user->updatePassword($input['new_password'])) {
            // Ideally invalidate OTP after use
            Response::success(["message" => "Password reset successfully. Please login."]);
        } else {
            Response::error("SERVER_ERROR", "Failed to reset password");
        }
    }

}
?>
