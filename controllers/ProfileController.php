<?php
// controllers/ProfileController.php
require_once __DIR__ . '/../models/User.php';

class ProfileController {
    private $user;
    private $db;
    
    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
    }
    
    public function getProfile() {
        // Get device count
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM devices WHERE user_id = :uid");
        $stmt->bindParam(':uid', $this->user['id']);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $this->user['device_count'] = (int)$count;

        // Return safe user info
        unset($this->user['password_hash']);
        Response::success($this->user);
    }
    
    public function updateProfile() {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        $error = Validator::validate($data, [
            'full_name' => 'required'
        ]);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        $fullName = $data['full_name'];
        $phone = $data['phone'] ?? ''; // Default to empty string
        
        $imagePath = null;
        if (!empty($data['profile_image'])) {
             // Handle Base64 Image
             $img = $data['profile_image'];
             $img = str_replace('data:image/png;base64,', '', $img);
             $img = str_replace('data:image/jpeg;base64,', '', $img);
             $img = str_replace('data:image/jpg;base64,', '', $img);
             $img = str_replace(' ', '+', $img);
             $dataImg = base64_decode($img);
             
             if ($dataImg !== false) {
                 $uploadDir = __DIR__ . '/../public/uploads/profiles/';
                 if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                 
                 $fileName = 'profile_' . $this->user['id'] . '_' . time() . '.png';
                 file_put_contents($uploadDir . $fileName, $dataImg);
                 
                 // Store URL path relative to public/ or full URL
                 // Assuming accessing via http://IP/vigilant_backend/public/uploads/profiles/
                 $imagePath = 'uploads/profiles/' . $fileName;
             }
        }

        $query = "UPDATE users SET full_name = :name, phone = :phone";
        if ($imagePath) $query .= ", profile_image = :img";
        $query .= " WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $fullName);
        $stmt->bindParam(':phone', $phone);
        if ($imagePath) $stmt->bindParam(':img', $imagePath);
        $stmt->bindParam(':id', $this->user['id']);
        
        if ($stmt->execute()) {
             Response::success(["message" => "Profile updated"]);
        } else {
             Response::error("DB_ERROR", "Failed to update profile");
        }
    }
    public function submitFeedback() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['feedback']) || empty(trim($data['feedback']))) {
            Response::error("VALIDATION_ERROR", "Feedback content is required");
        }
        
        $feedback = trim($data['feedback']);
        $userEmail = $this->user['email'];
        $userName = $this->user['full_name'];
        
        // Log feedback
        $logEntry = "[" . date('Y-m-d H:i:s') . "] Feedback from $userEmail ($userName): $feedback\n";
        file_put_contents(__DIR__ . '/../logs/feedback_log.txt', $logEntry, FILE_APPEND);
        
        // Send Email - Async simulation (Try/Catch and ignore failure)
        try {
            require_once __DIR__ . '/../utils/EmailSender.php';
            $subject = "New Feedback from Vigilant App";
            $body = "User: $userName ($userEmail)<br><br>Feedback:<br>$feedback";

            if (EmailSender::send("vigilantappdetection@gmail.com", "Admin", $subject, $body)) {
                 Response::success(["message" => "Feedback received successfully"]);
            } else {
                 error_log("Feedback Email Failed: SMTPClient returned false");
                 Response::error("EMAIL_FAILED", "Failed to send email. Please try again later.", 500);
            }
        } catch (Throwable $e) {
            error_log("Feedback Email Failed: " . $e->getMessage());
            Response::error("EMAIL_FAILED", "Error sending feedback: " . $e->getMessage(), 500);
        }
    }
}
?>
