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
use Moves\Support\Totp;
use PDO;
use Throwable;

class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const PASSWORD_OPTIONS = ['cost' => 12];
    private const DUMMY_HASH = '$2y$12$Sbie8wlhNn.JF8LF0Y2ySOpHbaRh8VXZQuYE2ohvtibhJLxNHBjHG';
    private const ROLE_PERMISSIONS = [
        'superadmin' => ['admin.access', 'users.manage', 'roles.manage', 'pages.create', 'pages.edit', 'pages.publish', 'pages.delete', 'articles.create', 'articles.edit', 'articles.publish', 'articles.delete'],
        'admin' => ['admin.access', 'users.manage', 'pages.create', 'pages.edit', 'pages.publish', 'articles.create', 'articles.edit', 'articles.publish'],
        'user' => [],
    ];

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
            $this->recordEvent(null, $user instanceof User ? (int) $user->id : null, 'login_rejected');
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

        if ($user->totp_enabled_at !== null) {
            if ($this->session->has('auth_user_id')) { $this->revokeCurrentSession(); }
            $this->session->unset('auth_user_id');
            $this->session->regenerate();
            $this->session->set('auth_pending_user_id', $user->id);
            $this->session->set('auth_pending_at', time());
            $this->session->csrf();
            return true;
        }

        $this->establishSession($user);

        return true;
    }

    /** Indica se a senha foi validada e o segundo fator ainda está pendente. */
    public function hasPendingTwoFactor(): bool
    {
        if (!$this->session->has('auth_pending_user_id') || $this->session->has('auth_user_id')) {
            return false;
        }
        if (!is_numeric($this->session->auth_pending_at) || time() - (int) $this->session->auth_pending_at > 300) {
            $this->session->unset('auth_pending_user_id');
            $this->session->unset('auth_pending_at');
            return false;
        }
        return true;
    }

    /** Conclui a autenticação pendente com TOTP ou código de recuperação de uso único. */
    public function completeTwoFactor(string $code): bool
    {
        if (!$this->hasPendingTwoFactor()) {
            return false;
        }

        $user = (new User())->findById((int) $this->session->auth_pending_user_id);
        if (!$user instanceof User || $user->status !== 'active' || !is_string($user->totp_secret_encrypted)) {
            return false;
        }

        if (!$this->verifySecondFactor($user, $code)) {
            return false;
        }

        $this->establishSession($user);
        return true;
    }

    /** Valida e consome um TOTP não reutilizado ou um código de recuperação. */
    public function verifySecondFactor(User $user, string $code): bool
    {
        if (!is_string($user->totp_secret_encrypted)) {
            return false;
        }

        $pdo = Connection::get();
        $identifier = $this->attemptIdentifier('2fa:' . (string) $user->id);
        $this->pruneAttempts($pdo);
        if ($this->isRateLimited($pdo, $identifier)) {
            return false;
        }
        try {
            $totp = new Totp();
            $counter = $totp->counter($totp->decrypt($user->totp_secret_encrypted), preg_replace('/\s+/', '', $code) ?? '');
        } catch (Throwable) {
            $this->recordFailure($pdo, $identifier);
            $this->recordEvent(null, (int) $user->id, 'two_factor_rejected');
            return false;
        }

        if ($counter !== null && ($user->last_totp_counter === null || $counter > (int) $user->last_totp_counter)) {
            $stmt = $pdo->prepare('UPDATE users SET last_totp_counter = :counter WHERE id = :id AND (last_totp_counter IS NULL OR last_totp_counter < :counter)');
            $stmt->execute(['counter' => $counter, 'id' => $user->id]);
            $valid = $stmt->rowCount() === 1;
        } else {
            $valid = false;
        }

        if (!$valid) {
            $stmt = $pdo->prepare('SELECT id, code_hash FROM user_recovery_codes WHERE user_id = :id AND used_at IS NULL');
            $stmt->execute(['id' => $user->id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $recovery) {
                if (password_verify(strtoupper(trim($code)), $recovery['code_hash'])) {
                    $consume = $pdo->prepare('UPDATE user_recovery_codes SET used_at = :used WHERE id = :id AND used_at IS NULL');
                    $consume->execute(['used' => gmdate('Y-m-d H:i:s'), 'id' => $recovery['id']]);
                    $valid = $consume->rowCount() === 1;
                    break;
                }
            }
        }

        if ($valid) {
            $pdo->prepare('DELETE FROM auth_attempts WHERE identifier = :identifier')->execute(['identifier' => $identifier]);
            return true;
        }

        $this->recordFailure($pdo, $identifier);
        $this->recordEvent(null, (int) $user->id, 'two_factor_rejected');
        return false;
    }

    /** Rotaciona o identificador atual mantendo sua sessão rastreada. */
    public function rotateAuthenticatedSession(): void
    {
        $userId = $this->session->has('auth_user_id') ? (int) $this->session->auth_user_id : 0;
        $oldHash = hash('sha256', session_id());
        $this->session->regenerate();
        if ($userId > 0) {
            Connection::get()->prepare('UPDATE user_sessions SET session_hash = :new WHERE user_id = :user AND session_hash = :old AND revoked_at IS NULL')
                ->execute(['new' => hash('sha256', session_id()), 'user' => $userId, 'old' => $oldHash]);
        }
        $this->session->csrf();
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
        if (!$user instanceof User || $user->status !== 'active' || !$this->sessionIsValid((int) $user->id)) {
            return null;
        }

        return $user;
    }

    /** Verifica uma permissão explícita; papéis desconhecidos têm acesso negado. */
    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user instanceof User || $permission === '') {
            return false;
        }

        $permissions = self::ROLE_PERMISSIONS[(string) $user->role] ?? [];

        return in_array($permission, $permissions, true);
    }

    /**
     * Encerra a autenticação e invalida o identificador anterior da sessão.
     */
    public function logout(): void
    {
        $userId = $this->session->has('auth_user_id') ? (int) $this->session->auth_user_id : null;
        if ($userId !== null) {
            $this->revokeCurrentSession();
            $this->recordEvent($userId, $userId, 'logout');
        }
        $this->session->unset('auth_user_id');
        $this->session->unset('auth_pending_user_id');
        $this->session->unset('auth_pending_at');
        $this->session->regenerate();
        $this->session->csrf();
    }

    /** Persiste somente o hash do identificador de sessão atual. */
    private function establishSession(User $user): void
    {
        if ($this->session->has('auth_user_id')) { $this->revokeCurrentSession(); }
        $this->session->unset('auth_pending_user_id');
        $this->session->unset('auth_pending_at');
        $this->session->regenerate();
        $this->session->csrf();
        $this->session->set('auth_user_id', $user->id);
        $hash = hash('sha256', session_id());
        $now = gmdate('Y-m-d H:i:s');
        $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $device = str_contains(strtolower($agent), 'firefox') ? 'Firefox'
            : (str_contains(strtolower($agent), 'edg') ? 'Edge'
                : (str_contains(strtolower($agent), 'chrome') ? 'Chrome'
                    : (str_contains(strtolower($agent), 'safari') ? 'Safari' : 'Navegador')));
        $pdo = Connection::get();
        $pdo->prepare('INSERT INTO user_sessions (user_id, session_hash, device_label, created_at, last_activity_at) VALUES (:user, :hash, :device, :created, :activity)')
            ->execute(['user' => $user->id, 'hash' => $hash, 'device' => $device, 'created' => $now, 'activity' => $now]);
        $pdo->prepare('UPDATE users SET last_login_at = :at WHERE id = :id')->execute(['at' => $now, 'id' => $user->id]);
        $this->recordEvent((int) $user->id, (int) $user->id, 'login_success');
    }

    private function sessionIsValid(int $userId): bool
    {
        $hash = hash('sha256', session_id());
        $pdo = Connection::get();
        $check = $pdo->prepare('SELECT id FROM user_sessions WHERE user_id = :user AND session_hash = :hash AND revoked_at IS NULL LIMIT 1');
        $check->execute(['user' => $userId, 'hash' => $hash]);
        if ($check->fetchColumn() === false) {
            return false;
        }
        $stmt = $pdo->prepare('UPDATE user_sessions SET last_activity_at = :at WHERE user_id = :user AND session_hash = :hash AND revoked_at IS NULL');
        $stmt->execute(['at' => gmdate('Y-m-d H:i:s'), 'user' => $userId, 'hash' => $hash]);
        return true;
    }

    private function revokeCurrentSession(): void
    {
        Connection::get()->prepare('UPDATE user_sessions SET revoked_at = :at WHERE session_hash = :hash AND revoked_at IS NULL')
            ->execute(['at' => gmdate('Y-m-d H:i:s'), 'hash' => hash('sha256', session_id())]);
    }

    /** Registra somente o tipo de evento e os identificadores envolvidos. */
    public function recordEvent(?int $actorId, ?int $subjectId, string $event): void
    {
        $allowed = ['login_success', 'login_rejected', 'two_factor_rejected', 'logout', 'totp_enabled', 'totp_disabled', 'password_changed', 'user_role_changed', 'user_status_changed', 'profile_updated', 'user_created', 'user_updated'];
        if (!in_array($event, $allowed, true)) {
            return;
        }
        Connection::get()->prepare('INSERT INTO security_events (actor_user_id, subject_user_id, event_type, created_at) VALUES (:actor, :subject, :type, :created)')
            ->execute(['actor' => $actorId, 'subject' => $subjectId, 'type' => $event, 'created' => gmdate('Y-m-d H:i:s')]);
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
