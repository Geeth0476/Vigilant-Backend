<?php
// controllers/CommunityController.php

require_once __DIR__ . '/../models/CommunityThreat.php';
require_once __DIR__ . '/../models/Device.php';

class CommunityController {
    private $db;
    private $model;
    private $user;
    private $device;

    public function __construct() {
        $this->db = (new Database())->getConnection();
        $this->user = Auth::requireLogin();
        $this->model = new CommunityThreat($this->db);
        $this->device = new Device($this->db);
    }

    public function getThreats() {
        $data = $this->model->getThreats();
        Response::success($data);
    }
    
    public function getThreatDetail($id) {
        $data = $this->model->getThreatById($id);
        if ($data) Response::success($data);
        else Response::error("NOT_FOUND", "Threat not found", 404);
    }
    
    public function submitReport() {
        $data = json_decode(file_get_contents("php://input"), true);
        $error = Validator::validate($data, ['package_name'=>'required', 'category'=>'required', 'description'=>'required']);
        if ($error) Response::error("VALIDATION_ERROR", $error);
        
        // Resolve Device
        $deviceIdUuid = $data['device_id'] ?? null;
        $dbDeviceId = null;
        if($deviceIdUuid) {
            $dbDeviceId = $this->device->getIdByUuid($deviceIdUuid, $this->user['id']);
        }

        $reportData = [
            'user_id' => $this->user['id'],
            'device_id' => $dbDeviceId,
            'app_name' => $data['app_name'] ?? 'Unknown',
            'package_name' => $data['package_name'],
            'category' => strtoupper($data['category'] ?? 'OTHER'),
            'description' => $data['description'],
            'additional_details' => $data['additional_details'] ?? '',
            'consent_anonymous' => $data['consent_anonymous'] ?? 1,
            'consent_data_usage' => $data['consent_data_usage'] ?? 1
        ];
        
        if ($this->model->submitReport($reportData)) {
            Response::success(["message" => "Report submitted successfully"]);
        } else {
            Response::error("SERVER_ERROR", "Failed to submit report");
        }
    }
    
    public function getMyReports() {
        $data = $this->model->getUserReports($this->user['id']);
        Response::success($data);
    }
}
?>
