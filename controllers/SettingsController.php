<?php
// controllers/SettingsController.php

require_once __DIR__ . '/../models/Settings.php';
require_once __DIR__ . '/../models/Device.php';

class SettingsController {
    private $db;
    private $user;
    private $device;
    private $settings;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
        $this->device = new Device($this->db);
        $this->settings = new Settings($this->db);
    }

    public function getScanSettings() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        $dbDeviceId = null;
        
        if ($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
            if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");
        } else {
            $dbDeviceId = $this->user['current_device_id'] ?? null;
        }

        $scanSettings = $this->settings->getScanSettings($this->user['id'], $dbDeviceId);
        Response::success($scanSettings);
    }

    public function updateScanSettings() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        $dbDeviceId = null;
        
        if ($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
            // If device UUID passed but not found, that's an error. 
            // If NOT passed, we treat as Global/Default (dbDeviceId = null).
            if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");
        } else {
             // Fallback
             $dbDeviceId = $this->user['current_device_id'] ?? null;
        }

        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        
        // Fetch current to merge partial updates
        $current = $this->settings->getScanSettings($this->user['id'], $dbDeviceId);
        $data = array_merge($current, $data);
        
        $error = Validator::validate($data, [
            'auto_scan' => 'required', // Now checked against merged data
            'scan_frequency' => 'required',
            'detection_level' => 'required',
            'deep_scan' => 'required'
        ]);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        if ($this->settings->updateScanSettings($this->user['id'], $dbDeviceId, $data)) {
            Response::success(["message" => "Scan settings updated"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to update scan settings");
        }
    }

    public function getAlertRules() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        $dbDeviceId = null;
        
        if ($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
            if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");
        } else {
             $dbDeviceId = $this->user['current_device_id'] ?? null;
        }

        $alertRules = $this->settings->getAlertRules($this->user['id'], $dbDeviceId);
        Response::success($alertRules);
    }

    public function updateAlertRules() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        $dbDeviceId = null;
        
        if ($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
            if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");
        } else {
             $dbDeviceId = $this->user['current_device_id'] ?? null;
        }

        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        
        $error = Validator::validate($data, [
            'notify_critical' => 'required',
            'notify_suspicious' => 'required',
            'notify_permissions' => 'required',
            'notify_community' => 'required'
        ]);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        if ($this->settings->updateAlertRules($this->user['id'], $dbDeviceId, $data)) {
            Response::success(["message" => "Alert rules updated"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to update alert rules");
        }
    }

    public function getPrivacySettings() {
        $privacySettings = $this->settings->getPrivacySettings($this->user['id']);
        Response::success($privacySettings);
    }

    public function updatePrivacySettings() {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        
        $error = Validator::validate($data, [
            'share_usage_stats' => 'required',
            'share_crash_reports' => 'required'
        ]);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        if ($this->settings->updatePrivacySettings($this->user['id'], $data)) {
            Response::success(["message" => "Privacy settings updated"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to update privacy settings");
        }
    }
}
?>
