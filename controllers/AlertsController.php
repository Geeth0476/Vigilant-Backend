<?php
// controllers/AlertsController.php

require_once __DIR__ . '/../models/SecurityAlert.php';
require_once __DIR__ . '/../models/Device.php';

class AlertsController {
    private $db;
    private $user;
    private $device;
    private $alert;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
        $this->device = new Device($this->db);
        $this->alert = new SecurityAlert($this->db);
    }

    public function getRecent() {
        $deviceIdUuid = $_GET['device_id'] ?? null;
        if (!$deviceIdUuid) Response::error("VALIDATION_ERROR", "device_id parameter is required");
        
        $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
        if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered");

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $alerts = $this->alert->getRecent($dbDeviceId, $limit);
        
        Response::success($alerts);
    }

    public function getDetail($publicId) {
        $alert = $this->alert->getById($publicId, $this->user['id']);
        
        if ($alert) {
            Response::success($alert);
        } else {
            Response::error("NOT_FOUND", "Alert not found", 404);
        }
    }

    public function acknowledge() {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        
        $error = Validator::validate($data, ['alert_id' => 'required']);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        if ($this->alert->acknowledge($data['alert_id'], $this->user['id'])) {
            Response::success(["message" => "Alert acknowledged"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to acknowledge alert");
        }
    }
}
?>
