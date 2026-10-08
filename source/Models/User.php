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

use MovesCode\Model\Connection;
use MovesCode\Model\Model;

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
     * Busca usuários com limite e deslocamento para listagens administrativas.
     *
     * @return list<self>
     */
    public function searchPage(string $search, int $limit, int $offset): array
    {
        $search = mb_strtolower(trim($search), 'UTF-8');
        $query = $this->find();
        if ($search !== '') {
            $query = $this->find(
                'LOWER(name) LIKE :name_search OR LOWER(email) LIKE :email_search',
                ['name_search' => '%' . $search . '%', 'email_search' => '%' . $search . '%']
            );
        }

        return $query->order('name ASC')->limit($limit)->offset($offset)->fetch(true) ?: [];
    }

    /** Conta os resultados da pesquisa sem carregar a listagem inteira. */
    public function countSearch(string $search): int
    {
        $search = mb_strtolower(trim($search), 'UTF-8');
        if ($search === '') {
            return $this->find()->count();
        }

        return $this->find(
            'LOWER(name) LIKE :name_search OR LOWER(email) LIKE :email_search',
            ['name_search' => '%' . $search . '%', 'email_search' => '%' . $search . '%']
        )->count();
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
