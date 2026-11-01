<?php

/**
 * Moves Code | CMS Versions Controller
 *
 * Lista o histórico e registra releases dos produtos Moves.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsNotifications;
use Moves\Models\CmsVersions;
use Moves\Models\User;
use MovesCode\Model\Message;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use PDOException;
use Throwable;

class Versions extends Controller
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
        if (!$this->guard('versions.view')) { return; }
        $product = is_string($_GET['product'] ?? null) && isset(CmsVersions::PRODUCTS[$_GET['product']]) ? $_GET['product'] : 'studio';
        $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
        try {
            $versions = new CmsVersions();
            $total = $versions->countForProduct($product);
            $pager = (new Pager(url('admin/versions?product=' . rawurlencode($product) . '&page={page}')))->pager($total, 20, $page);
            $this->screen([
                'product' => $product,
                'products' => CmsVersions::PRODUCTS,
                'versions' => $versions->forProduct($product, $pager->limit(), $pager->offset()),
                'total' => $total,
                'pagination' => $pager->render(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function publish(): void
    {
        if (!$this->guard('versions.publish')) { return; }
        if (!$this->session->validateCsrf($this->postString('_token'))) { $this->forbidden(); return; }

        $product = $this->postString('product');
        $version = trim($this->postString('version_number'));
        $name = trim($this->postString('release_name'));
        $notes = trim($this->postString('notes'));
        if (!isset(CmsVersions::PRODUCTS[$product]) || preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D', $version) !== 1
            || $name === '' || mb_strlen($name, 'UTF-8') > 160 || mb_strlen($notes, 'UTF-8') > 5000
        ) {
            $this->screen(['product' => isset(CmsVersions::PRODUCTS[$product]) ? $product : 'studio', 'errors' => ['Informe produto, versão SemVer válida, nome da entrega e notas com até 5.000 caracteres.']], 422);
            return;
        }

        try {
            $actor = $this->auth->user();
            if (!$actor instanceof User) { $this->redirect('login'); return; }
            (new CmsVersions())->publish($product, $version, $name, $notes, (int) $actor->id);
            $this->auth->recordEvent((int) $actor->id, (int) $actor->id, 'cms_version_published');
            $this->session->set('flash', (new Message())->success('Versão registrada no histórico. Nenhum deploy foi executado.'));
            $this->redirect('admin/versions?product=' . rawurlencode($product));
        } catch (PDOException) {
            $this->screen(['product' => $product, 'errors' => ['Não foi possível registrar essa versão. Verifique se o número já existe para este produto.']], 422);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function screen(array $data, int $status = 200): void
    {
        $actor = $this->auth->user();
        $context = $actor instanceof User && $this->auth->can('notifications.view')
            ? (new CmsNotifications())->headerContext((int) $actor->id)
            : ['unreadCount' => 0, 'preview' => []];
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo $this->view->render('screen', $data + [
            'view' => 'versions', 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
            'products' => CmsVersions::PRODUCTS, 'versions' => [], 'total' => 0, 'pagination' => '',
            'canManageRoles' => $this->auth->can('roles.manage'), 'canManageUsers' => $this->auth->can('users.manage'),
            'canAccessAdmin' => $this->auth->can('admin.access'),
            'permissions' => ['versions' => ['view' => $this->auth->can('versions.view'), 'publish' => $this->auth->can('versions.publish')], 'notifications' => ['view' => $this->auth->can('notifications.view')]],
            'currentPath' => '/admin/versions', 'flash' => $this->session->flash()?->text(), 'errors' => [],
            'unreadCount' => $context['unreadCount'], 'notificationPreview' => $context['preview'],
        ]);
    }

    private function guard(string $permission): bool
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { $this->redirect('login'); return false; }
        if (!$this->auth->can($permission)) { $this->forbidden(); return false; }
        return true;
    }

    private function postString(string $key): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : ''; }
    private function redirect(string $path): void { header('Location: ' . url($path), true, 303); }
    private function forbidden(): void { http_response_code(403); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Acesso negado'; }
    private function unavailable(): void { http_response_code(503); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Serviço temporariamente indisponível'; }
}
