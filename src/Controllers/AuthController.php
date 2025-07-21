<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\JWTService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Missing required fields']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = $request->getAttribute('db');
        $userModel = new User($db);
        
        if ($userModel->findByUsername($data['username'])) {
            $response->getBody()->write(json_encode(['error' => 'Username already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }
        
        if ($userModel->findByEmail($data['email'])) {
            $response->getBody()->write(json_encode(['error' => 'Email already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        if ($userModel->create($data)) {
            $response->getBody()->write(json_encode(['message' => 'User created successfully']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        }

        $response->getBody()->write(json_encode(['error' => 'Failed to create user']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        if (empty($data['username']) || empty($data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Username and password required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = $request->getAttribute('db');
        $userModel = new User($db);
        $user = $userModel->findByUsername($data['username']);
        
        if (!$user || !$userModel->verifyPassword($data['password'], $user['password_hash'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $jwtService = new JWTService();
        $token = $jwtService->generateToken([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'user_type' => $user['user_type']
        ]);

        $response->getBody()->write(json_encode([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'user_type' => $user['user_type']
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function profile(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        
        $response->getBody()->write(json_encode([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'user_type' => $user['user_type'],
                'created_at' => $user['created_at']
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}