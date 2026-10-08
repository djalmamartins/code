<?php

/**
 * Moves Code | Environment Tests
 *
 * Valida a leitura segura e a precedência das variáveis de ambiente.
 *
 * @author Djalma Martins
 * @package Moves\Tests
 */

use Moves\Boot\Environment;

require dirname(__DIR__) . '/index.php';

function assertEnvironmentTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$parsed = Environment::parse("# comment\nAPP_NAME=\"Moves Code\" # inline comment\nEMPTY=\nSINGLE='literal # value'\nESCAPED=\"one\\ntwo\"\n");
assertEnvironmentTest($parsed['APP_NAME'] === 'Moves Code', 'Quoted value or comment parsing failed.');
assertEnvironmentTest($parsed['EMPTY'] === '', 'Empty value parsing failed.');
assertEnvironmentTest($parsed['SINGLE'] === 'literal # value', 'Single-quoted value parsing failed.');
assertEnvironmentTest($parsed['ESCAPED'] === "one\ntwo", 'Double-quoted escape parsing failed.');

$projectRoot = sys_get_temp_dir() . '/moves-code-env-' . bin2hex(random_bytes(8));
mkdir($projectRoot, 0700);
file_put_contents($projectRoot . '/.env', "MOVES_CODE_ENV_TEST_FROM_FILE=from-file\nMOVES_CODE_ENV_TEST_PRECEDENCE=file-value\n");
chmod($projectRoot . '/.env', 0600);
putenv('MOVES_CODE_ENV_TEST_PRECEDENCE=server-value');
Environment::load($projectRoot);
assertEnvironmentTest(getenv('MOVES_CODE_ENV_TEST_FROM_FILE') === 'from-file', '.env value was not loaded.');
assertEnvironmentTest(getenv('MOVES_CODE_ENV_TEST_PRECEDENCE') === 'server-value', 'Server environment was overwritten.');

$invalidRejected = false;
try {
    Environment::parse("SAFE=value\nnot-a-valid-setting\n");
} catch (RuntimeException $exception) {
    $invalidRejected = !str_contains($exception->getMessage(), 'value');
}
assertEnvironmentTest($invalidRejected, 'Malformed configuration was not safely rejected.');

unlink($projectRoot . '/.env');
rmdir($projectRoot);
putenv('MOVES_CODE_ENV_TEST_FROM_FILE');
putenv('MOVES_CODE_ENV_TEST_PRECEDENCE');
unset($_ENV['MOVES_CODE_ENV_TEST_FROM_FILE'], $_ENV['MOVES_CODE_ENV_TEST_PRECEDENCE']);
unset($_SERVER['MOVES_CODE_ENV_TEST_FROM_FILE'], $_SERVER['MOVES_CODE_ENV_TEST_PRECEDENCE']);

echo "Environment tests passed.\n";
