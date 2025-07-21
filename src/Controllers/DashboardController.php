<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    public function index(Request $request, Response $response): Response
    {
        $html = file_get_contents(__DIR__ . '/../../templates/dashboard.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}