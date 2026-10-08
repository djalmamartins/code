<?php

/**
 * Moves Code | Database
 *
 * Configura a conexão PDO a partir das variáveis de ambiente do servidor.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

namespace Moves\Boot;

use MovesCode\Model\Connection;
use PDO;

class Database
{
    /**
     * Configura a conexão quando MOVES_DB_DSN está definido.
     *
     * @return bool Indica se a conexão foi configurada.
     */
    public static function configureFromEnvironment(): bool
    {
        $dsn = getenv('MOVES_DB_DSN');
        if ($dsn === false || trim($dsn) === '') {
            return false;
        }

        $user = getenv('MOVES_DB_USER');
        $password = getenv('MOVES_DB_PASSWORD');
        if ($user === false || $password === false) {
            return false;
        }

        $pdo = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        Connection::configure($pdo);

        return true;
    }
}
