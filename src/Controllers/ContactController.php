<?php

namespace App\Controllers;

use App\Models\Contact;
use App\Services\TalkSasaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContactController
{
    public function list(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $db = $request->getAttribute('db');

        $contactModel = new Contact($db);
        $contacts = $contactModel->findByUserId($user['id']);

        $response->getBody()->write(json_encode(['contacts' => $contacts]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function add(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('user');
        
        if (empty($data['phone_number'])) {
            $response->getBody()->write(json_encode(['error' => 'Phone number required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $smsService = new TalkSasaService();
        $phoneNumber = $smsService->formatPhoneNumber($data['phone_number']);
        
        if (!$smsService->validatePhoneNumber($phoneNumber)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid phone number format']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = $request->getAttribute('db');
        $contactModel = new Contact($db);
        
        if ($contactModel->create([
            'user_id' => $user['id'],
            'name' => $data['name'] ?? '',
            'phone_number' => $phoneNumber
        ])) {
            $response->getBody()->write(json_encode(['message' => 'Contact added successfully']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to add contact']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $user = $request->getAttribute('user');

        if (!isset($uploadedFiles['file'])) {
            $response->getBody()->write(json_encode(['error' => 'No file uploaded']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $uploadedFile = $uploadedFiles['file'];
        
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['error' => 'File upload error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $fileContent = $uploadedFile->getStream()->getContents();
        $contacts = $this->parseCSV($fileContent);

        if (empty($contacts)) {
            $response->getBody()->write(json_encode(['error' => 'No valid contacts found in file']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $twilioService = new TwilioService();
        $validContacts = [];
        
        foreach ($contacts as $contact) {
            if (!empty($contact['phone_number'])) {
                $formatted = $twilioService->formatPhoneNumber($contact['phone_number']);
                if ($twilioService->validatePhoneNumber($formatted)) {
                    $validContacts[] = [
                        'name' => $contact['name'] ?? '',
                        'phone_number' => $formatted
                    ];
                }
            }
        }

        $db = $request->getAttribute('db');
        $contactModel = new Contact($db);
        $results = $contactModel->bulkInsert($user['id'], $validContacts);

        $response->getBody()->write(json_encode([
            'message' => 'Contacts upload completed',
            'total_processed' => count($validContacts),
            'successful' => $results['success'],
            'failed' => $results['failed'],
            'errors' => $results['errors']
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $contactId = (int)$args['id'];
        $user = $request->getAttribute('user');
        $db = $request->getAttribute('db');

        $contactModel = new Contact($db);
        $contact = $contactModel->findById($contactId);

        if (!$contact || $contact['user_id'] != $user['id']) {
            $response->getBody()->write(json_encode(['error' => 'Contact not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        if ($contactModel->delete($contactId, $user['id'])) {
            $response->getBody()->write(json_encode(['message' => 'Contact deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to delete contact']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    private function parseCSV(string $content): array
    {
        $lines = explode("\n", trim($content));
        $contacts = [];
        $headers = null;

        foreach ($lines as $line) {
            $data = str_getcsv(trim($line));
            
            if ($headers === null) {
                $headers = array_map('strtolower', $data);
                continue;
            }

            if (count($data) >= count($headers)) {
                $contact = [];
                foreach ($headers as $index => $header) {
                    if (in_array($header, ['name', 'phone_number', 'phone', 'number'])) {
                        $key = ($header === 'phone' || $header === 'number') ? 'phone_number' : $header;
                        $contact[$key] = $data[$index] ?? '';
                    }
                }
                
                if (!empty($contact['phone_number'])) {
                    $contacts[] = $contact;
                }
            }
        }

        return $contacts;
    }
}