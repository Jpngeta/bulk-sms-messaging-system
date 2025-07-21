<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTService
{
    private string $secret;
    private string $algorithm = 'HS256';

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'fallback_secret_key';
    }

    public function generateToken(array $payload): string
    {
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + (24 * 60 * 60); // 24 hours
        
        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function extractTokenFromHeader(string $authHeader): ?string
    {
        if (strpos($authHeader, 'Bearer ') === 0) {
            return substr($authHeader, 7);
        }
        
        return null;
    }
}