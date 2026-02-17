<?php
// controllers/DevicesController.php

require_once __DIR__ . '/../models/Device.php';

class DevicesController {
    private $db;
    private $user;
    private $device;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
        $this->device = new Device($this->db);
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];

        $error = Validator::validate($data, [
            'device_id' => 'required',       // android uuid
            'device_model' => 'required',
            'os_version' => 'required',
        ]);
        if ($error) Response::error("VALIDATION_ERROR", $error);

        $this->device->user_id = $this->user['id'];
        $this->device->device_uuid = $data['device_id'];
        $this->device->device_model = $data['device_model'];
        $this->device->os_version = $data['os_version'];

        if ($this->device->registerOrUpdate()) {
            Response::success([
                "device_db_id" => (int)$this->device->id,
                "device_id" => $this->device->device_uuid,
            ], 201);
        }

        Response::error("SERVER_ERROR", "Unable to register device", 500);
    }

    public function list() {
        // Minimal: list the user's devices (active sessions UI)
        $stmt = $this->db->prepare("SELECT id, device_uuid as uuid, device_model as device_name, device_model as model, 'Unknown' as manufacturer, os_version, last_active_at as last_active, created_at FROM devices WHERE user_id = :uid ORDER BY last_active_at DESC");
        $stmt->bindParam(':uid', $this->user['id']);
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add is_current flag
        $currentUuid = $_GET['current_device_id'] ?? null;
        foreach ($devices as &$dev) {
            $dev['is_current'] = ($currentUuid && $dev['uuid'] === $currentUuid);
            // prettify manufacturer if possible, or leave as Unknown
        }
        
        Response::success($devices);
    }

    public function revoke($deviceUuid) {
        // For now: mark sessions revoked for this user + deviceUuid.
        // NOTE: user_sessions.device_id currently stores whatever the client passes; we standardize by resolving to DB device id.
        $dbDeviceId = $this->device->getIdByUuid($deviceUuid, $this->user['id']);
        if (!$dbDeviceId) Response::error("NOT_FOUND", "Device not registered", 404);

        $stmt = $this->db->prepare("UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = :uid AND device_id = :did AND revoked_at IS NULL");
        $stmt->bindParam(':uid', $this->user['id']);
        $stmt->bindParam(':did', $dbDeviceId);
        $stmt->execute();

        Response::success(["message" => "Device session revoked"]);
    }
}

?>

