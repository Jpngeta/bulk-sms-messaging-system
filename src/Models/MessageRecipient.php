<?php

namespace App\Models;

use PDO;

class MessageRecipient
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO message_recipients (message_id, phone_number, delivery_status, twilio_sid, error_message) 
             VALUES (?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $data['message_id'],
            $data['phone_number'],
            $data['delivery_status'] ?? 'pending',
            $data['twilio_sid'] ?? null,
            $data['error_message'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $twilioSid = null, ?string $errorMessage = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE message_recipients 
             SET delivery_status = ?, twilio_sid = ?, error_message = ?, 
                 sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END,
                 delivered_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE delivered_at END
             WHERE id = ?"
        );
        
        return $stmt->execute([$status, $twilioSid, $errorMessage, $status, $status, $id]);
    }

    public function findByMessageId(int $messageId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM message_recipients WHERE message_id = ?");
        $stmt->execute([$messageId]);
        
        return $stmt->fetchAll();
    }

    public function findByTwilioSid(string $twilioSid): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM message_recipients WHERE twilio_sid = ?");
        $stmt->execute([$twilioSid]);
        $recipient = $stmt->fetch();
        
        return $recipient ?: null;
    }

    public function getStatusCounts(int $messageId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                delivery_status,
                COUNT(*) as count
            FROM message_recipients 
            WHERE message_id = ?
            GROUP BY delivery_status
        ");
        $stmt->execute([$messageId]);
        
        $counts = [];
        while ($row = $stmt->fetch()) {
            $counts[$row['delivery_status']] = $row['count'];
        }
        
        return $counts;
    }

    public function bulkInsert(int $messageId, array $phoneNumbers): bool
    {
        $this->db->beginTransaction();
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO message_recipients (message_id, phone_number, delivery_status) VALUES (?, ?, 'pending')"
            );
            
            foreach ($phoneNumbers as $phone) {
                $stmt->execute([$messageId, $phone]);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}