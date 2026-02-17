<?php
// controllers/ScanController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/AppScan.php';
require_once __DIR__ . '/../core/risk_engine.php';

class ScanController {
    private $db;
    private $scan;
    private $user;
    private $device;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = Auth::requireLogin(); // Enforce Auth
        $this->scan = new AppScan($this->db);
        $this->device = new Device($this->db);
    }

    public function start() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $error = Validator::validate($data, ['device_id' => 'required', 'mode' => 'required']); // device_id here is UUID from Android
        if ($error) Response::error("VALIDATION_ERROR", $error);

        // Resolve Device ID (DB ID) from UUID
        $dbDeviceId = $this->device->getIdByUuid($data['device_id'], $this->user['id']);
        
        if (!$dbDeviceId) {
            // Auto-register device if missing? Or error? 
            // Usually app calls register first. But let's handle auto-reg or error.
            // For now, assume registered.
             // Try to register briefly
             $this->device->user_id = $this->user['id'];
             $this->device->device_uuid = $data['device_id'];
             $this->device->device_model = "Unknown";
             $this->device->os_version = "Unknown";
             if($this->device->registerOrUpdate()) {
                 $dbDeviceId = $this->device->id;
             } else {
                 Response::error("DEVICE_ERROR", "Device not found or could not register.");
             }
        }

        $this->scan->device_id = $dbDeviceId;
        $this->scan->user_id = $this->user['id'];
        $this->scan->mode = $data['mode'];

        if ($this->scan->start()) {
            // Android expects scan_id as a string in ScanStartData
            Response::success([
                "scan_id" => (string)$this->scan->id,
                "status" => "started"
            ]);
        } else {
            Response::error("SERVER_ERROR", "Could not start scan");
        }
    }

    public function complete() {
        // Prevent timeouts for large app lists (5 minutes)
        set_time_limit(300);

        $data = json_decode(file_get_contents("php://input"), true);
        
        $error = Validator::validate($data, ['scan_id' => 'required', 'risk_score' => 'required|numeric', 'risk_level' => 'required']);
        if ($error) {
            error_log("Scan Complete Validation Error: " . json_encode($error));
            Response::error("VALIDATION_ERROR", $error);
        }

        $this->scan->id = (int)$data['scan_id'];
        $this->scan->overall_risk_score = (int)$data['risk_score'];
        $this->scan->overall_risk_level = $data['risk_level'];
        
        // Process apps list
        $apps = $data['apps'] ?? [];
        $this->scan->app_count = count($apps);
        
        $high = 0; $med = 0; $safe = 0;
        $resultsBatch = [];
        
        try {
            $this->db->beginTransaction();

            // Ensure scan exists and belongs to the logged-in user, and fetch device_id
            $q = "SELECT device_id, user_id FROM app_scans WHERE id = :id LIMIT 1";
            $stmt = $this->db->prepare($q);
            $stmt->bindParam(':id', $this->scan->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                // If ID not found, check if it's a type mismatch or missing
                throw new Exception("Scan ID " . $this->scan->id . " not found in database.");
            }
            if ((int)$row['user_id'] !== (int)$this->user['id']) {
                throw new Exception("Scan does not belong to the current user (User ID mismatch).");
            }

            $this->scan->device_id = (int)$row['device_id'];

            foreach ($apps as $app) {
                // Validate essential app data
                $pkg = $app['package_name'] ?? null;
                $name = $app['app_name'] ?? null;
                
                if (!$pkg || !$name) {
                    continue; // Skip invalid entries
                }

                $score = isset($app['risk_score']) ? (int)$app['risk_score'] : 0;
                if ($score >= 70) $high++;
                elseif ($score >= 40) $med++;
                else $safe++;

                $versionName = $app['version_name'] ?? "1.0";
                $isSystem = isset($app['is_system_app']) ? (int)$app['is_system_app'] : 0;

                // 1) Upsert installed_apps snapshot
                $installedAppId = $this->scan->getOrCreateInstalledApp($pkg, $name, $versionName, $isSystem);

                // 2) Risk level
                $riskLevel = $app['risk_level'] ?? $this->deriveRiskLevel($score);
                $riskFactors = $app['risk_factors'] ?? null;
                $topFactor = null;
                if (is_array($riskFactors) && count($riskFactors) > 0) {
                    $top = $riskFactors[0];
                    $topFactor = is_string($top) ? $top : ($top['description'] ?? null);
                }

                // 3) Queue for Batch Save
                $resultsBatch[] = [
                    'installed_app_id' => $installedAppId,
                    'risk_score' => $score,
                    'risk_level' => $riskLevel,
                    'top_factor_desc' => $topFactor,
                    'risk_factors' => $riskFactors
                ];
            }
            
            // Execute Batch Save
            $this->scan->saveAppResultsBatch($resultsBatch);
            
            $this->scan->high_risk_count = $high;
            $this->scan->medium_risk_count = $med;
            $this->scan->safe_count = $safe;
        
             // 4) Calculate Aggregated Risk (Server-Side Logic)
            $aggregated = RiskEngine::aggregateDeviceRisk(
                $this->db, 
                $this->scan->device_id, 
                $this->scan->overall_risk_score, 
                $this->scan->overall_risk_level
            );

            // Update scan with server-side aggregated score
            $this->scan->overall_risk_score = $aggregated['aggregated_score'];
            $this->scan->overall_risk_level = $aggregated['aggregated_level'];

            if (!$this->scan->complete()) {
                throw new Exception("Could not update scan summary status.");
            }

            // 5) Update Device Risk Score
            $this->scan->updateDeviceRiskScore(
                $this->scan->device_id,
                $this->scan->overall_risk_score,
                $this->scan->overall_risk_level,
                $this->scan->id
            );

            $this->db->commit();
            
            // Return updated risk to client so it can update UI immediately
            Response::success([
                "message" => "Scan completed successfully",
                "backend_risk_score" => $this->scan->overall_risk_score,
                "backend_risk_level" => $this->scan->overall_risk_level,
                "community_threats" => $aggregated['community_threats'],
                "violations" => $aggregated['violations']
            ]);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // CRITICAL: Log this error so it can be seen in server logs
            error_log("SCAN COMPLETION FAILED: " . $e->getMessage());
            error_log("TRACE: " . $e->getTraceAsString());
            
            Response::error("SERVER_ERROR", "Scan completion failed: " . $e->getMessage(), 500);
        }
    }

    private function deriveRiskLevel($score) {
        if ($score >= 90) return "CRITICAL";
        if ($score >= 70) return "HIGH";
        if ($score >= 40) return "MEDIUM";
        if ($score >= 15) return "LOW";
        return "SAFE";
    }
    
    public function latest() {
         $deviceIdUuid = $_GET['device_id'] ?? null;
         if (!$deviceIdUuid) Response::error("VALIDATION_ERROR", "Device ID required");
         
         $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
         if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");

         $result = $this->scan->getLatest($dbDeviceId);
         if ($result) {
             Response::success($result);
         } else {
             Response::success(null); // No scans yet
         }
    }

    /**
     * GET /v1/scan/status?scan_id=123
     * Polling endpoint for real-time scan progress
     */
    public function status() {
        $scanId = $_GET['scan_id'] ?? null;
        if (!$scanId || !is_numeric($scanId)) {
            Response::error("VALIDATION_ERROR", "scan_id parameter is required and must be numeric");
        }

        $status = $this->scan->getStatus((int)$scanId, $this->user['id']);
        if (!$status) {
            Response::error("NOT_FOUND", "Scan not found or access denied", 404);
        }

        // Return status with real-time data
        Response::success([
            "scan_id" => (string)$status['id'],
            "status" => $status['status'], // RUNNING, COMPLETED, FAILED
            "progress_percent" => (int)$status['progress_percent'],
            "apps_scanned" => (int)$status['apps_scanned'],
            "app_count" => (int)$status['app_count'],
            "mode" => $status['mode'],
            "overall_risk_score" => $status['overall_risk_score'] ?? null,
            "overall_risk_level" => $status['overall_risk_level'] ?? null,
            "high_risk_count" => (int)($status['high_risk_count'] ?? 0),
            "medium_risk_count" => (int)($status['medium_risk_count'] ?? 0),
            "safe_count" => (int)($status['safe_count'] ?? 0),
            "started_at" => $status['started_at'],
            "completed_at" => $status['completed_at'] ?? null,
        ]);
    }

    /**
     * POST /v1/scan/{id}/progress
     * Update scan progress incrementally (optional, called during scan)
     */
    public function updateProgress($scanId) {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        
        // Verify scan belongs to user
        $status = $this->scan->getStatus((int)$scanId, $this->user['id']);
        if (!$status) {
            Response::error("NOT_FOUND", "Scan not found or access denied", 404);
        }

        $appsScanned = isset($data['apps_scanned']) ? (int)$data['apps_scanned'] : null;
        $totalApps = isset($data['total_apps']) ? (int)$data['total_apps'] : null;

        if ($appsScanned === null) {
            Response::error("VALIDATION_ERROR", "apps_scanned is required");
        }

        if ($this->scan->updateProgress((int)$scanId, $appsScanned, $totalApps)) {
            Response::success(["message" => "Progress updated"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to update progress");
        }
    }

    /**
     * GET /v1/scan/active
     * Get currently running scan for a device (useful for polling)
     */
    public function getActiveScan() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        if (!$deviceIdUuid) Response::error("VALIDATION_ERROR", "device_id parameter is required");
        
        $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
        if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");

        // Get most recent running scan
        $query = "SELECT id FROM app_scans 
                  WHERE device_id = :did AND user_id = :uid AND completed_at IS NULL 
                  ORDER BY started_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':did', $dbDeviceId, PDO::PARAM_INT);
        $stmt->bindParam(':uid', $this->user['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $scan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$scan) {
            Response::success(null); // No active scan
        }

        // Return full status
        $status = $this->scan->getStatus((int)$scan['id'], $this->user['id']);
        Response::success([
            "scan_id" => (string)$status['id'],
            "status" => $status['status'],
            "progress_percent" => (int)$status['progress_percent'],
            "apps_scanned" => (int)$status['apps_scanned'],
            "app_count" => (int)$status['app_count'],
            "mode" => $status['mode'],
            "started_at" => $status['started_at'],
        ]);
    }

    /**
     * GET /v1/scan/dashboard?device_id=...
     * Real-time dashboard aggregation endpoint for polling
     * Returns: latest risk score, active scan status, recent alerts count, top risky apps
     */
    public function getDashboardData() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        if (!$deviceIdUuid) Response::error("VALIDATION_ERROR", "device_id parameter is required");
        
        $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
        if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");

        $result = [];

        // 1. Latest risk score from device_risk_scores
        $query = "SELECT drs.last_score, drs.last_level, drs.last_updated_at, s.completed_at as last_scan_time
                  FROM device_risk_scores drs
                  LEFT JOIN app_scans s ON s.id = drs.last_scan_id
                  WHERE drs.device_id = :did";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':did', $dbDeviceId, PDO::PARAM_INT);
        $stmt->execute();
        $riskData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $result['risk_score'] = $riskData ? [
            'score' => (int)$riskData['last_score'],
            'level' => $riskData['last_level'],
            'updated_at' => $riskData['last_updated_at'],
            'last_scan_time' => $riskData['last_scan_time']
        ] : [
            'score' => 0,
            'level' => 'SAFE',
            'updated_at' => null,
            'last_scan_time' => null
        ];

        // 2. Active scan status (if any)
        $query = "SELECT id FROM app_scans 
                  WHERE device_id = :did AND user_id = :uid AND completed_at IS NULL 
                  ORDER BY started_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':did', $dbDeviceId, PDO::PARAM_INT);
        $stmt->bindParam(':uid', $this->user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $activeScan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activeScan) {
            $status = $this->scan->getStatus((int)$activeScan['id'], $this->user['id']);
            $result['active_scan'] = [
                'scan_id' => (int)$status['id'],
                'status' => $status['status'],
                'progress_percent' => (int)$status['progress_percent'],
                'apps_scanned' => (int)$status['apps_scanned'],
                'app_count' => (int)$status['app_count'],
                'mode' => $status['mode'],
            ];
        } else {
            $result['active_scan'] = null;
        }

        // 3. Recent alerts count (last 24 hours)
        $query = "SELECT COUNT(*) as count FROM security_alerts 
                  WHERE device_id = :did AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':did', $dbDeviceId, PDO::PARAM_INT);
        $stmt->execute();
        $alertCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['recent_alerts_count'] = (int)($alertCount['count'] ?? 0);

        // 4. Top 5 risky apps from latest scan
        // COMPLETE SCHEMA: Use app_scan_results + installed_apps
        $query = "SELECT ia.app_name, ia.package_name, sr.risk_level, sr.risk_score, sr.top_factor_desc
                  FROM app_scan_results sr
                  JOIN app_scans s ON s.id = sr.scan_id
                  JOIN installed_apps ia ON ia.id = sr.installed_app_id
                  WHERE s.device_id = :did AND s.completed_at IS NOT NULL
                  AND sr.risk_level IN ('HIGH', 'CRITICAL', 'MEDIUM')
                  ORDER BY s.completed_at DESC, sr.risk_score DESC
                  LIMIT 5";
                  
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':did', $dbDeviceId, PDO::PARAM_INT);
        $stmt->execute();
        $topRiskyApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['top_risky_apps'] = array_map(function($app) {
            return [
                'app_name' => $app['app_name'],
                'package_name' => $app['package_name'],
                'version_name' => 'Unknown', // Not strictly needed for dashboard card
                'is_system_app' => 0,
                'risk_score' => (int)$app['risk_score'],
                'risk_level' => $app['risk_level'],
                'top_factor_desc' => $app['top_factor_desc'] ?? 'General Risk'
            ];
        }, $topRiskyApps);

        // 5. Scan history summary (last 7 days)
        $query = "SELECT COUNT(*) as total_scans, 
                         SUM(CASE WHEN overall_risk_level IN ('HIGH','CRITICAL') THEN 1 ELSE 0 END) as high_risk_scans,
                         AVG(overall_risk_score) as avg_risk_score
                  FROM app_scans
                  WHERE device_id = :did AND user_id = :uid 
                    AND completed_at IS NOT NULL 
                    AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':did', $dbDeviceId, PDO::PARAM_INT);
        $stmt->bindParam(':uid', $this->user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $history = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['scan_history'] = [
            'total_scans' => (int)($history['total_scans'] ?? 0),
            'high_risk_scans' => (int)($history['high_risk_scans'] ?? 0),
            'avg_risk_score' => $history['avg_risk_score'] ? round((float)$history['avg_risk_score'], 1) : null
        ];

        Response::success($result);
    }
}
?>
