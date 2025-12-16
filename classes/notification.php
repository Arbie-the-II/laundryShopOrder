<?php
class Notification {
    private $conn;
    private $table = 'notifications';

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create a new notification for Admins
    public function create($title, $message, $link = '#', $type = 'info') {
        $sql = "INSERT INTO " . $this->table . " 
                (recipient_type, user_id, title, message, link, type, is_read, created_at) 
                VALUES ('user', NULL, :title, :message, :link, :type, 0, NOW())";
        
        // Note: user_id = NULL implies it's visible to ALL admins/staff
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':message' => $message,
                ':link' => $link,
                ':type' => $type
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }

    // Get unread notifications for the system
    public function getUnreadNotifications() {
        $sql = "SELECT * FROM " . $this->table . " 
                WHERE recipient_type = 'user' AND is_read = 0 
                ORDER BY created_at DESC LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Count unread notifications
    public function countUnread() {
        $sql = "SELECT COUNT(*) FROM " . $this->table . " 
                WHERE recipient_type = 'user' AND is_read = 0";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    // Mark a specific notification as read
    public function markAsRead($id) {
        $sql = "UPDATE " . $this->table . " SET is_read = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
    }
}
?>