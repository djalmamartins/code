<?php

/**
 * Moves Code | CMS Pages
 *
 * Gerencia criação, edição, publicação e exclusão de páginas do CMS.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Auth;
use Moves\Models\Page;
use Moves\Models\MediaItem;
use Moves\Models\User;
use Moves\Support\HtmlSanitizer;
use MovesCode\Model\Message;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use Throwable;

class Pages extends Controller
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
        if (!$this->guard('pages.edit')) { return; }
        try {
            $search = $this->queryString('q');
            $pageNumber = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
            $pages = new Page();
            $pager = (new Pager(url('admin/pages?q=' . rawurlencode($search) . '&page={page}')))
                ->pager($pages->countSearch($search), 20, $pageNumber);
            $this->screen('pages', [
                'pages' => $pages->searchPage($search, $pager->limit(), $pager->offset()),
                'search' => $search,
                'pagination' => $pager->render(),
                'permissions' => $this->permissions(),
            ]);
        } catch (Throwable) {
            $this->unavailable();
        }
    }

    public function createForm(): void
    {
        if (!$this->guard('pages.create')) { return; }
        $this->form(null, []);
    }

    public function create(): void
    {
        if (!$this->guard('pages.create') || !$this->csrf()) { return; }
        $data = $this->validatedInput();
        if ($data['errors'] !== []) { $this->form($data['record'], $data['errors'], 422); return; }

        try {
            $page = new Page();
            if ($page->slugExists($data['record']['slug'])) {
                $this->form($data['record'], ['Este slug já está sendo usado por outra página.'], 422);
                return;
            }
            $actor = $this->auth->user();
            if (!$actor instanceof User) { $this->redirect('login'); return; }
            $id = $page->savePage($data['record'] + ['author_id' => (int) $actor->id]);
            $this->successFlash('Página criada como rascunho.');
            $this->redirect('admin/pages/' . $id . '/edit');
        } catch (Throwable) {
            $this->form($data['record'], ['Não foi possível salvar a página. Verifique se o slug continua disponível.'], 422);
        }
    }

    public function editForm(array $route): void
    {
        if (!$this->guard('pages.edit')) { return; }
        $page = (new Page())->findPage((int) ($route['id'] ?? 0));
        if ($page === null) { $this->notFound(); return; }
        $this->form($page, []);
    }

    public function update(array $route): void
    {
        if (!$this->guard('pages.edit') || !$this->csrf()) { return; }
        $id = (int) ($route['id'] ?? 0);
        $page = new Page();
        $existing = $page->findPage($id);
        if ($existing === null) { $this->notFound(); return; }
        $data = $this->validatedInput();
        $data['record']['id'] = $id;
        $data['record']['status'] = $existing['status'];
        $data['record']['published_at'] = $existing['published_at'];
        if ($data['errors'] !== []) { $this->form($data['record'], $data['errors'], 422); return; }
        if ($page->slugExists($data['record']['slug'], $id)) {
            $this->form($data['record'], ['Este slug já está sendo usado por outra página.'], 422);
            return;
        }

        try {
            $page->savePage($data['record'], $id);
            $this->successFlash('Página atualizada.');
            $this->redirect('admin/pages/' . $id . '/edit');
        } catch (Throwable) {
            $this->form($data['record'], ['Não foi possível atualizar a página. Verifique se o slug continua disponível.'], 422);
        }
    }

    public function publish(array $route): void
    {
        if (!$this->guard('pages.publish') || !$this->csrf()) { return; }
        $id = (int) ($route['id'] ?? 0);
        try {
            $page = new Page();
            if ($page->findPage($id) === null) { $this->notFound(); return; }
            $page->setPublished($id, true);
            $this->successFlash('Página publicada.');
            $this->redirect('admin/pages');
        } catch (Throwable) { $this->unavailable(); }
    }

    public function unpublish(array $route): void
    {
        if (!$this->guard('pages.publish') || !$this->csrf()) { return; }
        $id = (int) ($route['id'] ?? 0);
        try {
            $page = new Page();
            if ($page->findPage($id) === null) { $this->notFound(); return; }
            $page->setPublished($id, false);
            $this->successFlash('Página retirada do site.');
            $this->redirect('admin/pages');
        } catch (Throwable) { $this->unavailable(); }
    }

    public function delete(array $route): void
    {
        if (!$this->guard('pages.delete') || !$this->csrf()) { return; }
        try {
            if (!(new Page())->deletePage((int) ($route['id'] ?? 0))) { $this->notFound(); return; }
            $this->successFlash('Página excluída.');
            $this->redirect('admin/pages');
        } catch (Throwable) { $this->unavailable(); }
    }

    private function validatedInput(): array
    {
        $title = trim($this->postString('title'));
        $slug = trim($this->postString('slug'));
        $content = $this->postString('content');
        if ($slug === '') { $slug = str_slug($title); }
        $errors = [];
        if ($title === '' || mb_strlen($title, 'UTF-8') > 200) { $errors[] = 'Informe um título com até 200 caracteres.'; }
        if (strlen($slug) > 200 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) { $errors[] = 'Informe um slug com letras minúsculas, números e hífens.'; }
        if (strlen($content) > 1_000_000) { $errors[] = 'O conteúdo excede o limite de 1 MB.'; }

        try {
            $safeContent = (new HtmlSanitizer())->purifyWithMediaImages($content);
            preg_match_all('~src="/media/([1-9][0-9]*)"~', $safeContent, $mediaMatches);
            foreach (array_unique($mediaMatches[1] ?? []) as $mediaId) {
                if ((new MediaItem())->findMedia((int) $mediaId) === null) {
                    $errors[] = 'O conteúdo contém uma imagem que não existe na biblioteca.';
                    break;
                }
            }
        }
        catch (Throwable) { $safeContent = ''; $errors[] = 'A sanitização do conteúdo está indisponível; nada foi salvo.'; }

        return [
            'record' => ['title' => $title, 'slug' => $slug, 'content' => $safeContent],
            'errors' => $errors,
        ];
    }

    private function form(?array $record, array $errors, int $status = 200): void
    {
        $editing = is_array($record) && isset($record['id']);
        $this->screen('page-form', [
            'record' => $record,
            'errors' => $errors,
            'editing' => $editing,
            'formAction' => $editing ? 'admin/pages/' . (int) $record['id'] : 'admin/pages',
            'permissions' => $this->permissions(),
        ], $status);
    }

    private function permissions(): array
    {
        return [
            'create' => $this->auth->can('pages.create'),
            'edit' => $this->auth->can('pages.edit'),
            'publish' => $this->auth->can('pages.publish'),
            'delete' => $this->auth->can('pages.delete'),
        ];
    }

    private function screen(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $actor = $this->auth->user();
        $flash = $this->session->flash();
        echo $this->view->render('screen', $data + [
            'view' => $view,
            'csrf' => $this->session->csrfToken(),
            'actor' => $actor?->publicData(),
            'canManageRoles' => $this->auth->can('roles.manage'),
            'canManageUsers' => $this->auth->can('users.manage'),
            'canAccessAdmin' => $this->auth->can('admin.access'),
            'currentPath' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
            'flash' => $flash?->text(),
            'errors' => $errors ?? [],
            'tab' => 'general',
            'record' => null,
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

    private function successFlash(string $text): void
    {
        $this->session->set('flash', (new Message())->success($text));
    }

    private function queryString(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? mb_substr($_GET[$key], 0, 160, 'UTF-8') : '';
    }

    private function postString(string $key): string
    {
        return is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    }

    private function redirect(string $path): void { header('Location: ' . url($path), true, 303); }
    private function forbidden(): void { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Acesso negado'; }
    private function notFound(): void { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Não encontrado'; }
    private function unavailable(): void { http_response_code(503); header('Cache-Control: no-store'); header('Content-Type: text/plain; charset=utf-8'); echo 'Serviço temporariamente indisponível'; }
}
