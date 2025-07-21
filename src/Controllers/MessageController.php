<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\MessageRecipient;
use App\Services\TalkSasaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MessageController
{
    public function sendSingle(Request $request, Response $response): Response
    {
        try {
            error_log("=== SMS Send Request Start ===");
            error_log("Raw body: " . $request->getBody());
            
            $data = $request->getParsedBody();
            $user = $request->getAttribute('user');
            
            error_log("Parsed data: " . json_encode($data));
            error_log("User: " . json_encode($user));
            
            if (empty($data['phone_number']) || empty($data['message'])) {
                error_log("ERROR: Missing phone_number or message");
                $response->getBody()->write(json_encode(['error' => 'Phone number and message required']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $db = $request->getAttribute('db');
            $smsService = new TalkSasaService();
            
            error_log("Original phone: " . $data['phone_number']);
            
            try {
                $phoneNumber = $smsService->formatPhoneNumber($data['phone_number']);
                error_log("Formatted phone: " . $phoneNumber);
            } catch (\InvalidArgumentException $e) {
                error_log("ERROR: Phone formatting failed: " . $e->getMessage());
                $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            if (!$smsService->validatePhoneNumber($phoneNumber)) {
                error_log("ERROR: Phone validation failed for: " . $phoneNumber);
                $response->getBody()->write(json_encode(['error' => 'Invalid phone number format']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $messageModel = new Message($db);
            $messageId = $messageModel->create([
                'user_id' => $user['id'],
                'message_text' => $data['message'],
                'total_recipients' => 1,
                'status' => 'processing'
            ]);
            error_log("Created message ID: " . $messageId);

            $recipientModel = new MessageRecipient($db);
            $recipientId = $recipientModel->create([
                'message_id' => $messageId,
                'phone_number' => $phoneNumber
            ]);
            error_log("Created recipient ID: " . $recipientId);

            error_log("Sending SMS to: " . $phoneNumber);
            $result = $smsService->sendSMS($phoneNumber, $data['message']);
            error_log("SMS result: " . json_encode($result));
            
            if ($result['success']) {
            $recipientModel->updateStatus($recipientId, 'sent', $result['sid']);
            $messageModel->updateStatus($messageId, 'completed');
            $messageModel->updateCounts($messageId, 1, 0);
            
                error_log("SMS sent successfully, Message ID: " . $result['sid']);
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message_id' => $messageId,
                    'sms_id' => $result['sid'],
                    'cost' => $result['cost'] ?? null
                ]));
            } else {
                error_log("SMS failed: " . $result['error']);
                $recipientModel->updateStatus($recipientId, 'failed', null, $result['error']);
                $messageModel->updateStatus($messageId, 'failed');
                $messageModel->updateCounts($messageId, 0, 1);
                
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => $result['error'],
                    'error_code' => $result['code'] ?? null
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            error_log("=== SMS Send Request End ===");
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log("FATAL ERROR in sendSingle: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function sendBulk(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        
        if (empty($data['phone_numbers']) || empty($data['message']) || !is_array($data['phone_numbers'])) {
            $response->getBody()->write(json_encode(['error' => 'Phone numbers array and message required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (count($data['phone_numbers']) > 100) {
            $response->getBody()->write(json_encode(['error' => 'Maximum 100 recipients allowed per batch']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = $request->getAttribute('db');
        $smsService = new TalkSasaService();
        
        $validNumbers = [];
        foreach ($data['phone_numbers'] as $phone) {
            $formatted = $smsService->formatPhoneNumber($phone);
            if ($smsService->validatePhoneNumber($formatted)) {
                $validNumbers[] = $formatted;
            }
        }

        if (empty($validNumbers)) {
            $response->getBody()->write(json_encode(['error' => 'No valid phone numbers provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $messageModel = new Message($db);
        $messageId = $messageModel->create([
            'user_id' => $user['id'],
            'message_text' => $data['message'],
            'total_recipients' => count($validNumbers),
            'status' => 'processing'
        ]);

        $recipientModel = new MessageRecipient($db);
        $recipientModel->bulkInsert($messageId, $validNumbers);

        $successful = 0;
        $failed = 0;
        $results = [];

        // Use bulk SMS for better performance
        $bulkResult = $smsService->sendBulkSMS($validNumbers, $data['message']);
        
        if ($bulkResult['success']) {
            $successful = $bulkResult['total_sent'];
            $failed = $bulkResult['total_failed'];
            $results = [];
            
            foreach ($bulkResult['successful'] as $success) {
                $results[] = ['phone' => $success['phone'], 'status' => 'sent', 'messageId' => $success['messageId']];
            }
            
            foreach ($bulkResult['failed'] as $failure) {
                $results[] = ['phone' => $failure['phone'], 'status' => 'failed', 'error' => $failure['error']];
            }
        } else {
            // Fallback to individual sends if bulk fails
            $successful = 0;
            $failed = 0;
            $results = [];

            foreach ($validNumbers as $phoneNumber) {
                $result = $smsService->sendSMS($phoneNumber, $data['message']);
            
                if ($result['success']) {
                    $successful++;
                    $results[] = ['phone' => $phoneNumber, 'status' => 'sent', 'sid' => $result['sid']];
                } else {
                    $failed++;
                    $results[] = ['phone' => $phoneNumber, 'status' => 'failed', 'error' => $result['error']];
                }
                
                usleep(200000); // 200ms delay between sends for rate limiting
            }
        }

        $messageModel->updateCounts($messageId, $successful, $failed);
        $messageModel->updateStatus($messageId, 'completed');

        $response->getBody()->write(json_encode([
            'success' => true,
            'message_id' => $messageId,
            'total_sent' => count($validNumbers),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function history(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $db = $request->getAttribute('db');
        
        $params = $request->getQueryParams();
        $limit = min((int)($params['limit'] ?? 20), 100);
        $offset = max((int)($params['offset'] ?? 0), 0);

        $messageModel = new Message($db);
        $messages = $messageModel->findByUserId($user['id'], $limit, $offset);

        $response->getBody()->write(json_encode([
            'messages' => $messages,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function status(Request $request, Response $response, array $args): Response
    {
        $messageId = (int)$args['id'];
        $user = $request->getAttribute('user');
        $db = $request->getAttribute('db');

        $messageModel = new Message($db);
        $message = $messageModel->findById($messageId);

        if (!$message || $message['user_id'] != $user['id']) {
            $response->getBody()->write(json_encode(['error' => 'Message not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $recipientModel = new MessageRecipient($db);
        $recipients = $recipientModel->findByMessageId($messageId);
        $statusCounts = $recipientModel->getStatusCounts($messageId);

        $response->getBody()->write(json_encode([
            'message' => $message,
            'recipients' => $recipients,
            'status_summary' => $statusCounts
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}