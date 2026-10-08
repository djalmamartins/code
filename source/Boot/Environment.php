<?php

/**
 * Moves Code | Environment
 *
 * Carrega variáveis locais do arquivo .env da raiz do projeto.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

namespace Moves\Boot;

use RuntimeException;

class Environment
{
    /**
     * Carrega o arquivo .env do projeto sem sobrescrever o ambiente do servidor.
     */
    public static function load(?string $projectRoot = null): void
    {
        $path = rtrim($projectRoot ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            return;
        }
        if (is_link($path)) {
            throw new RuntimeException('Application environment configuration is unavailable.');
        }

        $permissions = fileperms($path);
        if ($permissions === false || ($permissions & 0007) !== 0 || !is_readable($path)) {
            throw new RuntimeException('Application environment configuration is not private.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Application environment configuration is unavailable.');
        }

        foreach (self::parse($contents) as $name => $value) {
            if (getenv($name) !== false || array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    /**
     * Analisa linhas dotenv literais, sem expansão, execução ou interpolação.
     *
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $variables = [];
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                throw new RuntimeException('Invalid application environment configuration at line ' . ($index + 1) . '.');
            }

            $name = $matches[1];
            $rawValue = trim($matches[2]);
            if (array_key_exists($name, $variables)) {
                throw new RuntimeException('Duplicate application environment setting at line ' . ($index + 1) . '.');
            }

            if ($rawValue === '') {
                $value = '';
            } elseif ($rawValue[0] === '"' || $rawValue[0] === "'") {
                $quote = $rawValue[0];
                $pattern = $quote === '"'
                    ? '/^"((?:\\\\.|[^"\\\\])*)"(?:\s*(?:#.*)?)$/D'
                    : "/^'([^']*)'(?:\\s*(?:#.*)?)$/D";
                if (!preg_match($pattern, $rawValue, $quoted)) {
                    throw new RuntimeException('Invalid quoted application environment value at line ' . ($index + 1) . '.');
                }

                $value = $quoted[1];
                if ($quote === '"') {
                    $value = preg_replace_callback('/\\\\([\\\\"nrt])/', static fn (array $match): string => match ($match[1]) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        default => $match[1],
                    }, $value) ?? $value;
                }
            } else {
                $value = preg_replace('/\s+#.*$/', '', $rawValue) ?? $rawValue;
                if (str_contains($value, '"') || str_contains($value, "'")) {
                    throw new RuntimeException('Invalid unquoted application environment value at line ' . ($index + 1) . '.');
                }
                $value = trim($value);
            }

            $variables[$name] = $value;
        }

        return $variables;
    }
}
