<?php

namespace App\Services;

class TalkSasaService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = $_ENV['TALKSASA_API_KEY'] ?? '';
        $this->apiUrl = $_ENV['TALKSASA_API_URL'] ?? 'https://bulksms.talksasa.com/api/v3/sms/send';
        
        if (empty($this->apiKey)) {
            throw new \Exception('TalkSasa API key not configured');
        }
    }

    public function sendSMS(string $to, string $message): array
    {
        $postData = [
            'recipient' => $to,
            'type' => 'plain',
            'message' => $message
        ];

        // Only add sender_id if it's configured
        $senderId = $_ENV['TALKSASA_SENDER_ID'] ?? '';
        if (!empty($senderId)) {
            $postData['sender_id'] = $senderId;
        }

        return $this->makeApiCallWithRetry($postData, $to);
    }

    public function sendBulkSMS(array $recipients, string $message): array
    {
        // TalkSasa API appears to handle single recipients, so we'll send individually
        $successful = [];
        $failed = [];

        foreach ($recipients as $recipient) {
            $result = $this->sendSMS($recipient, $message);
            
            if ($result['success']) {
                $successful[] = [
                    'phone' => $recipient,
                    'messageId' => $result['sid'],
                    'cost' => $result['cost'] ?? null
                ];
            } else {
                $failed[] = [
                    'phone' => $recipient,
                    'error' => $result['error']
                ];
            }
            
            // Small delay between sends to avoid rate limiting
            usleep(100000); // 100ms delay
        }

        return [
            'success' => true,
            'successful' => $successful,
            'failed' => $failed,
            'total_sent' => count($successful),
            'total_failed' => count($failed)
        ];
    }

    private function makeApiCallWithRetry(array $postData, string $recipient, int $maxRetries = 3): array
    {
        $attempt = 0;
        $lastError = null;

        error_log("Sending SMS to {$recipient} with sender " . ($postData['sender_id'] ?? 'SMS'));
        error_log("Request payload: " . json_encode($postData));

        while ($attempt < $maxRetries) {
            try {
                $response = $this->makeApiCall($postData);
                
                error_log("Response: " . json_encode($response));
                
                if ($response && isset($response['status']) && $response['status'] === 'success') {
                    return [
                        'success' => true,
                        'sid' => $response['message_id'] ?? uniqid(),
                        'status' => 'sent',
                        'to' => $recipient,
                        'cost' => $response['cost'] ?? null
                    ];
                } else {
                    $lastError = $response['message'] ?? 'Unknown error occurred';
                    error_log("Error on attempt " . ($attempt + 1) . ": " . $lastError);
                }

            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                error_log("Exception on attempt " . ($attempt + 1) . ": " . $lastError);
            }
            
            $attempt++;
        }

        return [
            'success' => false,
            'error' => $lastError ?? 'All retry attempts failed',
            'code' => null
        ];
    }

    private function makeApiCall(array $postData): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        error_log("Response status: " . $httpCode);
        error_log("Response text: " . substr($response, 0, 200));
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL Error: ' . $error);
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('HTTP Error: ' . $httpCode . ' - ' . $response);
        }

        if (empty(trim($response))) {
            throw new \Exception('Empty response from API');
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . substr($response, 0, 100));
        }

        return $decoded;
    }

    public function validatePhoneNumber(string $phoneNumber): bool
    {
        return preg_match('/^\+?[1-9]\d{1,14}$/', $phoneNumber);
    }

    public function formatPhoneNumber(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // If already has country code (+), return as-is
        if (strpos($cleaned, '+') === 0) {
            return $cleaned;
        }
        
        // If no country code, throw error
        throw new \InvalidArgumentException('Please include country code (e.g. +254 for Kenya, +1 for US)');
    }
}