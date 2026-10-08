<?php

/**
 * Moves Code | Assign First Superadmin
 *
 * Atribui o primeiro perfil superadmin após confirmação explícita no Terminal.
 *
 * @author Djalma Martins
 * @package Moves\CLI
 */

use Moves\Boot\Database;
use MovesCode\Model\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/index.php';

if (!Database::configureFromEnvironment()) {
    fwrite(STDERR, "Database configuration is unavailable.\n");
    exit(1);
}

if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
    fwrite(STDERR, "Run this command from an interactive Terminal.\n");
    exit(1);
}

fwrite(STDOUT, 'Email of the existing active account: ');
$email = strtolower(trim((string) fgets(STDIN)));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "A valid email is required.\n");
    exit(1);
}

fwrite(STDOUT, "This grants the first superadmin profile to {$email}.\n");
fwrite(STDOUT, "Type PROMOTE {$email} to confirm: ");
$confirmation = trim((string) fgets(STDIN));
if (!hash_equals('PROMOTE ' . $email, $confirmation)) {
    fwrite(STDERR, "Confirmation did not match; no profile was changed.\n");
    exit(1);
}

$pdo = Connection::get();
$lockName = 'moves_code_first_superadmin';
$lock = $pdo->prepare('SELECT GET_LOCK(:name, 5)');
$lock->execute(['name' => $lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    fwrite(STDERR, "Could not obtain the authorization lock; no profile was changed.\n");
    exit(1);
}

$failure = '';
try {
    $pdo->beginTransaction();
    $existingSuperadmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
    if ($existingSuperadmin !== 0) {
        $pdo->rollBack();
        $failure = "A superadmin already exists; no profile was changed.\n";
    } else {
        $update = $pdo->prepare(
            "UPDATE users SET role = 'superadmin' WHERE email = :email AND status = 'active'"
        );
        $update->execute(['email' => $email]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            $failure = "No matching active account was found; no profile was changed.\n";
        } else {
            $pdo->commit();
        }
    }
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $failure = "The profile could not be assigned; no success was reported.\n";
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
    $release->execute(['name' => $lockName]);
}

if ($failure !== '') {
    fwrite(STDERR, $failure);
    exit(1);
}

fwrite(STDOUT, "The confirmed existing account now has the first superadmin profile.\n");
