<?php

/**
 * Moves Code | CMS User Management
 *
 * Gerencia contas, perfil pessoal, sessões, atividade e segurança TOTP.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth as AuthService;
use Moves\Models\CmsReports;
use Moves\Models\CmsNotifications;
use Moves\Models\User;
use Moves\Support\Totp;
use MovesCode\Model\Connection;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use PDO;
use Throwable;

class Users extends Controller
{
    private Session $session;
    private AuthService $auth;

    public function __construct(?Router $router = null)
    {
        $theme = dirname(__DIR__);
        parent::__construct($theme);
        $this->session = new Session();
        $this->auth = new AuthService($this->session);
    }

    public function dashboard(): void
    {
        if (!$this->guard('admin.access')) { return; }
        $stats = null;
        $overview = null;
        $statsError = false;
        $overviewError = false;
        if ($this->auth->can('users.manage')) {
            try {
                $stats = Connection::get()->query("SELECT COUNT(*) total, SUM(status='active') active, SUM(role='superadmin' AND status='active') superadmins FROM users")->fetch(PDO::FETCH_ASSOC);
                $stats = $stats ?: ['total' => 0, 'active' => 0, 'superadmins' => 0];
            } catch (Throwable) {
                $statsError = true;
            }
        }
        try { $overview = (new CmsReports())->summary(null, null); }
        catch (Throwable) { $overviewError = true; }
        $this->screen('dashboard', [
            'stats' => $stats,
            'overview' => $overview,
            'statsError' => $statsError,
            'overviewError' => $overviewError,
            'system' => [
                'name' => getenv('APP_NAME') ?: 'Moves Code',
                'environment' => getenv('APP_ENV') ?: 'local',
                'php' => PHP_VERSION,
            ],
        ]);
    }

    public function placeholder(): void
    {
        if (!$this->guard('admin.access')) { return; }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
        $pages = [
            '/admin/reports' => ['Relatórios', 'VISÃO GERAL'],
            '/admin/notifications' => ['Notificações', 'VISÃO GERAL'],
            '/admin/proposals' => ['Propostas', 'VISÃO GERAL'],
            '/admin/pages' => ['Páginas', 'CONTEÚDO'],
            '/admin/blog' => ['Artigos', 'CONTEÚDO'],
            '/admin/media' => ['Mídia', 'CONTEÚDO'],
            '/admin/slides' => ['Destaques', 'CONTEÚDO'],
            '/admin/testimonials' => ['Depoimentos', 'CONTEÚDO'],
            '/admin/faqs' => ['FAQ', 'CONTEÚDO'],
            '/admin/settings' => ['Configurações', 'GESTÃO'],
            '/admin/system-logs' => ['Log', 'GESTÃO'],
        ];
        if (!isset($pages[$path])) { $this->notFound(); return; }

        $this->screen('placeholder', ['pageTitle' => $pages[$path][0], 'pageGroup' => $pages[$path][1]]);
    }

    public function editorDemo(): void
    {
        if (!$this->guard('admin.access')) { return; }
        $this->screen('editor-demo');
    }

    public function index(): void
    {
        if (!$this->guard('users.manage')) { return; }
        $search = $this->queryString('q');
        $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
        $pager = (new Pager(url('admin/users?q=' . rawurlencode($search) . '&page={page}')))
            ->pager((new User())->countSearch($search), 20, $page);
        $this->screen('users', [
            'users' => (new User())->searchPage($search, $pager->limit(), $pager->offset()),
            'search' => $search,
            'pagination' => $pager->render(),
        ]);
    }

    public function createForm(): void
    {
        if (!$this->guard('users.manage')) { return; }
        $this->screen('user-form', ['record' => null, 'errors' => [], 'rolesAllowed' => $this->auth->can('roles.manage')]);
    }

    public function create(): void
    {
        if (!$this->guard('users.manage') || !$this->csrf()) { return; }
        $name = $this->postString('name');
        $email = mb_strtolower(trim($this->postString('email')), 'UTF-8');
        $password = $this->postString('password');
        $role = $this->auth->can('roles.manage') ? $this->validRole($this->postString('role')) : 'user';
        $errors = $this->validateUser($name, $email, null);
        if (strlen($password) < 12 || strlen($password) > 72) { $errors[] = 'A senha deve ter entre 12 e 72 bytes.'; }
        if ($errors !== []) {
            $this->screen('user-form', ['record' => ['name' => $name, 'email' => $email, 'role' => $role], 'errors' => $errors, 'rolesAllowed' => $this->auth->can('roles.manage')], 422);
            return;
        }
        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->status = 'active';
        if (!$user->save()) { $this->screen('user-form', ['record' => ['name' => $name, 'email' => $email, 'role' => $role], 'errors' => ['Não foi possível salvar o usuário.'], 'rolesAllowed' => $this->auth->can('roles.manage')], 422); return; }
        $id = (int) $user->id;
        if ($role !== 'user') {
            Connection::get()->prepare('UPDATE users SET role = :role WHERE id = :id')->execute(['role' => $role, 'id' => $id]);
        }
        $this->audit('user_created', $id);
        $this->redirect('admin/users/' . $id);
    }

    public function show(array $data): void
    {
        if (!$this->guard('users.manage')) { return; }
        $user = (new User())->findById((int) ($data['id'] ?? 0));
        if (!$user instanceof User) { $this->notFound(); return; }
        $tab = $this->queryString('tab', 'general');
        $extra = [];
        if ($tab === 'sessions') {
            $stmt = Connection::get()->prepare('SELECT device_label, created_at, last_activity_at, revoked_at FROM user_sessions WHERE user_id = :id ORDER BY last_activity_at DESC LIMIT 100');
            $stmt->execute(['id' => $user->id]); $extra['sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($tab === 'activity' && $this->auth->can('roles.manage')) {
            $stmt = Connection::get()->prepare('SELECT event_type, created_at FROM security_events WHERE subject_user_id = :id ORDER BY created_at DESC LIMIT 100');
            $stmt->execute(['id' => $user->id]); $extra['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $this->screen('user-profile', ['record' => $user->publicData(), 'lastLogin' => $user->last_login_at ?? null, 'totpEnabled' => $user->totp_enabled_at !== null, 'tab' => $tab, 'rolesAllowed' => $this->auth->can('roles.manage'), 'showActivity' => $this->auth->can('roles.manage')] + $extra);
    }

    public function update(array $data): void
    {
        if (!$this->guard('users.manage') || !$this->csrf()) { return; }
        $id = (int) ($data['id'] ?? 0);
        $actor = $this->auth->user();
        $current = (new User())->findById($id);
        if (!$current instanceof User || !$actor instanceof User) { $this->notFound(); return; }
        $name = trim($this->postString('name'));
        $email = mb_strtolower(trim($this->postString('email')), 'UTF-8');
        $status = $this->postString('status') === 'inactive' ? 'inactive' : 'active';
        $role = (string) $current->role;
        if ($this->auth->can('roles.manage')) { $role = $this->validRole($this->postString('role')); }
        if ((int) $actor->id === $id && ($role !== $current->role || $status !== $current->status)) {
            $this->screen('user-profile', ['record' => $current->publicData(), 'tab' => 'general', 'errors' => ['Não é permitido alterar o próprio papel ou status.'], 'rolesAllowed' => $this->auth->can('roles.manage')], 403); return;
        }
        $errors = $this->validateUser($name, $email, $id);
        if ($errors !== []) { $this->screen('user-profile', ['record' => ['id' => $id, 'name' => $name, 'email' => $email, 'status' => $status, 'role' => $role], 'tab' => 'general', 'errors' => $errors, 'rolesAllowed' => $this->auth->can('roles.manage')], 422); return; }

        $pdo = Connection::get();
        try {
            $pdo->beginTransaction();
            $lock = $pdo->query("SELECT id FROM users WHERE role='superadmin' AND status='active' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if ($current->role === 'superadmin' && $current->status === 'active' && ($role !== 'superadmin' || $status !== 'active') && count($lock) <= 1) {
                $pdo->rollBack();
                $this->screen('user-profile', ['record' => $current->publicData(), 'tab' => 'general', 'errors' => ['O último superadmin ativo não pode ser removido ou desativado.'], 'rolesAllowed' => $this->auth->can('roles.manage')], 422); return;
            }
            $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, status = :status, role = :role WHERE id = :id');
            $stmt->execute(['name' => $name, 'email' => $email, 'status' => $status, 'role' => $role, 'id' => $id]);
            if ($status === 'inactive') {
                $pdo->prepare('UPDATE user_sessions SET revoked_at = :at WHERE user_id = :id AND revoked_at IS NULL')->execute(['at' => gmdate('Y-m-d H:i:s'), 'id' => $id]);
            }
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $this->screen('user-profile', ['record' => $current->publicData(), 'tab' => 'general', 'errors' => ['Não foi possível atualizar o usuário.'], 'rolesAllowed' => $this->auth->can('roles.manage')], 422); return;
        }
        $event = $current->role !== $role ? 'user_role_changed' : ($current->status !== $status ? 'user_status_changed' : 'user_updated');
        $this->audit($event, $id);
        $this->redirect('admin/users/' . $id);
    }

    public function profile(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User) { return; }
        $pendingUri = null;
        $manualSecret = null;
        if ($this->session->has('totp_setup_secret')) {
            try { $manualSecret = (new Totp())->decrypt((string) $this->session->totp_setup_secret); $pendingUri = (new Totp())->provisioningUri($manualSecret, (string) $user->email); } catch (Throwable) { $pendingUri = null; $manualSecret = null; }
        }
        $recoveryCodes = $this->session->has('new_recovery_codes') ? (array) $this->session->new_recovery_codes : [];
        $this->session->unset('new_recovery_codes');
        $qr = $pendingUri !== null ? (new QRCode(new QROptions(['outputBase64' => true])))->render($pendingUri) : null;
        $tab = $this->queryString('tab', 'general');
        $extra = [];
        if ($tab === 'sessions') {
            $stmt = Connection::get()->prepare('SELECT session_hash, device_label, created_at, last_activity_at, revoked_at FROM user_sessions WHERE user_id = :id ORDER BY last_activity_at DESC LIMIT 100');
            $stmt->execute(['id' => $user->id]); $extra = ['sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'currentHash' => hash('sha256', session_id())];
        } elseif ($tab === 'activity') {
            $stmt = Connection::get()->prepare('SELECT event_type, created_at FROM security_events WHERE subject_user_id = :id ORDER BY created_at DESC LIMIT 100');
            $stmt->execute(['id' => $user->id]); $extra['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => $tab, 'lastLogin' => $user->last_login_at ?? null, 'totpEnabled' => $user->totp_enabled_at !== null, 'pendingUri' => $pendingUri, 'manualSecret' => $manualSecret, 'qr' => $qr, 'recoveryCodes' => $recoveryCodes] + $extra);
    }

    public function updateProfile(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User || !$this->csrf()) { return; }
        $name = trim($this->postString('name'));
        $email = mb_strtolower(trim($this->postString('email')), 'UTF-8');
        $errors = $this->validateUser($name, $email, (int) $user->id);
        if ($errors !== []) { $this->screen('self-profile', ['record' => ['id' => $user->id, 'name' => $name, 'email' => $email], 'tab' => 'general', 'errors' => $errors], 422); return; }
        Connection::get()->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id')->execute(['name' => $name, 'email' => $email, 'id' => $user->id]);
        $this->audit('profile_updated', (int) $user->id);
        $this->redirect('profile');
    }

    public function changePassword(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User || !$this->csrf()) { return; }
        $current = $this->postString('current_password'); $password = $this->postString('password');
        if (!password_verify($current, (string) $user->password) || strlen($password) < 12 || strlen($password) > 72) { $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'security', 'errors' => ['Confirme sua senha atual e informe uma nova senha válida.']], 422); return; }
        Connection::get()->prepare('UPDATE users SET password = :password WHERE id = :id')->execute(['password' => password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]), 'id' => $user->id]);
        Connection::get()->prepare('UPDATE user_sessions SET revoked_at = :at WHERE user_id = :id AND session_hash <> :hash AND revoked_at IS NULL')->execute(['at' => gmdate('Y-m-d H:i:s'), 'id' => $user->id, 'hash' => hash('sha256', session_id())]);
        $this->audit('password_changed', (int) $user->id);
        $this->auth->rotateAuthenticatedSession();
        $this->redirect('profile?tab=security');
    }

    public function sessions(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User) { return; }
        $stmt = Connection::get()->prepare('SELECT session_hash, device_label, created_at, last_activity_at, revoked_at FROM user_sessions WHERE user_id = :id ORDER BY last_activity_at DESC LIMIT 100');
        $stmt->execute(['id' => $user->id]);
        $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'sessions', 'sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'currentHash' => hash('sha256', session_id())]);
    }

    public function revokeOtherSessions(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User || !$this->csrf()) { return; }
        Connection::get()->prepare('UPDATE user_sessions SET revoked_at = :at WHERE user_id = :id AND session_hash <> :hash AND revoked_at IS NULL')->execute(['at' => gmdate('Y-m-d H:i:s'), 'id' => $user->id, 'hash' => hash('sha256', session_id())]);
        $this->redirect('profile/sessions');
    }

    public function activity(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User) { return; }
        $stmt = Connection::get()->prepare('SELECT event_type, created_at FROM security_events WHERE subject_user_id = :id ORDER BY created_at DESC LIMIT 100');
        $stmt->execute(['id' => $user->id]);
        $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'activity', 'events' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function beginTwoFactor(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User || !$this->csrf()) { return; }
        if (!password_verify($this->postString('current_password'), (string) $user->password)) { $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'security', 'errors' => ['A senha atual não confere.']], 422); return; }
        try { $secret = (new Totp())->generateSecret(); $encrypted = (new Totp())->encrypt($secret); }
        catch (Throwable) { $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'security', 'errors' => ['A configuração de 2FA está indisponível neste ambiente.']], 503); return; }
        $this->session->set('totp_setup_secret', $encrypted);
        $this->redirect('profile?tab=security');
    }

    public function confirmTwoFactor(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User || !$this->csrf()) { return; }
        try {
            $pdo = Connection::get();
            $identifier = hash('sha256', 'totp-setup|' . (string) $user->id . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $cutoff = gmdate('Y-m-d H:i:s', time() - 900);
            $pdo->prepare('DELETE FROM auth_attempts WHERE attempted_at < :cutoff')->execute(['cutoff' => $cutoff]);
            $limit = $pdo->prepare('SELECT COUNT(*) FROM auth_attempts WHERE identifier = :id AND attempted_at >= :cutoff');
            $limit->execute(['id' => $identifier, 'cutoff' => $cutoff]);
            if ((int) $limit->fetchColumn() >= 5) { throw new \RuntimeException('TOTP setup is rate limited.'); }
            $totp = new Totp();
            $encrypted = (string) $this->session->totp_setup_secret;
            $secret = $totp->decrypt($encrypted);
            $counter = $totp->counter($secret, $this->postString('code'));
            if ($counter === null) {
                $pdo->prepare('INSERT INTO auth_attempts (identifier, attempted_at) VALUES (:id, :at)')->execute(['id' => $identifier, 'at' => gmdate('Y-m-d H:i:s')]);
                throw new \RuntimeException('Invalid TOTP setup code.');
            }
            $codes = [];
            for ($i = 0; $i < 8; $i++) { $codes[] = strtoupper(bin2hex(random_bytes(5))); }
            $pdo->prepare('DELETE FROM auth_attempts WHERE identifier = :id')->execute(['id' => $identifier]);
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET totp_secret_encrypted = :secret, totp_enabled_at = :at, last_totp_counter = :counter WHERE id = :id')->execute(['secret' => $encrypted, 'at' => gmdate('Y-m-d H:i:s'), 'counter' => $counter, 'id' => $user->id]);
            $insert = $pdo->prepare('INSERT INTO user_recovery_codes (user_id, code_hash) VALUES (:user, :hash)');
            foreach ($codes as $code) { $insert->execute(['user' => $user->id, 'hash' => password_hash($code, PASSWORD_DEFAULT)]); }
            $pdo->commit();
            $this->session->unset('totp_setup_secret'); $this->session->set('new_recovery_codes', $codes);
            $this->audit('totp_enabled', (int) $user->id);
            Connection::get()->prepare('UPDATE user_sessions SET revoked_at = :at WHERE user_id = :id AND session_hash <> :hash AND revoked_at IS NULL')->execute(['at' => gmdate('Y-m-d H:i:s'), 'id' => $user->id, 'hash' => hash('sha256', session_id())]);
            $this->auth->rotateAuthenticatedSession();
            $this->redirect('profile?tab=security');
        } catch (Throwable) {
            if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
            $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'security', 'pendingSecret' => $this->session->totp_setup_secret, 'errors' => ['Código inválido ou configuração temporariamente indisponível.']], 422);
        }
    }

    public function disableTwoFactor(): void
    {
        $user = $this->requireUser(); if (!$user instanceof User || !$this->csrf()) { return; }
        if (!password_verify($this->postString('current_password'), (string) $user->password) || !$this->auth->verifySecondFactor($user, $this->postString('code'))) {
            $this->screen('self-profile', ['record' => $user->publicData(), 'tab' => 'security', 'totpEnabled' => true, 'errors' => ['Senha ou segundo fator inválido.']], 422); return;
        }
        $pdo = Connection::get(); $pdo->beginTransaction();
        $pdo->prepare('UPDATE user_recovery_codes SET used_at = :at WHERE user_id = :id AND used_at IS NULL')->execute(['at' => gmdate('Y-m-d H:i:s'), 'id' => $user->id]);
        $pdo->prepare('UPDATE users SET totp_secret_encrypted = NULL, totp_enabled_at = NULL, last_totp_counter = NULL WHERE id = :id')->execute(['id' => $user->id]);
        $pdo->commit(); $this->audit('totp_disabled', (int) $user->id); $this->auth->rotateAuthenticatedSession(); $this->redirect('profile?tab=security');
    }

    private function screen(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status); header('Cache-Control: no-store'); header('Content-Type: text/html; charset=utf-8');
        $actor = $this->auth->user();
        $message = $this->session->flash();
        $data += ['view' => $view, 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(), 'canManageRoles' => $this->auth->can('roles.manage'), 'canManageUsers' => $this->auth->can('users.manage'), 'canAccessAdmin' => $this->auth->can('admin.access'), 'permissions' => ['edit' => $this->auth->can('pages.edit'), 'view' => $this->auth->can('media.view'), 'upload' => $this->auth->can('media.upload'), 'delete' => $this->auth->can('media.delete'), 'notifications' => ['view' => $this->auth->can('notifications.view')]], 'currentPath' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/', 'flash' => $message?->text(), 'errors' => [], 'tab' => 'general', 'record' => null];
        if ($actor instanceof User && $this->auth->can('notifications.view')) { $data += (new CmsNotifications())->headerContext((int) $actor->id); }
        echo $this->view->render('screen', $data);
    }

    private function guard(string $permission): bool
    {
        $user = $this->requireUser();
        if (!$user instanceof User) { return false; }
        if (!$this->auth->can($permission)) { $this->forbidden(); return false; }
        return true;
    }

    private function requireUser(): ?User
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return null; }
        if (!$user instanceof User) { $this->redirect('login'); return null; }
        return $user;
    }

    private function csrf(): bool
    {
        if ($this->session->validateCsrf($this->postString('_token'))) { return true; }
        $this->forbidden(); return false;
    }

    private function validateUser(string $name, string $email, ?int $exclude): array
    {
        $errors = [];
        if ($name === '' || mb_strlen($name, 'UTF-8') > 160) { $errors[] = 'Informe um nome com até 160 caracteres.'; }
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $errors[] = 'Informe um e-mail válido.'; }
        $existing = (new User())->findByEmail($email);
        if ($existing instanceof User && (int) $existing->id !== $exclude) { $errors[] = 'Este e-mail já está cadastrado.'; }
        return $errors;
    }

    private function validRole(string $role): string { return in_array($role, ['superadmin', 'admin', 'user'], true) ? $role : 'user'; }
    private function audit(string $event, int $subject): void { $actor = $this->auth->user(); $this->auth->recordEvent($actor ? (int) $actor->id : null, $subject, $event); }
    private function postString(string $key): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
    private function queryString(string $key, string $default = ''): string { return is_string($_GET[$key] ?? null) ? substr($_GET[$key], 0, 160) : $default; }
    private function redirect(string $path): void { header('Location: ' . url($path), true, 303); }
    private function forbidden(): void { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Acesso negado'; }
    private function notFound(): void { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Não encontrado'; }
    private function unavailable(): void { http_response_code(503); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Serviço temporariamente indisponível'; }
}
