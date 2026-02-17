<?php
// models/Settings.php

class Settings {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getScanSettings($userId, $deviceId = null) {
        $query = "SELECT * FROM scan_settings 
                  WHERE user_id = :uid";
        $params = [':uid' => $userId];
        
        if ($deviceId) {
            $query .= " AND device_id = :did";
            $params[':did'] = $deviceId;
        } else {
            $query .= " AND device_id IS NULL";
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if not found
        if (!$settings) {
            return [
                'auto_scan' => 0,
                'scan_frequency' => 'DAILY',
                'detection_level' => 'MEDIUM',
                'deep_scan' => 0
            ];
        }
        
        return $settings;
    }

    public function updateScanSettings($userId, $deviceId, $data) {
        // Check if exists
        $query = "SELECT id FROM scan_settings 
                  WHERE user_id = :uid AND device_id " . ($deviceId ? "= :did" : "IS NULL");
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        if ($deviceId) {
            $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update
            $query = "UPDATE scan_settings 
                      SET auto_scan = :auto, scan_frequency = :freq, detection_level = :level, deep_scan = :deep
                      WHERE user_id = :uid AND device_id " . ($deviceId ? "= :did" : "IS NULL");
        } else {
            // Insert
            $query = "INSERT INTO scan_settings 
                      (user_id, device_id, auto_scan, scan_frequency, detection_level, deep_scan)
                      VALUES (:uid, :did, :auto, :freq, :level, :deep)";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        if (strpos($query, ':did') !== false) {
             $stmt->bindParam(':did', $deviceId);
        }
        $stmt->bindParam(':auto', $data['auto_scan'], PDO::PARAM_INT);
        $stmt->bindParam(':freq', $data['scan_frequency']);
        $stmt->bindParam(':level', $data['detection_level']);
        $stmt->bindParam(':deep', $data['deep_scan'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    public function getAlertRules($userId, $deviceId = null) {
        $query = "SELECT * FROM alert_rules 
                  WHERE user_id = :uid";
        $params = [':uid' => $userId];
        
        if ($deviceId) {
            $query .= " AND device_id = :did";
            $params[':did'] = $deviceId;
        } else {
            $query .= " AND device_id IS NULL";
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $rules = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if not found
        if (!$rules) {
            return [
                'notify_critical' => 1,
                'notify_suspicious' => 1,
                'notify_permissions' => 1,
                'notify_community' => 1,
                'quiet_hours_enabled' => 0,
                'quiet_start_time' => null,
                'quiet_end_time' => null
            ];
        }
        
        return $rules;
    }

    public function updateAlertRules($userId, $deviceId, $data) {
        // Check if exists
        $query = "SELECT id FROM alert_rules 
                  WHERE user_id = :uid AND device_id " . ($deviceId ? "= :did" : "IS NULL");
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        if ($deviceId) {
            $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update
            $query = "UPDATE alert_rules 
                      SET notify_critical = :nc, notify_suspicious = :ns, notify_permissions = :np,
                          notify_community = :ncom, quiet_hours_enabled = :qh, quiet_start_time = :qs, quiet_end_time = :qe
                      WHERE user_id = :uid AND device_id " . ($deviceId ? "= :did" : "IS NULL");
        } else {
            // Insert
            $query = "INSERT INTO alert_rules 
                      (user_id, device_id, notify_critical, notify_suspicious, notify_permissions,
                       notify_community, quiet_hours_enabled, quiet_start_time, quiet_end_time)
                      VALUES (:uid, :did, :nc, :ns, :np, :ncom, :qh, :qs, :qe)";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        if (strpos($query, ':did') !== false) {
             $stmt->bindParam(':did', $deviceId);
        }
        $stmt->bindParam(':nc', $data['notify_critical'], PDO::PARAM_INT);
        $stmt->bindParam(':ns', $data['notify_suspicious'], PDO::PARAM_INT);
        $stmt->bindParam(':np', $data['notify_permissions'], PDO::PARAM_INT);
        $stmt->bindParam(':ncom', $data['notify_community'], PDO::PARAM_INT);
        $stmt->bindParam(':qh', $data['quiet_hours_enabled'], PDO::PARAM_INT);
        $stmt->bindParam(':qs', $data['quiet_start_time']);
        $stmt->bindParam(':qe', $data['quiet_end_time']);
        
        return $stmt->execute();
    }

    public function getPrivacySettings($userId) {
        $query = "SELECT * FROM privacy_settings WHERE user_id = :uid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if not found
        if (!$settings) {
            return [
                'share_usage_stats' => 0,
                'share_crash_reports' => 0
            ];
        }
        
        return $settings;
    }

    public function updatePrivacySettings($userId, $data) {
        // Check if exists
        $query = "SELECT id FROM privacy_settings WHERE user_id = :uid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update
            $query = "UPDATE privacy_settings 
                      SET share_usage_stats = :stats, share_crash_reports = :crash
                      WHERE user_id = :uid";
        } else {
            // Insert
            $query = "INSERT INTO privacy_settings (user_id, share_usage_stats, share_crash_reports)
                      VALUES (:uid, :stats, :crash)";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':stats', $data['share_usage_stats'], PDO::PARAM_INT);
        $stmt->bindParam(':crash', $data['share_crash_reports'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }
}
?>
