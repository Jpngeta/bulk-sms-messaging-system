<?php

use Config\Database;
use App\Controllers\AuthController;
use App\Controllers\MessageController;
use App\Controllers\ContactController;
use App\Controllers\DashboardController;
use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv->load();
}

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

$database = new Database();
$db = $database->getConnection();

$app->add(function (Request $request, $handler) use ($db) {
    $request = $request->withAttribute('db', $db);
    return $handler->handle($request);
});

$app->get('/', DashboardController::class . ':index');

$app->group('/api', function (RouteCollectorProxy $group) {
    $group->post('/auth/register', AuthController::class . ':register');
    $group->post('/auth/login', AuthController::class . ':login');
    
    $group->group('', function (RouteCollectorProxy $protected) {
        $protected->get('/auth/profile', AuthController::class . ':profile');
        $protected->post('/messages/send', MessageController::class . ':sendSingle');
        $protected->post('/messages/send-bulk', MessageController::class . ':sendBulk');
        $protected->get('/messages/history', MessageController::class . ':history');
        $protected->get('/messages/{id}/status', MessageController::class . ':status');
        
        $protected->get('/contacts', ContactController::class . ':list');
        $protected->post('/contacts', ContactController::class . ':add');
        $protected->post('/contacts/upload', ContactController::class . ':upload');
        $protected->delete('/contacts/{id}', ContactController::class . ':delete');
    })->add(AuthMiddleware::class);
});

$app->run();