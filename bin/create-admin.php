<?php

/**
 * Moves Code | Create Admin
 *
 * Cria interativamente a primeira conta ativa usada na área administrativa.
 *
 * @author Djalma Martins
 * @package Moves\CLI
 */

use Moves\Boot\Database;
use Moves\Models\User;

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

$readSecret = static function (string $prompt): string {
    fwrite(STDOUT, $prompt);
    passthru('/bin/stty -echo 2>/dev/null', $status);
    if ($status !== 0) {
        fwrite(STDERR, "Could not safely read the password.\n");
        exit(1);
    }

    try {
        $value = fgets(STDIN);
    } finally {
        passthru('/bin/stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);
    }

    return trim((string) $value);
};

fwrite(STDOUT, 'Name: ');
$name = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Email: ');
$email = trim((string) fgets(STDIN));
$password = $readSecret('Password (12–72 bytes): ');
$confirmation = $readSecret('Confirm password: ');

if (strlen($password) < 12 || strlen($password) > 72 || !hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Invalid account details or password confirmation.\n");
    exit(1);
}

$user = new User();
$user->name = $name;
$user->email = $email;
$user->password = $password;
$user->status = 'active';

if (!$user->save()) {
    fwrite(STDERR, "The account could not be created. Verify the name, email, and whether the email is already registered.\n");
    exit(1);
}

fwrite(STDOUT, "Active account created.\n");
