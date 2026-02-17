<?php
// core/risk_engine.php
// Backend risk aggregation helpers

class RiskEngine {
    /**
     * Aggregate risk scores from multiple sources
     * Combines: app scan results, community threats, permission events
     */
    public static function aggregateDeviceRisk($db, $deviceId, $currentScanScore, $currentScanLevel) {
        // Base score is the current scan's finding
        $baseScore = $currentScanScore;

        // Get community threat matches
        $query = "SELECT COUNT(*) as threat_count
                  FROM installed_apps ia
                  JOIN community_threats ct ON ct.package_name = ia.package_name
                  WHERE ia.device_id = :did AND ct.risk_level IN ('HIGH', 'CRITICAL')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->execute();
        $communityThreats = (int)$stmt->fetch(PDO::FETCH_ASSOC)['threat_count'];

        // Get recent permission violations
        $query = "SELECT COUNT(*) as violation_count
                  FROM permission_events pe
                  JOIN installed_apps ia ON ia.id = pe.installed_app_id
                  WHERE ia.device_id = :did 
                    AND pe.event_type = 'USED'
                    AND pe.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':did', $deviceId, PDO::PARAM_INT);
        $stmt->execute();
        $violations = (int)$stmt->fetch(PDO::FETCH_ASSOC)['violation_count'];

        // Calculate aggregated score (weighted)
        // Add penalties to the base score found by the on-device scanner
        $communityPenalty = min(20, $communityThreats * 10); // Max +20 
        $violationPenalty = min(15, $violations * 2); // Max +15
        
        $aggregatedScore = min(100, $baseScore + $communityPenalty + $violationPenalty);
        $aggregatedLevel = self::scoreToLevel($aggregatedScore);

        return [
            'base_score' => $baseScore,
            'base_level' => $currentScanLevel,
            'community_threats' => $communityThreats,
            'violations' => $violations,
            'aggregated_score' => $aggregatedScore,
            'aggregated_level' => $aggregatedLevel
        ];
    }

    /**
     * Convert risk score to level
     */
    public static function scoreToLevel($score) {
        if ($score >= 90) return 'CRITICAL';
        if ($score >= 70) return 'HIGH';
        if ($score >= 40) return 'MEDIUM';
        if ($score >= 15) return 'LOW';
        return 'SAFE';
    }

    /**
     * Check if app should trigger security alert
     */
    public static function shouldAlert($riskScore, $riskLevel, $alertRules) {
        if ($riskLevel === 'CRITICAL' && ($alertRules['notify_critical'] ?? 1)) {
            return true;
        }
        if ($riskLevel === 'HIGH' && ($alertRules['notify_suspicious'] ?? 1)) {
            return true;
        }
        return false;
    }

    /**
     * Generate alert recommendations based on risk factors
     */
    public static function generateRecommendations($riskLevel, $riskFactors) {
        $recommendations = [];
        
        if ($riskLevel === 'CRITICAL' || $riskLevel === 'HIGH') {
            $recommendations[] = "Uninstall this app immediately";
            $recommendations[] = "Review all permissions granted to this app";
        }
        
        if (is_array($riskFactors)) {
            foreach ($riskFactors as $factor) {
                $desc = is_string($factor) ? $factor : ($factor['description'] ?? '');
                if (stripos($desc, 'camera') !== false) {
                    $recommendations[] = "Revoke camera permission if not needed";
                }
                if (stripos($desc, 'location') !== false) {
                    $recommendations[] = "Set location permission to 'Only while using app'";
                }
                if (stripos($desc, 'background') !== false) {
                    $recommendations[] = "Disable background activity for this app";
                }
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "Monitor this app's behavior";
            $recommendations[] = "Run regular security scans";
        }
        
        return array_unique($recommendations);
    }
}
?>
