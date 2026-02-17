<?php
// controllers/AppsController.php

require_once __DIR__ . '/../models/InstalledApp.php';
require_once __DIR__ . '/../models/Device.php';

class AppsController {
    private $user;
    private $model;
    private $device;

    public function __construct() {
        $db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
        $this->model = new InstalledApp($db);
        $this->device = new Device($db);
    }

    public function listApps() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        if (!$deviceIdUuid) {
            // Try to find any device for user? Or error.
            // For simple use case, return error or first device.
             Response::error("VALIDATION_ERROR", "device_id is required");
        }
        
        $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
        if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");

        $data = $this->model->getAll($dbDeviceId);
        Response::success($data);
    }

    public function getApp($packageName) {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        if (!$deviceIdUuid) Response::error("VALIDATION_ERROR", "device_id is required");

        $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
        if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");

        $data = $this->model->getDetails($dbDeviceId, $packageName);
        
        if ($data) Response::success($data);
        else Response::error("NOT_FOUND", "App not found or not installed on this device", 404);
    }
}
?>
