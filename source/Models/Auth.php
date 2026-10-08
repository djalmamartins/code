<?php

/**
 * Moves Code | Auth
 *
 * Autentica usuários ativos e gerencia o estado de acesso na sessão.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use Moves\Core\Session;
use MovesCode\Model\Connection;
use PDO;

class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const PASSWORD_OPTIONS = ['cost' => 12];
    private const DUMMY_HASH = '$2y$12$Sbie8wlhNn.JF8LF0Y2ySOpHbaRh8VXZQuYE2ohvtibhJLxNHBjHG';

    private Session $session;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?? new Session();
    }

    /**
     * Confere credenciais e inicia uma sessão autenticada após regenerar o ID.
     */
    public function login(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        $identifier = $this->attemptIdentifier($email);
        $pdo = Connection::get();
        $this->pruneAttempts($pdo);

        if ($this->isRateLimited($pdo, $identifier)) {
            return false;
        }

        $user = (new User())->findByEmail($email);
        $hash = $user?->password;
        $passwordMatches = is_string($hash)
            ? password_verify($password, $hash)
            : password_verify($password, self::DUMMY_HASH);

        if (!$passwordMatches || !$user || $user->status !== 'active') {
            $this->recordFailure($pdo, $identifier);
            return false;
        }

        $pdo->prepare('DELETE FROM auth_attempts WHERE identifier = :identifier')->execute([
            'identifier' => $identifier,
        ]);

        if (password_needs_rehash($hash, PASSWORD_DEFAULT, self::PASSWORD_OPTIONS)) {
            $user->password = $password;
            if (!$user->save()) {
                return false;
            }
        }

        $this->session->regenerate();
        $this->session->csrf();
        $this->session->set('auth_user_id', $user->id);

        return true;
    }

    /**
     * Retorna o usuário autenticado somente enquanto estiver ativo.
     */
    public function user(): ?User
    {
        if (!$this->session->has('auth_user_id')) {
            return null;
        }

        $user = (new User())->findById((int) $this->session->auth_user_id);

        return $user instanceof User && $user->status === 'active' ? $user : null;
    }

    /**
     * Encerra a autenticação e invalida o identificador anterior da sessão.
     */
    public function logout(): void
    {
        $this->session->unset('auth_user_id');
        $this->session->regenerate();
        $this->session->csrf();
    }

    private function attemptIdentifier(string $email): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        return hash('sha256', $email . '|' . $ip);
    }

    private function pruneAttempts(PDO $pdo): void
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $pdo->prepare('DELETE FROM auth_attempts WHERE attempted_at < :cutoff')->execute([
            'cutoff' => $cutoff,
        ]);
    }

    private function isRateLimited(PDO $pdo, string $identifier): bool
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $query = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_attempts WHERE identifier = :identifier AND attempted_at >= :cutoff'
        );
        $query->execute(['identifier' => $identifier, 'cutoff' => $cutoff]);

        return (int) $query->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    private function recordFailure(PDO $pdo, string $identifier): void
    {
        $pdo->prepare(
            'INSERT INTO auth_attempts (identifier, attempted_at) VALUES (:identifier, :attempted_at)'
        )->execute(['identifier' => $identifier, 'attempted_at' => gmdate('Y-m-d H:i:s')]);
    }
}
