<?php

/**
 * Moves Code | Authentication Tests
 *
 * Valida o modelo de usuário, autenticação, sessão e proteção contra tentativas.
 *
 * @author Djalma Martins
 * @package Moves\Tests
 */

use Moves\Boot\Database;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\User;
use MovesCode\Model\Connection;

require dirname(__DIR__) . '/index.php';

function assertAuthTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('MOVES_DB_DSN=sqlite::memory:');
putenv('MOVES_DB_USER=');
putenv('MOVES_DB_PASSWORD=');
assertAuthTest(Database::configureFromEnvironment(), 'Database environment configuration failed.');
$pdo = Connection::get();
$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL COLLATE NOCASE UNIQUE,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec('CREATE TABLE auth_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL
)');

session_name('moves_code_auth_test');
session_save_path(sys_get_temp_dir());
$_SERVER['HTTPS'] = 'on';
session_id('auth-test-' . bin2hex(random_bytes(8)));
$session = new Session();
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';

$user = new User();
$user->name = 'Usuário de Teste';
$user->email = 'Teste@exemplo.com';
$user->password = 'senha-segura-de-teste';
$user->status = 'active';
assertAuthTest($user->save(), 'User creation failed.');
assertAuthTest(password_verify('senha-segura-de-teste', $user->password), 'Password was not hashed.');
assertAuthTest(password_get_info($user->password)['options']['cost'] === 12, 'Password hash cost is below the configured value.');
assertAuthTest($user->email === 'teste@exemplo.com', 'Email was not normalized.');

$duplicate = new User();
$duplicate->name = 'Duplicado';
$duplicate->email = 'TESTE@EXEMPLO.COM';
$duplicate->password = 'outra-senha-segura';
assertAuthTest(!$duplicate->save(), 'Duplicate email was accepted.');
assertAuthTest((new User())->findByEmail("' OR 1=1 --") === null, 'SQL injection input matched a user.');
assertAuthTest(!array_key_exists('password', $user->publicData()), 'Public user data leaked the password hash.');

$auth = new Auth($session);
$sessionId = session_id();
assertAuthTest($auth->login('teste@exemplo.com', 'senha-segura-de-teste'), 'Valid login failed.');
assertAuthTest(session_id() !== $sessionId, 'Session ID was not regenerated after login.');
assertAuthTest($auth->user()?->id == $user->id, 'Authenticated user could not be loaded.');
assertAuthTest($session->validateCsrf($session->csrfToken()), 'Valid CSRF token was rejected.');
assertAuthTest(!$session->validateCsrf('invalid-token'), 'Invalid CSRF token was accepted.');
$auth->logout();
assertAuthTest($auth->user() === null, 'Logout did not clear authentication.');

$inactive = new User();
$inactive->name = 'Conta Inativa';
$inactive->email = 'inativa@exemplo.com';
$inactive->password = 'senha-segura-inativa';
$inactive->status = 'inactive';
assertAuthTest($inactive->save(), 'Inactive user fixture creation failed.');
assertAuthTest(!$auth->login('inativa@exemplo.com', 'senha-segura-inativa'), 'Inactive account logged in.');

$pdo->exec('DELETE FROM auth_attempts');
for ($i = 0; $i < 5; $i++) {
    assertAuthTest(!$auth->login('teste@exemplo.com', 'senha-incorreta'), 'Invalid password was accepted.');
}
assertAuthTest(!$auth->login('teste@exemplo.com', 'senha-segura-de-teste'), 'Rate limit did not block the sixth attempt.');

$cookie = session_get_cookie_params();
assertAuthTest(
    $cookie['httponly'] === true
    && $cookie['secure'] === true
    && strtolower((string) $cookie['samesite']) === 'lax',
    'Session cookie protections are missing.'
);

session_unset();
session_destroy();
echo "Authentication tests passed.\n";
