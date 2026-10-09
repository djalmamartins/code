<?php

/**
 * Moves Code | CMS System Logs Controller
 *
 * Exibe eventos de auditoria em modo somente leitura.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsAuditLog;
use Moves\Models\CmsNotifications;
use Moves\Models\User;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use Throwable;

class SystemLogs extends Controller
{
    private Session $session;
    private Auth $auth;

    public function __construct(?Router $router = null)
    {
        parent::__construct(dirname(__DIR__));
        $this->session = new Session();
        $this->auth = new Auth($this->session);
    }

    public function index(): void
    {
        if (!$this->guard()) { return; }
        $event = is_string($_GET['event'] ?? null) ? substr(trim($_GET['event']), 0, 64) : '';
        $from = $this->date($_GET['from'] ?? null);
        $to = $this->date($_GET['to'] ?? null);
        $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
        try {
            $result = (new CmsAuditLog())->search($event, $from, $to, 30, ($page - 1) * 30);
            $pager = (new Pager(url('admin/system-logs?event=' . rawurlencode($event) . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&page={page}')))->pager($result['total'], 30, $page);
            $actor = $this->auth->user();
            $notificationContext = $actor instanceof User && $this->auth->can('notifications.view')
                ? (new CmsNotifications())->headerContext((int) $actor->id)
                : ['unreadCount' => 0, 'preview' => []];
            http_response_code(200); header('Cache-Control: no-store'); header('Content-Type: text/html; charset=utf-8');
            echo $this->view->render('screen', [
                'view' => 'system-logs', 'events' => $result['items'], 'total' => $result['total'],
                'event' => $event, 'from' => $from, 'to' => $to, 'pagination' => $pager->render(),
                'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
                'canManageUsers' => $this->auth->can('users.manage'), 'canManageRoles' => $this->auth->can('roles.manage'),
                'canAccessAdmin' => $this->auth->can('admin.access'), 'currentPath' => '/admin/system-logs',
                'permissions' => ['logs' => ['view' => $this->auth->can('logs.view')], 'notifications' => ['view' => $this->auth->can('notifications.view')]],
                'flash' => null, 'errors' => [], 'unreadCount' => $notificationContext['unreadCount'],
                'notificationPreview' => $notificationContext['preview'],
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function date(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) { return ''; }
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') === $value ? $value : '';
    }

    private function guard(): bool
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { header('Location: ' . url('login'), true, 303); return false; }
        if (!$this->auth->can('logs.view')) { http_response_code(403); echo 'Acesso negado'; return false; }
        return true;
    }

    private function unavailable(): void { http_response_code(503); header('Cache-Control: no-store'); echo 'Serviço temporariamente indisponível'; }
}
