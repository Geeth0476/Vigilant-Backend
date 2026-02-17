<?php
// models/WeeklyReport.php

class WeeklyReport {
    private $conn;
    private $table = 'weekly_reports';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getWeekly($userId, $deviceId = null, $weekStart = null) {
        if (!$weekStart) {
            // Default to current week
            $weekStart = date('Y-m-d', strtotime('monday this week'));
        }
        
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :uid AND week_start = :week";
        $params = [':uid' => $userId, ':week' => $weekStart];
        
        if ($deviceId) {
            $query .= " AND device_id = :did";
            $params[':did'] = $deviceId;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getHistory($userId, $deviceId = null, $limit = 10) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = :uid";
        $params = [':uid' => $userId];
        
        if ($deviceId) {
            $query .= " AND device_id = :did";
            $params[':did'] = $deviceId;
        }
        
        $query .= " ORDER BY week_start DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generate($userId, $deviceId, $weekStart, $weekEnd) {
        // Check if report already exists
        $existing = $this->getWeekly($userId, $deviceId, $weekStart);
        if ($existing) {
            return $existing;
        }

        // Calculate statistics from scans and alerts
        $stats = $this->calculateStats($userId, $deviceId, $weekStart, $weekEnd);
        
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, device_id, week_start, week_end, threats_count, violations_count, 
                   alerts_count, top_offenders, risk_trend)
                  VALUES (:uid, :did, :start, :end, :threats, :violations, :alerts, :offenders, :trend)";
        $stmt = $this->conn->prepare($query);
        
        $topOffendersJson = json_encode($stats['top_offenders']);
        $riskTrendJson = json_encode($stats['risk_trend']);
        
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':did', $deviceId);
        $stmt->bindParam(':start', $weekStart);
        $stmt->bindParam(':end', $weekEnd);
        $stmt->bindParam(':threats', $stats['threats_count'], PDO::PARAM_INT);
        $stmt->bindParam(':violations', $stats['violations_count'], PDO::PARAM_INT);
        $stmt->bindParam(':alerts', $stats['alerts_count'], PDO::PARAM_INT);
        $stmt->bindParam(':offenders', $topOffendersJson);
        $stmt->bindParam(':trend', $riskTrendJson);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    private function calculateStats($userId, $deviceId, $weekStart, $weekEnd) {
        // Count threats (high/critical risk apps)
        $query = "SELECT COUNT(DISTINCT asr.installed_app_id) as threats
                  FROM app_scan_results asr
                  JOIN app_scans s ON s.id = asr.scan_id
                  WHERE s.user_id = :uid AND s.device_id = :did 
                    AND s.completed_at BETWEEN :start AND :end
                    AND asr.risk_level IN ('HIGH', 'CRITICAL')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->bindParam(':start', $weekStart);
        $stmt->bindParam(':end', $weekEnd);
        $stmt->execute();
        $threats = (int)$stmt->fetch(PDO::FETCH_ASSOC)['threats'];

        // Count violations (permission events)
        $query = "SELECT COUNT(*) as violations
                  FROM permission_events pe
                  JOIN installed_apps ia ON ia.id = pe.installed_app_id
                  WHERE ia.device_id = :did 
                    AND pe.created_at BETWEEN :start AND :end
                    AND pe.event_type = 'USED'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->bindParam(':start', $weekStart);
        $stmt->bindParam(':end', $weekEnd);
        $stmt->execute();
        $violations = (int)$stmt->fetch(PDO::FETCH_ASSOC)['violations'];

        // Count alerts
        $query = "SELECT COUNT(*) as alerts
                  FROM security_alerts
                  WHERE device_id = :did AND created_at BETWEEN :start AND :end";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->bindParam(':start', $weekStart);
        $stmt->bindParam(':end', $weekEnd);
        $stmt->execute();
        $alerts = (int)$stmt->fetch(PDO::FETCH_ASSOC)['alerts'];

        // Top offenders
        $query = "SELECT ia.package_name, ia.app_name, MAX(asr.risk_score) as max_score, COUNT(*) as events
                  FROM app_scan_results asr
                  JOIN app_scans s ON s.id = asr.scan_id
                  JOIN installed_apps ia ON ia.id = asr.installed_app_id
                  WHERE s.user_id = :uid AND s.device_id = :did 
                    AND s.completed_at BETWEEN :start AND :end
                    AND asr.risk_level IN ('HIGH', 'CRITICAL')
                  GROUP BY ia.id
                  ORDER BY max_score DESC, events DESC
                  LIMIT 5";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->bindParam(':start', $weekStart);
        $stmt->bindParam(':end', $weekEnd);
        $stmt->execute();
        $topOffenders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Risk trend (daily averages)
        $query = "SELECT DATE(s.completed_at) as date, AVG(s.overall_risk_score) as avg_score
                  FROM app_scans s
                  WHERE s.user_id = :uid AND s.device_id = :did 
                    AND s.completed_at BETWEEN :start AND :end
                    AND s.completed_at IS NOT NULL
                  GROUP BY DATE(s.completed_at)
                  ORDER BY date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->bindParam(':start', $weekStart);
        $stmt->bindParam(':end', $weekEnd);
        $stmt->execute();
        $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'threats_count' => $threats,
            'violations_count' => $violations,
            'alerts_count' => $alerts,
            'top_offenders' => $topOffenders,
            'risk_trend' => $trend
        ];
    }
}
?>
