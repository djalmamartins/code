<?php

/**
 * Moves Code | CMS Media
 *
 * Gerencia metadados e entrega protegida da biblioteca privada de imagens.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\CmsNotifications;
use Moves\Models\MediaItem;
use Moves\Models\User;
use Moves\Support\MediaStorage;
use MovesCode\Model\Connection;
use MovesCode\Model\Message;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use Throwable;

class Media extends Controller
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
        if (!$this->guard('media.view')) { return; }
        $this->listing('media');
    }

    /** Endpoint HTML reutilizável para seleção futura nos formulários editoriais. */
    public function picker(): void
    {
        if (!$this->guard('media.view')) { return; }
        $this->listing('media-picker');
    }

    public function upload(): void
    {
        if (!$this->guard('media.upload') || !$this->csrf()) { return; }
        $storage = new MediaStorage();
        $title = trim($this->postString('title'));
        $altText = trim($this->postString('alt_text'));
        if (($title !== '' && mb_strlen($title, 'UTF-8') > 200)
            || mb_strlen($altText, 'UTF-8') > 300
        ) {
            $this->screen('media', ['errors' => ['Título deve ter até 200 caracteres e texto alternativo até 300.']], 422);
            return;
        }

        $stored = null;
        try {
            $uploaded = $_FILES['image'] ?? [];
            if (!is_array($uploaded)) { throw new \RuntimeException('Selecione uma imagem para enviar.'); }
            $stored = $storage->store($uploaded);
            $actor = $this->auth->user();
            if (!$actor instanceof User) { throw new \RuntimeException('Sessão indisponível.'); }
            $id = (new MediaItem())->create([
                'original_name' => $stored['original_name'],
                'stored_name' => basename($stored['storage_path']),
                'storage_path' => $stored['storage_path'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
                'title' => $title !== '' ? $title : $stored['title'],
                'alt_text' => $altText,
                'uploaded_by' => (int) $actor->id,
            ]);
            $this->flash('Imagem adicionada à biblioteca.');
            $this->redirect('admin/media/' . $id . '/edit');
        } catch (Throwable $exception) {
            if (is_array($stored) && is_string($stored['storage_path'] ?? null)) {
                try { $storage->delete($stored['storage_path']); } catch (Throwable) { /* Mantém erro genérico ao usuário. */ }
            }
            $message = $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'Não foi possível registrar a imagem. Nenhum arquivo de teste foi mantido.';
            $this->screen('media', ['errors' => [$message]], 422);
        }
    }

    public function editForm(array $route): void
    {
        if (!$this->guard('media.edit')) { return; }
        try {
            $item = (new MediaItem())->findMedia((int) ($route['id'] ?? 0));
            if ($item === null) { $this->notFound(); return; }
            $this->screen('media-detail', ['item' => $item]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function update(array $route): void
    {
        if (!$this->guard('media.edit') || !$this->csrf()) { return; }
        $id = (int) ($route['id'] ?? 0);
        $title = trim($this->postString('title'));
        $altText = trim($this->postString('alt_text'));
        if ($title === '' || mb_strlen($title, 'UTF-8') > 200 || mb_strlen($altText, 'UTF-8') > 300) {
            try {
                $item = (new MediaItem())->findMedia($id);
                if ($item === null) { $this->notFound(); return; }
                $this->screen('media-detail', [
                    'item' => $item,
                    'errors' => ['Informe título de até 200 caracteres e texto alternativo de até 300.'],
                ], 422);
            } catch (Throwable) { $this->unavailable(); }
            return;
        }
        try {
            $media = new MediaItem();
            if ($media->findMedia($id) === null) { $this->notFound(); return; }
            $media->updateDescription($id, $title, $altText);
            $this->flash('Descrição da imagem atualizada.');
            $this->redirect('admin/media/' . $id . '/edit');
        } catch (Throwable) { $this->unavailable(); }
    }

    /** Entrega imagens privadas somente a usuários autorizados no CMS. */
    public function file(array $route): void
    {
        if (!$this->guard('media.view')) { return; }
        try {
            $item = (new MediaItem())->findMedia((int) ($route['id'] ?? 0));
            if ($item === null) { $this->notFound(); return; }
            $this->deliver($item, true);
        } catch (Throwable) { $this->notFound(); }
    }

    /** Entrega mídia somente quando estiver vinculada a conteúdo público ativo. */
    public function publicFile(array $route): void
    {
        try {
            $id = (int) ($route['id'] ?? 0);
            $item = (new MediaItem())->findMedia($id);
            if ($item === null || !(new \Moves\Models\CmsContent())->isPublicMedia($id, gmdate('Y-m-d H:i:s'))) { $this->notFound(); return; }
            $this->deliver($item, false);
        } catch (Throwable) { $this->notFound(); }
    }

    private function deliver(array $item, bool $private): void
    {
        if (!in_array($item['mime_type'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) { $this->notFound(); return; }
        $path = (new MediaStorage())->resolve((string) ($item['storage_path'] ?? ''));
        header('Content-Type: ' . $item['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . basename((string) ($item['stored_name'] ?? 'image')) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Referrer-Policy: no-referrer');
        header($private ? 'Cache-Control: private, no-store' : 'Cache-Control: public, max-age=300');
        readfile($path);
    }

    public function delete(array $route): void
    {
        if (!$this->guard('media.delete') || !$this->csrf()) { return; }
        $id = (int) ($route['id'] ?? 0);
        $storage = new MediaStorage();
        $pendingFile = null;
        $pdo = null;
        try {
            $pdo = Connection::get();
            $media = new MediaItem();
            $item = $media->findMedia($id);
            if ($item === null) { $this->notFound(); return; }
            if ($media->isReferenced($id)) {
                $this->screen('media-detail', [
                    'item' => $item,
                    'errors' => ['Esta imagem está associada a uma página ou artigo e não pode ser excluída.'],
                ], 409);
                return;
            }
            $pdo->beginTransaction();
            $pendingFile = $storage->quarantine((string) $item['storage_path']);
            if (!$media->deleteMedia($id)) { throw new \RuntimeException('Não foi possível remover os metadados.'); }
            $pdo->commit();
            $storage->removeQuarantine($pendingFile);
            $this->flash('Imagem removida da biblioteca.');
            $this->redirect('admin/media');
        } catch (Throwable) {
            if ($pdo instanceof \PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
            if (is_array($pendingFile)) { $storage->restoreQuarantine($pendingFile); }
            $this->unavailable();
        }
    }

    private function listing(string $view): void
    {
        try {
            $query = $this->queryString('q');
            $type = $this->queryString('type');
            if (!in_array($type, ['all', 'jpeg', 'png', 'webp'], true)) { $type = 'all'; }
            $pageNumber = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
            $media = new MediaItem();
            $url = $view === 'media-picker' ? 'admin/media/picker' : 'admin/media';
            $pager = (new Pager(url($url . '?q=' . rawurlencode($query) . '&type=' . rawurlencode($type) . '&page={page}')))
                ->pager($media->countSearch($query, $type), 24, $pageNumber);
            $this->screen($view, [
                'items' => $media->search($query, $type, $pager->limit(), $pager->offset()),
                'search' => $query,
                'type' => $type,
                'pagination' => $pager->render(),
                'total' => $media->countSearch($query, $type),
                'permissions' => $this->permissions(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function screen(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $actor = $this->auth->user();
        $flash = $this->session->flash();
        if ($actor instanceof User && $this->auth->can('notifications.view')) { $data += (new CmsNotifications())->headerContext((int) $actor->id); }
        echo $this->view->render('screen', $data + [
            'view' => $view, 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
            'permissions' => $this->permissions(),
            'canManageRoles' => $this->auth->can('roles.manage'), 'canManageUsers' => $this->auth->can('users.manage'),
            'canAccessAdmin' => $this->auth->can('admin.access'),
            'currentPath' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
            'flash' => $flash?->text(), 'errors' => [], 'tab' => 'general', 'record' => null,
        ]);
    }

    private function guard(string $permission): bool
    {
        try { $user = $this->auth->user(); } catch (Throwable) { $this->unavailable(); return false; }
        if (!$user instanceof User) { $this->redirect('login'); return false; }
        if (!$this->auth->can($permission)) { $this->forbidden(); return false; }
        return true;
    }

    private function csrf(): bool
    {
        if ($this->session->validateCsrf($this->postString('_token'))) { return true; }
        $this->forbidden();
        return false;
    }

    private function permissions(): array
    {
        return [
            'view' => $this->auth->can('media.view'),
            'upload' => $this->auth->can('media.upload'),
            'edit' => $this->auth->can('media.edit'),
            'delete' => $this->auth->can('media.delete'),
        ];
    }

    private function postString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function queryString(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? mb_substr($_GET[$key], 0, 160, 'UTF-8') : '';
    }

    private function flash(string $message): void
    {
        $this->session->set('flash', (new Message())->success($message));
    }

    private function redirect(string $path): void
    {
        header('Location: ' . url($path), true, 303);
    }

    private function forbidden(): void
    {
        http_response_code(403);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso negado';
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não encontrado';
    }

    private function unavailable(): void
    {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Biblioteca de mídia temporariamente indisponível';
    }
}
