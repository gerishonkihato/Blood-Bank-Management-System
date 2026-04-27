<?php
// Use __DIR__ so the include works regardless of where the file is required from
require_once __DIR__ . '/../config/database.php';

class AuditLog {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function log($userId, $action, $targetId = null) {
        $stmt = $this->db->prepare("INSERT INTO audit_log (userId, action, targetId) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $targetId]);
    }
}
?>