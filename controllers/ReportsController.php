<?php
// controllers/ReportsController.php

require_once __DIR__ . '/../models/WeeklyReport.php';
require_once __DIR__ . '/../models/Device.php';

class ReportsController {
    private $db;
    private $user;
    private $device;
    private $report;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
        $this->device = new Device($this->db);
        $this->report = new WeeklyReport($this->db);
    }

    public function getWeekly() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        $dbDeviceId = null;
        
        if ($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
            if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");
        }

        $weekStart = $_GET['week_start'] ?? null;
        
        $weekly = $this->report->getWeekly($this->user['id'], $dbDeviceId, $weekStart);
        
        if (!$weekly) {
            // Auto-generate if not exists
            if ($dbDeviceId) {
                $weekStart = $weekStart ?: date('Y-m-d', strtotime('monday this week'));
                $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                $reportId = $this->report->generate($this->user['id'], $dbDeviceId, $weekStart, $weekEnd);
                if ($reportId) {
                    $weekly = $this->report->getWeekly($this->user['id'], $dbDeviceId, $weekStart);
                }
            }
        }
        
        if ($weekly) {
            // Decode JSON fields
            $weekly['top_offenders'] = json_decode($weekly['top_offenders'], true) ?: [];
            $weekly['risk_trend'] = json_decode($weekly['risk_trend'], true) ?: [];
            // Retrieve list
            Response::success([$weekly]);
        } else {
            Response::success([]); // Empty list
        }
    }

    public function getHistory() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        $dbDeviceId = null;
        
        if ($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
            if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $history = $this->report->getHistory($this->user['id'], $dbDeviceId, $limit);
        
        // Decode JSON fields
        foreach ($history as &$report) {
            $report['top_offenders'] = json_decode($report['top_offenders'], true) ?: [];
            $report['risk_trend'] = json_decode($report['risk_trend'], true) ?: [];
        }
        
        Response::success($history);
    }

    public function getDetail($reportId) {
        $query = "SELECT * FROM weekly_reports 
                  WHERE id = :id AND user_id = :uid LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $reportId, PDO::PARAM_INT);
        $stmt->bindParam(':uid', $this->user['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
            $report['top_offenders'] = json_decode($report['top_offenders'], true) ?: [];
            $report['risk_trend'] = json_decode($report['risk_trend'], true) ?: [];
            Response::success($report);
        } else {
            Response::error("NOT_FOUND", "Report not found", 404);
        }
    }
}
?>
