<?php
// models/AppScan.php
// Compatible with 'complete_schema.sql' (app_scans, app_scan_results, installed_apps)

class AppScan {
    private $conn;
    private $table = 'app_scans';

    public $id;
    public $device_id;
    public $user_id;
    public $mode; // 'quick','deep'
    
    public $overall_risk_score;
    public $overall_risk_level;
    public $app_count;
    public $apps_scanned; // apps_scanned
    public $high_risk_count;
    public $medium_risk_count;
    public $safe_count;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function start() {
        $query = "INSERT INTO " . $this->table . " 
                  (device_id, user_id, mode, status, overall_risk_score, overall_risk_level, apps_scanned, high_risk_count, medium_risk_count, safe_count, started_at) 
                  VALUES (:did, :uid, :mode, 'RUNNING', 0, 'SAFE', 0, 0, 0, 0, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        // Map mode
        $validModes = ['quick', 'deep'];
        $mode = strtolower($this->mode);
        if (!in_array($mode, $validModes)) $mode = 'quick';

        $stmt->bindParam(':did', $this->device_id);
        $stmt->bindParam(':uid', $this->user_id);
        $stmt->bindParam(':mode', $mode);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    
    public function complete() {
        $query = "UPDATE " . $this->table . " 
                  SET overall_risk_score = :score, 
                      overall_risk_level = :level, 
                      app_count = :count, 
                      apps_scanned = :scanned,
                      high_risk_count = :high,
                      medium_risk_count = :med,
                      safe_count = :safe,
                      completed_at = NOW(), 
                      status = 'COMPLETED'
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':score', $this->overall_risk_score);
        $stmt->bindParam(':level', $this->overall_risk_level);
        $stmt->bindParam(':count', $this->app_count);
        $stmt->bindParam(':scanned', $this->apps_scanned);
        $stmt->bindParam(':high', $this->high_risk_count);
        $stmt->bindParam(':med', $this->medium_risk_count);
        $stmt->bindParam(':safe', $this->safe_count);
        $stmt->bindParam(':id', $this->id);
        
        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Helper to get or create installed app record
    public function getOrCreateInstalledApp($packageName, $appName, $versionName, $isSystem) {
        // Check if exists
        $query = "SELECT id FROM installed_apps WHERE device_id = :did AND package_name = :pkg LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $this->device_id);
        $stmt->bindParam(':pkg', $packageName);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $id = $row['id'];
            // Update last seen
            $upd = "UPDATE installed_apps SET last_seen_at = NOW(), app_name = :name, version_name = :ver, is_system_app = :sys WHERE id = :id";
            $ustmt = $this->conn->prepare($upd);
            $ustmt->bindParam(':name', $appName);
            $ustmt->bindParam(':ver', $versionName);
            $ustmt->bindParam(':sys', $isSystem, PDO::PARAM_INT);
            $ustmt->bindParam(':id', $id);
            $ustmt->execute();
            return $id;
        } else {
            // Create
            $ins = "INSERT INTO installed_apps (device_id, package_name, app_name, version_name, is_system_app, first_seen_at, last_seen_at)
                    VALUES (:did, :pkg, :name, :ver, :sys, NOW(), NOW())";
            $istmt = $this->conn->prepare($ins);
            $istmt->bindParam(':did', $this->device_id);
            $istmt->bindParam(':pkg', $packageName);
            $istmt->bindParam(':name', $appName);
            $istmt->bindParam(':ver', $versionName);
            $istmt->bindParam(':sys', $isSystem, PDO::PARAM_INT);
            $istmt->execute();
            return $this->conn->lastInsertId();
        }
    }
    
    // Save indvidual app result
    public function saveAppResult($installedAppId, $riskScore, $riskLevel, $topFactorDesc, $riskFactors = []) {
        $query = "INSERT INTO app_scan_results (scan_id, installed_app_id, risk_score, risk_level, top_factor_desc)
                  VALUES (:sid, :aid, :score, :level, :top)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sid', $this->id);
        $stmt->bindParam(':aid', $installedAppId);
        $stmt->bindParam(':score', $riskScore);
        $stmt->bindParam(':level', $riskLevel);
        $stmt->bindParam(':top', $topFactorDesc);
        
        if ($stmt->execute()) {
            $resultId = $this->conn->lastInsertId();
            
            // Insert risk factors if any
            if (!empty($riskFactors) && is_array($riskFactors)) {
                $fQuery = "INSERT INTO risk_factors (app_scan_result_id, description, score, factor_type) VALUES (:rid, :desc, :score, :type)";
                $fStmt = $this->conn->prepare($fQuery);
                
                foreach ($riskFactors as $factor) {
                    // Factor can be string or array
                    $desc = is_string($factor) ? $factor : ($factor['description'] ?? 'Unknown');
                    $score = is_array($factor) ? ($factor['score'] ?? 0) : 0;
                    $type = is_array($factor) ? ($factor['type'] ?? 'BEHAVIOR') : 'BEHAVIOR';
                    
                    $fStmt->bindParam(':rid', $resultId);
                    $fStmt->bindParam(':desc', $desc);
                    $fStmt->bindParam(':score', $score);
                    $fStmt->bindParam(':type', $type);
                    $fStmt->execute();
                }
            }
            return $resultId;
        }
        return false;
    }
    
    public function getLatest($deviceId) {
        $query = "SELECT overall_risk_score as risk_score, overall_risk_level as risk_level, completed_at, high_risk_count, medium_risk_count 
                  FROM " . $this->table . " 
                  WHERE device_id = :did AND status = 'COMPLETED' 
                  ORDER BY completed_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStatus($scanId, $userId) {
        $query = "SELECT id, mode, status, apps_scanned, app_count, overall_risk_score, overall_risk_level, started_at, completed_at, high_risk_count, medium_risk_count 
                  FROM " . $this->table . " 
                  WHERE id = :id AND user_id = :uid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $scanId);
        $stmt->bindParam(':uid', $userId);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        // Map to expected output
        $row['apps_scanned_count'] = $row['apps_scanned']; // Alias for compatibility
        return $row;
    }
    
    public function updateProgress($scanId, $appsScanned, $totalApps = null) {
        $sql = "UPDATE " . $this->table . " SET apps_scanned = :scanned";
        if ($totalApps) $sql .= ", app_count = :total";
        $sql .= " WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':scanned', $appsScanned);
        if ($totalApps) $stmt->bindValue(':total', $totalApps);
        $stmt->bindValue(':id', $scanId);
        return $stmt->execute();
    }

    // Batch save app results for performance
    public function saveAppResultsBatch($results) {
        if (empty($results)) return true;

        // Prepare statements once
        $query = "INSERT INTO app_scan_results (scan_id, installed_app_id, risk_score, risk_level, top_factor_desc)
                  VALUES (:sid, :aid, :score, :level, :top)";
        $stmt = $this->conn->prepare($query);

        $fQuery = "INSERT INTO risk_factors (app_scan_result_id, description, score, factor_type) VALUES (:rid, :desc, :score, :type)";
        $fStmt = $this->conn->prepare($fQuery);

        foreach ($results as $result) {
            $stmt->bindParam(':sid', $this->id);
            $stmt->bindParam(':aid', $result['installed_app_id']);
            $stmt->bindParam(':score', $result['risk_score']);
            $stmt->bindParam(':level', $result['risk_level']);
            $stmt->bindParam(':top', $result['top_factor_desc']);
            
            if ($stmt->execute()) {
                $resultId = $this->conn->lastInsertId();
                
                // Insert risk factors
                if (!empty($result['risk_factors']) && is_array($result['risk_factors'])) {
                     foreach ($result['risk_factors'] as $factor) {
                        $desc = is_string($factor) ? $factor : ($factor['description'] ?? 'Unknown');
                        $score = is_array($factor) ? ($factor['score'] ?? 0) : 0;
                        $type = is_array($factor) ? ($factor['type'] ?? 'BEHAVIOR') : 'BEHAVIOR';
                        
                        $fStmt->bindParam(':rid', $resultId);
                        $fStmt->bindParam(':desc', $desc);
                        $fStmt->bindParam(':score', $score);
                        $fStmt->bindParam(':type', $type);
                        $fStmt->execute();
                     }
                }
            } else {
                return false;
            }
        }
        return true;
    }

    // New Method: Update device risk score
    public function updateDeviceRiskScore($deviceId, $score, $level, $scanId) {
        // Upsert device_risk_scores
        $query = "INSERT INTO device_risk_scores (device_id, last_score, last_level, last_scan_id, last_updated_at)
                  VALUES (:did, :score, :level, :sid, NOW())
                  ON DUPLICATE KEY UPDATE 
                    last_score = :score_upd, 
                    last_level = :level_upd, 
                    last_scan_id = :sid_upd, 
                    last_updated_at = NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId);
        $stmt->bindParam(':score', $score);
        $stmt->bindParam(':level', $level);
        $stmt->bindParam(':sid', $scanId);
        $stmt->bindParam(':score_upd', $score);
        $stmt->bindParam(':level_upd', $level);
        $stmt->bindParam(':sid_upd', $scanId);
        
        return $stmt->execute();
    }
}
?>
