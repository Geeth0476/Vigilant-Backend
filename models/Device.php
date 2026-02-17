<?php
// models/Device.php

class Device {
    private $conn;
    private $table = 'devices';

    public $id;
    public $user_id;
    public $device_uuid;
    public $device_model;
    public $os_version;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function registerOrUpdate() {
        // Check if exists
        $query = "SELECT id FROM " . $this->table . " WHERE user_id = :uid AND device_uuid = :uuid";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uid', $this->user_id);
        $stmt->bindParam(':uuid', $this->device_uuid);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            
            $query = "UPDATE " . $this->table . " SET device_model = :model, os_version = :os, last_active_at = NOW() WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':model', $this->device_model);
            $stmt->bindParam(':os', $this->os_version);
            $stmt->bindParam(':id', $this->id);
            return $stmt->execute();
        } else {
            // Insert
            $query = "INSERT INTO " . $this->table . " (user_id, device_uuid, device_model, os_version) VALUES (:uid, :uuid, :model, :os)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':uid', $this->user_id);
            $stmt->bindParam(':uuid', $this->device_uuid);
            $stmt->bindParam(':model', $this->device_model);
            $stmt->bindParam(':os', $this->os_version);
            if($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
        }
        return false;
    }
    
    public function getIdByUuid($uuid, $user_id) {
        $query = "SELECT id FROM " . $this->table . " WHERE device_uuid = :uuid AND user_id = :uid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':uuid', $uuid);
        $stmt->bindParam(':uid', $user_id);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC)['id'];
        }
        return null;
    }
}
?>
