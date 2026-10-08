<?php

/**
 * Moves Code | Authorization HTTP Tests
 *
 * Exercita login, sessão, CSRF, logout e autorização HTTP com banco isolado.
 *
 * @author Djalma Martins
 * @package Moves\Tests
 */

require dirname(__DIR__) . '/index.php';

function assertAuthorizationHttpTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function authorizationHttpRequest(string $url, string $method = 'GET', string $cookie = '', string $body = ''): array
{
    $headers = ['Accept: text/html'];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 4,
    ]]);
    $content = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    $status = (int) ($statusMatch[1] ?? 0);
    $cookieHeader = '';
    $location = '';
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie:') === 0
            && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $cookieMatch)
        ) {
            $cookieHeader = $cookieMatch[1] . '=' . $cookieMatch[2];
        }
        if (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, strlen('Location:')));
        }
    }

    return [$status, is_string($content) ? $content : '', $cookieHeader, $location];
}

function authorizationHttpCsrf(string $html): string
{
    if (!preg_match('/name="_token"\s+value="([a-f0-9]{64})"/', $html, $match)) {
        throw new RuntimeException('Could not obtain the CSRF token from the isolated test session.');
    }

    return $match[1];
}

$databasePath = sys_get_temp_dir() . '/moves-code-auth-http-' . bin2hex(random_bytes(8)) . '.sqlite';
$database = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL COLLATE NOCASE UNIQUE,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    role VARCHAR(16) NOT NULL DEFAULT 'user',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$database->exec('CREATE TABLE auth_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL
)');
$insert = $database->prepare('INSERT INTO users (name, email, password, status, role) VALUES (?, ?, ?, ?, ?)');
$insert->execute(['HTTP Admin Fixture', 'http-admin@example.test', password_hash('test-password-admin', PASSWORD_DEFAULT), 'active', 'admin']);
$insert->execute(['HTTP User Fixture', 'http-user@example.test', password_hash('test-password-user', PASSWORD_DEFAULT), 'active', 'user']);
unset($database);
chmod($databasePath, 0600);

$listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($listener === false) {
    unlink($databasePath);
    throw new RuntimeException('Could not reserve a local port for the authorization HTTP test.');
}
$address = stream_socket_get_name($listener, false);
fclose($listener);
$port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
$baseUrl = 'http://127.0.0.1:' . $port;

putenv('APP_ENV=local');
putenv('APP_DEBUG=false');
putenv('APP_URL=' . $baseUrl);
putenv('MOVES_DB_DSN=sqlite:' . $databasePath);
putenv('MOVES_DB_USER=');
putenv('MOVES_DB_PASSWORD=');

$pipes = [];
$server = proc_open([
    PHP_BINARY,
    '-S', '127.0.0.1:' . $port,
    '-t', dirname(__DIR__) . '/public',
    dirname(__DIR__) . '/public/index.php',
], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));

if (!is_resource($server)) {
    unlink($databasePath);
    throw new RuntimeException('Could not start the isolated authorization HTTP server.');
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.1);
        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100_000);
    }
    assertAuthorizationHttpTest($ready, 'Isolated authorization HTTP server did not start.');

    [$status, , $cookie] = authorizationHttpRequest($baseUrl . '/admin');
    assertAuthorizationHttpTest($status === 303, 'Anonymous HTTP request was not denied by /admin.');

    [$status, $loginPage, $cookie] = authorizationHttpRequest($baseUrl . '/login');
    assertAuthorizationHttpTest($status === 200 && $cookie !== '', 'HTTP login form or isolated session cookie was unavailable.');
    $csrf = authorizationHttpCsrf($loginPage);
    [$status, , $cookie, $location] = authorizationHttpRequest(
        $baseUrl . '/login',
        'POST',
        $cookie,
        http_build_query(['_token' => $csrf, 'email' => 'http-admin@example.test', 'password' => 'test-password-admin'])
    );
    assertAuthorizationHttpTest($status === 303 && str_ends_with($location, '/admin'), 'HTTP admin login failed.');

    $adminCookie = $cookie;
    [$status, $adminPage] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($adminPage, 'HTTP Admin Fixture'), 'Authorized HTTP admin access failed.');
    [$status, $adminPage] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 200 && str_contains($adminPage, 'HTTP Admin Fixture'), 'Authenticated HTTP session did not persist.');

    $logoutCsrf = authorizationHttpCsrf($adminPage);
    [$status, , $cookie, $location] = authorizationHttpRequest(
        $baseUrl . '/logout',
        'POST',
        $adminCookie,
        http_build_query(['_token' => $logoutCsrf])
    );
    assertAuthorizationHttpTest($status === 303 && str_ends_with($location, '/login'), 'HTTP logout did not complete.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $cookie);
    assertAuthorizationHttpTest($status === 303, 'HTTP /admin remained accessible after logout.');
    [$status] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $adminCookie);
    assertAuthorizationHttpTest($status === 303, 'The pre-logout session remained valid.');

    [$status, $loginPage, $userCookie] = authorizationHttpRequest($baseUrl . '/login');
    assertAuthorizationHttpTest($status === 200, 'Could not open the login form for a standard user.');
    $csrf = authorizationHttpCsrf($loginPage);
    [$status, , $userCookie, $location] = authorizationHttpRequest(
        $baseUrl . '/login',
        'POST',
        $userCookie,
        http_build_query(['_token' => $csrf, 'email' => 'http-user@example.test', 'password' => 'test-password-user'])
    );
    assertAuthorizationHttpTest($status === 303 && rtrim($location, '/') === $baseUrl, 'Standard user login did not remain outside admin.');
    [$status, , , $location] = authorizationHttpRequest($baseUrl . '/admin', 'GET', $userCookie);
    assertAuthorizationHttpTest($status === 303 && rtrim($location, '/') === $baseUrl, 'Standard user was not denied /admin.');

    echo "Authorization HTTP tests passed; isolated admin login/session/logout and user denial verified.\n";
} finally {
    proc_terminate($server);
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_close($server);
    @unlink($databasePath);
    @unlink($databasePath . '-journal');
    @unlink($databasePath . '-wal');
    @unlink($databasePath . '-shm');
}
