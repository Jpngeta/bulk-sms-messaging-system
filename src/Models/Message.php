<?php

namespace App\Models;

use PDO;

class Message
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (user_id, message_text, total_recipients, status) VALUES (?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $data['user_id'],
            $data['message_text'],
            $data['total_recipients'] ?? 0,
            $data['status'] ?? 'pending'
        ]);
        
        return $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        $message = $stmt->fetch();
        
        return $message ?: null;
    }

    public function findByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE messages SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function updateCounts(int $id, ?int $successful = null, ?int $failed = null): bool
    {
        $updates = [];
        $params = [];
        
        if ($successful !== null) {
            $updates[] = "successful_sends = ?";
            $params[] = $successful;
        }
        
        if ($failed !== null) {
            $updates[] = "failed_sends = ?";
            $params[] = $failed;
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE messages SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function getStatistics(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_campaigns,
                SUM(total_recipients) as total_messages_sent,
                SUM(successful_sends) as total_successful,
                SUM(failed_sends) as total_failed
            FROM messages 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch() ?: [];
    }
}