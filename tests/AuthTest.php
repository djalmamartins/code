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
use Moves\Support\Totp;
use MovesCode\Model\Connection;

require dirname(__DIR__) . '/index.php';

function assertAuthTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function authTestTotpCode(string $secret, int $counter): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $buffer = 0; $bits = 0; $key = '';
    foreach (str_split($secret) as $char) {
        $buffer = ($buffer << 5) | strpos($alphabet, $char); $bits += 5;
        if ($bits >= 8) { $bits -= 8; $key .= chr(($buffer >> $bits) & 255); $buffer &= (1 << $bits) - 1; }
    }
    $hash = hash_hmac('sha1', pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000), $key, true);
    $offset = ord($hash[19]) & 15; $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
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
    role VARCHAR(16) NOT NULL DEFAULT 'user',
    last_login_at DATETIME NULL,
    totp_secret_encrypted VARCHAR(255) NULL,
    totp_enabled_at DATETIME NULL,
    last_totp_counter BIGINT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec('CREATE TABLE auth_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier CHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL
)');
$pdo->exec('CREATE TABLE user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, session_hash CHAR(64) UNIQUE, device_label TEXT, created_at DATETIME, last_activity_at DATETIME, revoked_at DATETIME)');
$pdo->exec('CREATE TABLE security_events (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER NULL, subject_user_id INTEGER NULL, event_type TEXT, created_at DATETIME)');
$pdo->exec('CREATE TABLE user_recovery_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, code_hash TEXT, used_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

$ephemeralKey = base64_encode(random_bytes(32));
putenv('MOVES_2FA_ENCRYPTION_KEY=' . $ephemeralKey);
$totp = new Totp();
$secret = $totp->generateSecret();
$encryptedSecret = $totp->encrypt($secret);
assertAuthTest($totp->decrypt($encryptedSecret) === $secret, 'Encrypted TOTP secret did not round-trip.');
$totpUser = new User();
$totpUser->name = 'Conta TOTP'; $totpUser->email = 'totp@exemplo.com'; $totpUser->password = 'senha-segura-totp'; $totpUser->status = 'active';
assertAuthTest($totpUser->save(), 'TOTP account fixture creation failed.');
$pdo->prepare('UPDATE users SET totp_secret_encrypted = :secret, totp_enabled_at = :enabled WHERE id = :id')->execute(['secret' => $encryptedSecret, 'enabled' => gmdate('Y-m-d H:i:s'), 'id' => $totpUser->id]);

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
$user->role = 'superadmin';
assertAuthTest($user->save(), 'User creation failed.');
assertAuthTest($user->role === 'user', 'A new account received a privileged role through User::save().');
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
assertAuthTest(!$auth->can('admin.access'), 'An active user received administrative access by default.');
assertAuthTest(!$auth->can('roles.manage'), 'A user received role-management permission by default.');
assertAuthTest(!$auth->can('articles.create') && !$auth->can('articles.edit') && !$auth->can('articles.publish') && !$auth->can('articles.delete'), 'A user received article permissions by default.');
assertAuthTest(!$auth->can('media.view') && !$auth->can('media.upload') && !$auth->can('media.edit') && !$auth->can('media.delete'), 'A user received media permissions by default.');
assertAuthTest(!$auth->can('settings.manage'), 'A user received CMS settings permission by default.');
assertAuthTest(!$auth->can('slides.view') && !$auth->can('slides.manage') && !$auth->can('testimonials.view') && !$auth->can('testimonials.manage') && !$auth->can('faqs.view') && !$auth->can('faqs.manage'), 'A user received editorial module permissions by default.');
$pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id")->execute(['id' => $user->id]);
assertAuthTest($auth->can('admin.access'), 'Admin profile was denied its explicit permission.');
assertAuthTest(!$auth->can('roles.manage'), 'Admin profile received superadmin-only permission.');
assertAuthTest($auth->can('pages.create') && $auth->can('pages.edit') && $auth->can('pages.publish'), 'Admin page permissions were not granted explicitly.');
assertAuthTest(!$auth->can('pages.delete'), 'Admin received page-deletion permission.');
assertAuthTest($auth->can('articles.create') && $auth->can('articles.edit') && $auth->can('articles.publish'), 'Admin article permissions were not granted explicitly.');
assertAuthTest(!$auth->can('articles.delete'), 'Admin received article-deletion permission.');
assertAuthTest($auth->can('media.view') && $auth->can('media.upload') && $auth->can('media.edit'), 'Admin media permissions were not granted explicitly.');
assertAuthTest(!$auth->can('media.delete'), 'Admin received media-deletion permission.');
assertAuthTest($auth->can('slides.view') && $auth->can('slides.manage') && $auth->can('testimonials.view') && $auth->can('testimonials.manage') && $auth->can('faqs.view') && $auth->can('faqs.manage'), 'Admin editorial module permissions were not granted explicitly.');
assertAuthTest($auth->can('settings.manage'), 'Admin settings permission was not granted explicitly.');
assertAuthTest($auth->can('versions.view') && !$auth->can('versions.publish'), 'Admin version permissions are incorrect.');
$mutatedUser = $auth->user();
$mutatedUser->role = 'superadmin';
assertAuthTest($mutatedUser->save(), 'Existing user update failed.');
assertAuthTest($mutatedUser->role === 'admin', 'User::save() allowed self-escalation to superadmin.');
$pdo->prepare("UPDATE users SET role = 'superadmin' WHERE id = :id")->execute(['id' => $user->id]);
assertAuthTest($auth->can('roles.manage'), 'Superadmin profile was denied explicit role-management permission.');
assertAuthTest($auth->can('versions.publish'), 'Superadmin version publishing permission was not granted.');
assertAuthTest($auth->can('pages.delete'), 'Superadmin was denied page-deletion permission.');
assertAuthTest($auth->can('articles.delete'), 'Superadmin was denied article-deletion permission.');
assertAuthTest($auth->can('media.view') && $auth->can('media.upload') && $auth->can('media.edit') && $auth->can('media.delete'), 'Superadmin media permissions were not granted explicitly.');
assertAuthTest($auth->can('slides.view') && $auth->can('slides.manage') && $auth->can('testimonials.view') && $auth->can('testimonials.manage') && $auth->can('faqs.view') && $auth->can('faqs.manage'), 'Superadmin editorial module permissions were not granted explicitly.');
$pdo->prepare("UPDATE users SET role = 'unknown' WHERE id = :id")->execute(['id' => $user->id]);
assertAuthTest(!$auth->can('admin.access'), 'Unknown roles were not denied by default.');

$totpAuth = new Auth($session);
assertAuthTest($totpAuth->login('totp@exemplo.com', 'senha-segura-totp'), 'Valid password did not start the 2FA pending state.');
assertAuthTest($totpAuth->hasPendingTwoFactor() && $totpAuth->user() === null, 'Pending 2FA state granted an authenticated session.');
$counter = intdiv(time(), 30);
$code = authTestTotpCode($secret, $counter);
assertAuthTest($totpAuth->completeTwoFactor($code), 'Valid TOTP did not complete authentication.');
assertAuthTest($totpAuth->user()?->id == $totpUser->id, 'TOTP completion did not establish the authenticated user.');
$totpAuth->logout();
assertAuthTest($totpAuth->login('totp@exemplo.com', 'senha-segura-totp'), 'Second TOTP login did not reach its pending state.');
assertAuthTest(!$totpAuth->completeTwoFactor($code), 'A previously accepted TOTP counter was replayed.');
$recoveryHash = password_hash('AB12CD34', PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO user_recovery_codes (user_id, code_hash) VALUES (:id, :hash)')->execute(['id' => $totpUser->id, 'hash' => $recoveryHash]);
assertAuthTest($totpAuth->completeTwoFactor('AB12CD34'), 'Unused recovery code was rejected.');
$totpAuth->logout();
assertAuthTest($totpAuth->login('totp@exemplo.com', 'senha-segura-totp'), 'Recovery-code replay test did not reach pending state.');
assertAuthTest(!$totpAuth->completeTwoFactor('AB12CD34'), 'A recovery code was accepted more than once.');
$totpAuth->logout();
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

$session->set('auth_user_id', $user->id);
$_SESSION['last_activity'] = time() - 1801;
$expiredSessionId = session_id();
session_write_close();
new Session();
assertAuthTest(session_id() !== $expiredSessionId, 'Expired session identifier was not rotated.');
assertAuthTest(!isset($_SESSION['auth_user_id']), 'Expired session retained authenticated state.');

session_unset();
session_destroy();
echo "Authentication tests passed.\n";
