<?php

/**
 * Moves Code | CMS Notifications Controller
 *
 * Lista e marca somente notificações pertencentes à conta autenticada.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsNotifications;
use Moves\Models\User;
use MovesCode\Model\Message;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use Throwable;

class Notifications extends Controller
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
        try {
            $user = $this->auth->user();
            $filter = is_string($_GET['filter'] ?? null) && in_array($_GET['filter'], ['all', 'unread', 'read'], true) ? $_GET['filter'] : 'all';
            $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
            $notifications = new CmsNotifications();
            $total = $notifications->count((int) $user->id, $filter);
            $pager = (new Pager(url('admin/notifications?filter=' . rawurlencode($filter) . '&page={page}')))->pager($total, 20, $page);
            $this->screen([
                'items' => $notifications->search((int) $user->id, $filter, $pager->limit(), $pager->offset()),
                'total' => $total, 'filter' => $filter, 'pagination' => $pager->render(),
                'unreadCount' => $notifications->unreadCount((int) $user->id),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function markRead(array $route): void
    {
        if (!$this->guard()) { return; }
        if (!$this->session->validateCsrf($this->postString('_token'))) { $this->forbidden(); return; }
        try {
            $user = $this->auth->user();
            if (!(new CmsNotifications())->markRead((int) ($route['id'] ?? 0), (int) $user->id)) { $this->notFound(); return; }
            $this->session->set('flash', (new Message())->success('Notificação marcada como lida.'));
            $this->redirect('admin/notifications');
        } catch (Throwable) { $this->unavailable(); }
    }

    public function markAllRead(): void
    {
        if (!$this->guard()) { return; }
        if (!$this->session->validateCsrf($this->postString('_token'))) { $this->forbidden(); return; }
        try {
            $user = $this->auth->user();
            (new CmsNotifications())->markAllRead((int) $user->id);
            $this->session->set('flash', (new Message())->success('Notificações marcadas como lidas.'));
            $this->redirect('admin/notifications');
        } catch (Throwable) { $this->unavailable(); }
    }

    private function guard(): bool
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { $this->redirect('login'); return false; }
        if (!$this->auth->can('notifications.view')) { $this->forbidden(); return false; }
        return true;
    }

    private function screen(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $actor = $this->auth->user();
        $flash = $this->session->flash();
        $permissions = [
            'edit' => $this->auth->can('pages.edit'), 'view' => $this->auth->can('media.view'),
            'upload' => $this->auth->can('media.upload'), 'delete' => $this->auth->can('media.delete'),
            'notifications' => ['view' => $this->auth->can('notifications.view')],
            'slides' => ['view' => $this->auth->can('slides.view')], 'testimonials' => ['view' => $this->auth->can('testimonials.view')],
            'faqs' => ['view' => $this->auth->can('faqs.view')], 'settings' => ['manage' => $this->auth->can('settings.manage')],
        ];
        $actorContext = $actor instanceof User ? (new CmsNotifications())->headerContext((int) $actor->id) : ['unreadCount' => 0, 'preview' => []];
        echo $this->view->render('screen', $data + [
            'view' => 'notifications', 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
            'permissions' => $permissions, 'canManageRoles' => $this->auth->can('roles.manage'),
            'canManageUsers' => $this->auth->can('users.manage'), 'canAccessAdmin' => $this->auth->can('admin.access'),
            'currentPath' => '/admin/notifications', 'flash' => $flash?->text(), 'errors' => [],
            'notificationPreview' => $actorContext['preview'],
        ]);
    }

    private function postString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function redirect(string $path): void
    {
        header('Location: ' . url($path), true, 303);
    }

    private function forbidden(): void
    {
        http_response_code(403); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Acesso negado';
    }

    private function notFound(): void
    {
        http_response_code(404); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Não encontrado';
    }

    private function unavailable(): void
    {
        http_response_code(503); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Serviço temporariamente indisponível';
    }
}
