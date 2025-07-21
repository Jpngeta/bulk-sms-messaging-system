<?php

namespace App\Middleware;

use App\Services\JWTService;
use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header missing');
        }

        $jwtService = new JWTService();
        $token = $jwtService->extractTokenFromHeader($authHeader);
        
        if (!$token) {
            return $this->unauthorizedResponse('Invalid authorization format');
        }

        $payload = $jwtService->validateToken($token);
        
        if (!$payload) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        $db = $request->getAttribute('db');
        $userModel = new User($db);
        $user = $userModel->findById($payload['user_id']);
        
        if (!$user) {
            return $this->unauthorizedResponse('User not found');
        }

        $request = $request->withAttribute('user', $user);
        
        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => $message]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}