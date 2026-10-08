<?php

/**
 * Moves Code | User
 *
 * Representa e valida contas de usuário persistidas pela aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Models
 */

namespace Moves\Models;

use MovesCode\Model\Model;
use MovesCode\Model\Connection;

class User extends Model
{
    private const MIN_PASSWORD_LENGTH = 12;
    private const MAX_PASSWORD_BYTES = 72;
    private const PASSWORD_OPTIONS = ['cost' => 12];

    public function __construct()
    {
        parent::__construct('users', ['id'], ['name', 'email', 'password', 'status', 'role']);
    }

    /**
     * Localiza uma conta pelo e-mail normalizado.
     */
    public function findByEmail(string $email): ?self
    {
        $email = mb_strtolower(trim($email), 'UTF-8');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $result = $this->find('LOWER(email) = :email', ['email' => $email])->fetch();

        return $result instanceof self ? $result : null;
    }

    /**
     * Valida os dados e salva a conta, armazenando somente o hash da senha.
     */
    public function save(): bool
    {
        $name = trim((string) ($this->name ?? ''));
        $email = mb_strtolower(trim((string) ($this->email ?? '')), 'UTF-8');
        $password = (string) ($this->password ?? '');
        $status = (string) ($this->status ?? 'active');
        $currentId = $this->id ?? null;

        if ($name === '' || mb_strlen($name, 'UTF-8') > 160
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || !in_array($status, ['active', 'inactive'], true)
        ) {
            return false;
        }

        $isPasswordHash = $currentId !== null && password_get_info($password)['algo'] !== null;
        if (!$isPasswordHash
            && (mb_strlen($password, 'UTF-8') < self::MIN_PASSWORD_LENGTH
                || strlen($password) > self::MAX_PASSWORD_BYTES)
        ) {
            return false;
        }

        $existing = $this->findByEmail($email);
        if ($existing !== null && (string) $existing->id !== (string) $currentId) {
            return false;
        }

        if ($currentId === null) {
            // New accounts never receive a privileged role through the User model.
            $role = 'user';
        } else {
            $roleQuery = Connection::get()->prepare('SELECT role FROM users WHERE id = :id');
            $roleQuery->execute(['id' => $currentId]);
            $storedRole = $roleQuery->fetchColumn();
            if (!is_string($storedRole)) {
                return false;
            }
            // Role changes are restricted to the explicit, confirmed CLI command.
            $role = $storedRole;
        }

        $this->name = $name;
        $this->email = $email;
        $this->status = $status;
        $this->role = $role;
        if (!$isPasswordHash || password_needs_rehash($password, PASSWORD_DEFAULT, self::PASSWORD_OPTIONS)) {
            $this->password = password_hash($password, PASSWORD_DEFAULT, self::PASSWORD_OPTIONS);
        }

        return parent::save();
    }

    /**
     * Retorna os campos permitidos para apresentação, sem o hash da senha.
     *
     * @return array{id: int|string|null, name: string|null, email: string|null, status: string|null, role: string|null}
     */
    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'role' => $this->role,
        ];
    }
}
