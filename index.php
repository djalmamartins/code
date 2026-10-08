<?php

/**
 * Moves Code | Bootstrap
 *
 * Carrega as dependências, configurações e funções auxiliares da aplicação.
 *
 * @author Djalma Martins
 * @package Moves
 */

require __DIR__ . "/vendor/autoload.php";
try {
    \Moves\Boot\Environment::load();
} catch (\Throwable $exception) {
    error_log('Moves Code environment configuration failed.');
    if (PHP_SAPI === 'cli') {
        throw $exception;
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Application configuration unavailable.');
}
require __DIR__ . "/source/Boot/Config.php";
require __DIR__ . "/source/Boot/Helpers.php";
