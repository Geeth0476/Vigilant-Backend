<?php
// controllers/ChatController.php

require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../config/db.php';

class ChatController {
    
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function sendMessage() {
        // Authenticate (Basic check)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        // In a real app, verify token. Skipping for demo speed/robustness unless needed.
        
        $input = json_decode(file_get_contents("php://input"), true);
        $query = $input['query'] ?? '';
        $packageName = $input['package_name'] ?? '';
        $behaviors = $input['behaviors'] ?? [];
        
        if (empty($query)) {
            Response::error("BAD_REQUEST", "Query is required", 400);
            return;
        }
        
        // 1. Analyze Intent
        $responseDetails = $this->generateResponse($query, $packageName, $behaviors);
        
        Response::json([
            "response" => $responseDetails['text'],
            "suggestions" => $responseDetails['suggestions']
        ]);
    }
    
    private function generateResponse($query, $packageName, $behaviors = []) {
        $q = strtolower($query);
        $dbData = null;
        
        // Try to fetch real data if package is known
        if (!empty($packageName)) {
            $stmt = $this->db->prepare("SELECT * FROM community_threats WHERE package_name = :pkg LIMIT 1");
            $stmt->execute([':pkg' => $packageName]);
            $dbData = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // CASE: Detected Local Behaviors (Prioritize this if specific risk query)
        if (!empty($behaviors) && (strpos($q, 'why') !== false || strpos($q, 'risk') !== false || strpos($q, 'behavior') !== false || strpos($q, 'do') !== false)) {
            $behaviorList = implode(", ", $behaviors);
            return [
                'text' => "I've analyzed the specific behaviors detected on this device.\n\nThis app is flagged due to: **$behaviorList**.\n\nThese patterns are often associated with data collection or potential tracking. I recommend restricting permissions or uninstalling if you don't use these features.",
                'suggestions' => ["How to restrict permissions?", "Uninstall now"]
            ];
        }

        // CASE: Asking about specific app risk (Global DB)
        if (strpos($q, 'report') !== false || strpos($q, 'safe') !== false || strpos($q, 'risk') !== false) {
            if ($dbData) {
                $risk = $dbData['risk_level'];
                $count = $dbData['report_count'];
                $cat = $dbData['category'];
                
                return [
                    'text' => "I checked our live community database. This app is flagged as **$risk** risk.\n\nIt has been reported by **$count users**, mostly for **$cat**. \n\nMy advice: Proceed with caution.",
                    'suggestions' => ["Show details", "How to uninstall?"]
                ];
            } else if (!empty($packageName)) {
                 return [
                    'text' => "I checked the global database, but there are no community reports for this specific app yet. \n\nThis means it's either safe or very new. Trust the local scan score.",
                    'suggestions' => ["What permissions does it use?", "Is it new?"]
                ];
            }
        }
        
        // CASE: General Privacy
        if (strpos($q, 'privacy') !== false || strpos($q, 'data') !== false) {
             return [
                'text' => "At Vigilant, we believe in 'On-Device Intelligence'.\n\nUnlike other cloud antiviruses, we calculate risk locally on your phone. We only check the cloud for community stats.",
                'suggestions' => ["How to secure my phone?", "Check my risk score"]
            ];
        }
        
        // CASE: Joke / Hello
        if (strpos($q, 'hello') !== false || strpos($q, 'hi') !== false) {
             return [
                'text' => "Hello! I am Vigi, your real-time security assistant. I'm connected to the Vigilant Threat Network.\n\nAsk me about any app.",
                'suggestions' => ["Is WhatsApp safe?", "Check this app"]
            ];
        }

        // Default "AI-like" fallback
        return [
            'text' => "I'm analyzing your request against our threat engine... \n\nFor '$query', I recommend reviewing your 'High Risk' apps in the dashboard. I prioritize apps that use Camera or Mic in the background.",
            'suggestions' => ["Scan my device", "Show high risk apps"]
        ];
    }
}
?>
