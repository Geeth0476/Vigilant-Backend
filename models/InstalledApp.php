<?php
// models/InstalledApp.php

class InstalledApp {
    private $conn;
    private $table = 'installed_apps';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($deviceId) {
        // Join with latest scan results if available
        $query = "SELECT ia.id, ia.app_name, ia.package_name, ia.version_name, ia.is_system_app, ia.last_seen_at,
                         r.risk_score, r.risk_level, r.top_factor_desc
                  FROM " . $this->table . " ia
                  LEFT JOIN (
                      SELECT installed_app_id, risk_score, risk_level, top_factor_desc
                      FROM app_scan_results 
                      WHERE scan_id = (SELECT last_scan_id FROM device_risk_scores WHERE device_id = :did LIMIT 1)
                  ) r ON ia.id = r.installed_app_id
                  WHERE ia.device_id = :did
                  ORDER BY r.risk_score DESC, ia.app_name ASC";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetails($deviceId, $packageName) {
        $query = "SELECT ia.*, r.risk_score, r.risk_level, r.top_factor_desc
                  FROM " . $this->table . " ia
                  LEFT JOIN (
                      SELECT installed_app_id, risk_score, risk_level, top_factor_desc
                      FROM app_scan_results 
                      WHERE scan_id = (SELECT last_scan_id FROM device_risk_scores WHERE device_id = :did LIMIT 1)
                  ) r ON ia.id = r.installed_app_id
                  WHERE ia.device_id = :did AND ia.package_name = :pkg";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId);
        $stmt->bindParam(':pkg', $packageName);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
