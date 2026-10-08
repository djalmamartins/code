<?php

/**
 * Moves Code | Theme Loader
 *
 * Carrega com segurança a definição do tema ativo da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

namespace Moves\Boot;

use RuntimeException;

class ThemeLoader
{
    /**
     * Carrega e valida a definição de um tema configurado.
     *
     * @param array{name: string, version: string}|null $selection
     * @return array{type: string, name: string, version: string, controller: class-string, routes: callable}
     */
    public static function load(string $type, ?array $selection = null): array
    {
        $selection ??= APP_THEMES[$type] ?? null;

        if (!is_array($selection)) {
            throw new RuntimeException("Tema não configurado.");
        }

        $name = $selection["name"] ?? null;
        $version = $selection["version"] ?? null;

        if (!self::validIdentifier($type)
            || !is_string($name)
            || !self::validIdentifier($name)
            || !is_string($version)
            || !self::validIdentifier($version)
        ) {
            throw new RuntimeException("Identificação de tema inválida.");
        }

        $themeRoot = realpath(dirname(__DIR__, 2) . "/container/{$type}");
        $themeFile = realpath(dirname(__DIR__, 2) . "/container/{$type}/{$name}/{$version}/theme.php");

        if ($themeRoot === false
            || $themeFile === false
            || !str_starts_with($themeFile, $themeRoot . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException("Tema não encontrado.");
        }

        $definition = require $themeFile;

        if (!is_array($definition)
            || ($definition["type"] ?? null) !== $type
            || ($definition["name"] ?? null) !== $name
            || ($definition["version"] ?? null) !== $version
            || !is_string($definition["controller"] ?? null)
            || !class_exists($definition["controller"])
            || !is_callable($definition["routes"] ?? null)
        ) {
            throw new RuntimeException("Definição de tema inválida.");
        }

        return $definition;
    }

    private static function validIdentifier(string $value): bool
    {
        return preg_match('/^[a-z][a-z0-9-]*$/', $value) === 1;
    }
}
