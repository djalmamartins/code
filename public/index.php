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

header('X-Content-Type-Options: nosniff');
header_remove('X-Powered-By');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline'; connect-src 'self'");
if (APP_ENV === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(APP_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $details = APP_DEBUG ? ' ' . basename($file) . ':' . $line : '';
    error_log('Moves Code runtime warning (' . $severity . ').' . $details);

    return true;
});

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
    error_log('Moves Code database connection failed.');
}

try {
    $theme = ThemeLoader::load("web");
    $router = new Router(url());
    $theme["routes"]($router);
} catch (Throwable $exception) {
    $details = APP_DEBUG ? ' ' . get_class($exception) . ' at ' . basename($exception->getFile()) . ':' . $exception->getLine() : '';
    error_log('Moves Code request failed.' . $details);
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
