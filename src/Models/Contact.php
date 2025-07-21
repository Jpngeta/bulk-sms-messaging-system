<?php

namespace App\Models;

use PDO;

class Contact
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO contacts (user_id, name, phone_number) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        );
        
        return $stmt->execute([
            $data['user_id'],
            $data['name'],
            $data['phone_number']
        ]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM contacts WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$id]);
        $contact = $stmt->fetch();
        
        return $contact ?: null;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM contacts WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function bulkInsert(int $userId, array $contacts): array
    {
        $this->db->beginTransaction();
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO contacts (user_id, name, phone_number) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)"
            );
            
            foreach ($contacts as $contact) {
                try {
                    $stmt->execute([
                        $userId,
                        $contact['name'] ?? '',
                        $contact['phone_number']
                    ]);
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Error with {$contact['phone_number']}: " . $e->getMessage();
                }
            }
            
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            $results['errors'][] = "Transaction failed: " . $e->getMessage();
        }
        
        return $results;
    }

    public function getPhoneNumbers(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT phone_number FROM contacts WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return array_column($stmt->fetchAll(), 'phone_number');
    }
}