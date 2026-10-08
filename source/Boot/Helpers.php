<?php

/**
 * Moves Code | Helpers
 *
 * Disponibiliza funções auxiliares compartilhadas pela aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

function url(?string $path = null): string
{
    $base = rtrim(APP_URL, "/");

    return $path === null || $path === ""
        ? $base
        : $base . "/" . ltrim($path, "/");
}
