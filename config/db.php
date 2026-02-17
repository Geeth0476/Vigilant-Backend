<?php
// config/db.php
require_once 'config.php';

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            // In a real app, log this error and don't show specific details to user
            file_put_contents(__DIR__ . '/../logs/api.log', "[" . date('Y-m-d H:i:s') . "] DB Connection Error: " . $exception->getMessage() . "\n", FILE_APPEND);
            echo json_encode(["success" => false, "error" => ["code" => "DB_ERROR", "message" => "Database connection failed"]]);
            exit;
        }
        return $this->conn;
    }
}
?>
