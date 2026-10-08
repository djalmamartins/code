<?php

/**
 * Moves Code | Database Authentication Tests
 *
 * Valida User e Auth no banco configurado sem persistir fixtures.
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

function assertDatabaseAuthTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (!Database::configureFromEnvironment()) {
    fwrite(STDOUT, "Database authentication test skipped: database is not configured.\n");
    exit(0);
}

$pdo = Connection::get();
$pdo->beginTransaction();
session_name('moves_code_db_auth_test');
session_save_path(sys_get_temp_dir());
$_SERVER['HTTPS'] = 'on';
$_SERVER['REMOTE_ADDR'] = '192.0.2.20';
session_id('moves-code-db-auth-' . bin2hex(random_bytes(8)));

try {
    $session = new Session();
    $password = bin2hex(random_bytes(12));
    $email = 'code020-' . bin2hex(random_bytes(8)) . '@example.test';
    $user = new User();
    $user->name = 'Temporary CODE-020 Test';
    $user->email = $email;
    $user->password = $password;
    $user->status = 'active';
    $user->role = 'superadmin';
    assertDatabaseAuthTest($user->save(), 'Could not create transactional user fixture.');
    assertDatabaseAuthTest($user->role === 'user', 'New account was not assigned the least-privileged role.');

    $auth = new Auth($session);
    $sessionId = session_id();
    assertDatabaseAuthTest($auth->login($email, $password), 'Valid database-backed login failed.');
    assertDatabaseAuthTest(session_id() !== $sessionId, 'Database-backed login did not regenerate the session.');
    assertDatabaseAuthTest($auth->user()?->email === $email, 'Authenticated database user could not be loaded.');
    assertDatabaseAuthTest(!$auth->can('admin.access'), 'Active database user received admin access by default.');
    $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id")->execute(['id' => $user->id]);
    assertDatabaseAuthTest($auth->can('admin.access'), 'Database-backed admin permission was not recognized.');
    assertDatabaseAuthTest(!$auth->can('roles.manage'), 'Admin received a superadmin-only permission.');
    $loadedUser = $auth->user();
    $loadedUser->role = 'superadmin';
    assertDatabaseAuthTest($loadedUser->save(), 'Database-backed user update failed.');
    assertDatabaseAuthTest($loadedUser->role === 'admin', 'Database-backed User::save() allowed privilege escalation.');
    $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE id = :id")->execute(['id' => $user->id]);
    assertDatabaseAuthTest($auth->can('roles.manage'), 'Database-backed superadmin permission was not recognized.');
    $auth->logout();
    assertDatabaseAuthTest($auth->user() === null, 'Database-backed logout failed.');

    assertDatabaseAuthTest(
        !$auth->login('missing-' . bin2hex(random_bytes(8)) . '@example.test', bin2hex(random_bytes(12))),
        'Invalid database-backed credentials were accepted.'
    );

    $pdo->rollBack();
    echo "Database authentication tests passed; all fixture writes rolled back.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}
