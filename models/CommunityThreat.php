<?php
// models/CommunityThreat.php

class CommunityThreat {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getThreats($limit = 50) {
        $query = "SELECT id, app_name, package_name, category, risk_level, 
                         reporter_count as report_count, 
                         created_at as first_seen_at, 
                         updated_at as last_reported_at, 
                         behaviors, description 
                  FROM community_threats 
                  ORDER BY updated_at DESC LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getThreatById($id) {
        $query = "SELECT * FROM community_threats WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function submitReport($data) {
        try {
            // 1. Insert into individual reports table
            $query = "INSERT INTO community_reports (user_id, device_id, app_name, package_name, category, description, additional_details, consent_anonymous, consent_data_usage)
                      VALUES (:uid, :did, :name, :pkg, :cat, :desc, :details, :ca, :cdu)";
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':uid', $data['user_id']);
            $stmt->bindParam(':did', $data['device_id']);
            $stmt->bindParam(':name', $data['app_name']);
            $stmt->bindParam(':pkg', $data['package_name']);
            $stmt->bindParam(':cat', $data['category']);
            $stmt->bindParam(':desc', $data['description']);
            $stmt->bindParam(':details', $data['additional_details']);
            $stmt->bindParam(':ca', $data['consent_anonymous']);
            $stmt->bindParam(':cdu', $data['consent_data_usage']);
            
            $success = $stmt->execute();
            
            if ($success) {
                $this->updateAggregatedThreat($data);
            } else {
                error_log("Report Insert Failed: " . implode(" ", $stmt->errorInfo()));
            }
            
            return $success;
        } catch (PDOException $e) {
            error_log("Report Exception: " . $e->getMessage());
            return false;
        }
    }

    private function updateAggregatedThreat($data) {
        try {
            $pkg = $data['package_name'];
            $check = $this->conn->prepare("SELECT id, reporter_count FROM community_threats WHERE package_name = :pkg LIMIT 1");
            $check->execute([':pkg' => $pkg]);
            
            if ($check->rowCount() > 0) {
                // Update
                $update = "UPDATE community_threats 
                           SET reporter_count = reporter_count + 1, updated_at = NOW() 
                           WHERE package_name = :pkg";
                $upStmt = $this->conn->prepare($update);
                $upStmt->execute([':pkg' => $pkg]);
            } else {
                // Create New
                $risk = 'MEDIUM';
                if ($data['category'] == 'SPYWARE' || $data['category'] == 'STALKERWARE') $risk = 'CRITICAL';
                if ($data['category'] == 'DATA_THEFT') $risk = 'HIGH';
                
                $behaviors = json_encode([ucfirst(strtolower(str_replace('_', ' ', $data['category'])))]);

                $insert = "INSERT INTO community_threats (app_name, package_name, category, risk_level, reporter_count, created_at, updated_at, description, behaviors)
                           VALUES (:name, :pkg, :cat, :risk, 1, NOW(), NOW(), :desc, :behav)";
                $inStmt = $this->conn->prepare($insert);
                if (!$inStmt->execute([
                    ':name' => $data['app_name'],
                    ':pkg' => $pkg,
                    ':cat' => $data['category'],
                    ':risk' => $risk,
                    ':desc' => $data['description'],
                    ':behav' => $behaviors
                ])) {
                    error_log("Aggregated Threat Insert Failed: " . implode(" ", $inStmt->errorInfo()));
                }
            }
        } catch (Exception $e) {
            error_log("Aggregated Threat Error: " . $e->getMessage());
        }
    }
    
    public function getUserReports($userId) {
        // Alias category -> report_type
        // Ensure status exists (if not in DB, coalesce/hardcode)
        // Assuming DB has 'status' column, if not we add IFNULL or static
        // Let's assume 'status' column exists, defaulting to 'Pending'
        $query = "SELECT id, app_name, package_name, category as report_type, description, COALESCE(status, 'UNDER_REVIEW') as status, created_at FROM community_reports WHERE user_id = :uid ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
