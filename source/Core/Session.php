<?php

/**
 * Moves Code | Session
 *
 * Gerencia os dados de sessão da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */

namespace Moves\Core;

use MovesCode\Model\Message;

class Session
{
    private const INACTIVITY_TIMEOUT = 1800;

    /**
     * Inicia a sessão caso ainda não esteja ativa.
     */
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();

            $lastActivity = $_SESSION['last_activity'] ?? null;
            if (is_numeric($lastActivity) && time() - (int) $lastActivity > self::INACTIVITY_TIMEOUT) {
                $_SESSION = [];
                session_regenerate_id(true);
            }
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Retorna um valor armazenado na sessão.
     */
    public function __get(string $name): mixed
    {
        return !empty($_SESSION[$name]) ? $_SESSION[$name] : null;
    }

    /**
     * Verifica se uma chave existe na sessão.
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Retorna todos os dados da sessão como objeto.
     */
    public function all(): object
    {
        return (object) $_SESSION;
    }

    /**
     * Armazena um valor na sessão.
     */
    public function set(string $key, mixed $value): self
    {
        $_SESSION[$key] = is_array($value) ? (object) $value : $value;

        return $this;
    }

    /**
     * Remove um valor da sessão.
     */
    public function unset(string $key): self
    {
        unset($_SESSION[$key]);

        return $this;
    }

    /**
     * Verifica se uma chave possui valor definido na sessão.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Regenera o identificador da sessão.
     */
    public function regenerate(): self
    {
        session_regenerate_id(true);

        return $this;
    }

    /**
     * Encerra a sessão atual.
     */
    public function destroy(): self
    {
        session_destroy();

        return $this;
    }

    /**
     * Retorna e remove a mensagem flash da sessão.
     */
    public function flash(): ?Message
    {
        if (!$this->has('flash')) {
            return null;
        }

        $flash = $this->flash;
        $this->unset('flash');

        return $flash;
    }

    /**
     * Gera um novo token CSRF para a sessão.
     */
    public function csrf(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Retorna um token CSRF estável durante a sessão atual.
     */
    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $this->csrf();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Valida um token CSRF recebido em uma requisição.
     */
    public function validateCsrf(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && hash_equals($this->csrfToken(), $token);
    }
}
