<?php

/**
 * Moves Code | Front Controller
 *
 * Recebe as requisições públicas e expõe o diagnóstico inicial da aplicação.
 *
 * @author Djalma Martins
 * @package Moves
 */

require dirname(__DIR__) . "/index.php";

use Moves\Boot\Database;
use Moves\Boot\ThemeLoader;
use MovesCode\Router\Router;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'application' => 'Moves Code',
        'php' => PHP_VERSION,
        'timestamp' => gmdate(DATE_ATOM),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    Database::configureFromEnvironment();
} catch (Throwable) {
    // Authentication routes report a generic unavailable response; no connection details are exposed.
}

try {
    $theme = ThemeLoader::load("web");
    $router = new Router(url());
    $theme["routes"]($router);
} catch (Throwable $exception) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Request Error";
    exit;
}

if (!$router->dispatch()) {
    $status = $router->error() ?? 500;
    http_response_code($status);
    header("Content-Type: text/plain; charset=utf-8");
    echo $status === 404 ? "Not Found" : "Request Error";
}
