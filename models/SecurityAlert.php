<?php
// models/SecurityAlert.php
// Re-implemented for V2 Schema (security_alerts with direct package_name)

class SecurityAlert {
    private $conn;
    private $table = 'security_alerts';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getRecent($deviceId, $limit = 10) {
        $query = "SELECT public_id as id, 'Security Alert' as type, title, short_desc as description, 
                         detailed_info, severity, is_acknowledged, created_at, 
                         acknowledged_at, package_name
                  FROM " . $this->table . "
                  WHERE device_id = :did
                  ORDER BY created_at DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add empty recommendations list to prevent Android null crash
        foreach ($alerts as &$alert) {
            $alert['recommendations'] = []; 
        }
        
        return $alerts;
    }

    public function getById($publicId, $userId) {
        $query = "SELECT sa.*
                  FROM " . $this->table . " sa
                  JOIN devices d ON d.id = sa.device_id
                  WHERE sa.public_id = :pid AND d.user_id = :uid
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pid', $publicId);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function acknowledge($publicId, $userId) {
        $query = "UPDATE " . $this->table . " sa
                  JOIN devices d ON d.id = sa.device_id
                  SET sa.acknowledged_at = NOW(), sa.is_acknowledged = 1
                  WHERE sa.public_id = :pid AND d.user_id = :uid AND sa.acknowledged_at IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pid', $publicId);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function create($data) {
        $publicId = $data['public_id'] ?? 'alert_' . bin2hex(random_bytes(16));
        
        // Use package_name instead of installed_app_id
        $query = "INSERT INTO " . $this->table . " 
                  (public_id, user_id, device_id, package_name, title, short_desc, detailed_info, severity)
                  VALUES (:pid, :uid, :did, :pkg, :title, :short, :detail, :severity)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pid', $publicId);
        $stmt->bindParam(':uid', $data['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':did', $data['device_id'], PDO::PARAM_INT);
        $stmt->bindParam(':pkg', $data['package_name']); // Expecting package_name in $data
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':short', $data['short_desc']);
        $stmt->bindParam(':detail', $data['detailed_info']);
        $stmt->bindParam(':severity', $data['severity']);
        
        if ($stmt->execute()) {
            return $publicId;
        }
        return false;
    }
}
?>
