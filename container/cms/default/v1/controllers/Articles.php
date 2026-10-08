<?php

/**
 * Moves Code | CMS Articles
 *
 * Gerencia artigos, categorias, publicação e exclusão no CMS.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Cms\DefaultV1\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Controller;
use Moves\Core\Session;
use Moves\Models\Article;
use Moves\Models\ArticleCategory;
use Moves\Models\Auth;
use Moves\Models\User;
use Moves\Support\HtmlSanitizer;
use MovesCode\Model\Message;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use Throwable;

class Articles extends Controller
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
        if (!$this->guard('articles.edit')) { return; }
        try {
            $search = $this->queryString('q');
            $pageNumber = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
            $articles = new Article();
            $pager = (new Pager(url('admin/blog?q=' . rawurlencode($search) . '&page={page}')))
                ->pager($articles->countSearch($search), 20, $pageNumber);
            $this->screen('articles', [
                'articles' => $articles->search($search, $pager->limit(), $pager->offset()),
                'search' => $search,
                'pagination' => $pager->render(),
                'permissions' => $this->permissions(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function createForm(): void
    {
        if (!$this->guard('articles.create')) { return; }
        $this->form(null, []);
    }

    public function create(): void
    {
        if (!$this->guard('articles.create') || !$this->csrf()) { return; }
        $data = $this->validatedInput();
        if ($data['errors'] !== []) { $this->form($data['record'], $data['errors'], 422); return; }

        try {
            $articles = new Article();
            if ($articles->slugExists($data['record']['slug'])) {
                $this->form($data['record'], ['Este slug já está sendo usado por outro artigo.'], 422);
                return;
            }
            $actor = $this->auth->user();
            if (!$actor instanceof User) { $this->redirect('login'); return; }
            $id = $articles->saveArticle($data['record'] + ['author_id' => (int) $actor->id]);
            $this->flash('Artigo salvo como rascunho.');
            $this->redirect('admin/blog/' . $id . '/edit');
        } catch (Throwable) {
            $this->form($data['record'], ['Não foi possível salvar o artigo. Verifique se o slug continua disponível.'], 422);
        }
    }

    public function editForm(array $route): void
    {
        if (!$this->guard('articles.edit')) { return; }
        try {
            $article = (new Article())->findArticle((int) ($route['id'] ?? 0));
            if ($article === null) { $this->notFound(); return; }
            $this->form($article, []);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function update(array $route): void
    {
        if (!$this->guard('articles.edit') || !$this->csrf()) { return; }
        $id = (int) ($route['id'] ?? 0);
        try {
            $articles = new Article();
            $existing = $articles->findArticle($id);
            if ($existing === null) { $this->notFound(); return; }
            $data = $this->validatedInput();
            $data['record']['id'] = $id;
            $data['record']['status'] = $existing['status'];
            $data['record']['published_at'] = $existing['published_at'];
            if ($data['errors'] !== []) { $this->form($data['record'], $data['errors'], 422); return; }
            if ($articles->slugExists($data['record']['slug'], $id)) {
                $this->form($data['record'], ['Este slug já está sendo usado por outro artigo.'], 422);
                return;
            }
            $articles->saveArticle($data['record'], $id);
            $this->flash('Artigo atualizado.');
            $this->redirect('admin/blog/' . $id . '/edit');
        } catch (Throwable) {
            $this->form($data['record'] ?? [], ['Não foi possível atualizar o artigo. Verifique os dados e a categoria.'], 422);
        }
    }

    public function publish(array $route): void
    {
        $this->changePublication($route, true);
    }

    public function unpublish(array $route): void
    {
        $this->changePublication($route, false);
    }

    private function changePublication(array $route, bool $published): void
    {
        if (!$this->guard('articles.publish') || !$this->csrf()) { return; }
        try {
            $id = (int) ($route['id'] ?? 0);
            $articles = new Article();
            if ($articles->findArticle($id) === null) { $this->notFound(); return; }
            $articles->setPublished($id, $published);
            $this->flash($published ? 'Artigo publicado.' : 'Artigo retirado do blog.');
            $this->redirect('admin/blog');
        } catch (Throwable) { $this->unavailable(); }
    }

    public function delete(array $route): void
    {
        if (!$this->guard('articles.delete') || !$this->csrf()) { return; }
        try {
            if (!(new Article())->deleteArticle((int) ($route['id'] ?? 0))) { $this->notFound(); return; }
            $this->flash('Artigo excluído.');
            $this->redirect('admin/blog');
        } catch (Throwable) { $this->unavailable(); }
    }

    public function categories(): void
    {
        if (!$this->guard('articles.edit')) { return; }
        try {
            $this->screen('article-categories', [
                'categories' => (new ArticleCategory())->all(),
                'record' => [],
                'editing' => false,
                'permissions' => $this->permissions(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function editCategoryForm(array $route): void
    {
        if (!$this->guard('articles.edit')) { return; }
        try {
            $category = (new ArticleCategory())->findCategory((int) ($route['id'] ?? 0));
            if ($category === null) { $this->notFound(); return; }
            $this->screen('article-categories', [
                'categories' => (new ArticleCategory())->all(),
                'record' => $category,
                'editing' => true,
                'permissions' => $this->permissions(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    public function saveCategory(array $route = []): void
    {
        if (!$this->guard('articles.edit') || !$this->csrf()) { return; }
        $id = isset($route['id']) ? (int) $route['id'] : null;
        $name = trim($this->postString('name'));
        $slug = trim($this->postString('slug'));
        $description = trim($this->postString('description'));
        if ($slug === '') { $slug = str_slug($name); }
        $errors = [];
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) { $errors[] = 'Informe um nome com até 120 caracteres.'; }
        if ($slug === '' || strlen($slug) > 140 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) { $errors[] = 'Informe um slug válido.'; }
        if (mb_strlen($description, 'UTF-8') > 500) { $errors[] = 'A descrição deve ter até 500 caracteres.'; }

        try {
            $categories = new ArticleCategory();
            $existing = $id === null ? null : $categories->findCategory($id);
            if ($id !== null && $existing === null) { $this->notFound(); return; }
            $record = ['name' => $name, 'slug' => $slug, 'description' => $description];
            if ($errors === [] && $categories->slugExists($slug, $id)) { $errors[] = 'Este slug já está em uso.'; }
            if ($errors !== []) {
                http_response_code(422);
                $this->screen('article-categories', [
                    'categories' => $categories->all(), 'record' => $record + ($id === null ? [] : ['id' => $id]),
                    'editing' => $id !== null, 'permissions' => $this->permissions(), 'errors' => $errors,
                ], 422);
                return;
            }
            $categories->saveCategory($record, $id);
            $this->flash($id === null ? 'Categoria criada.' : 'Categoria atualizada.');
            $this->redirect('admin/blog/categories');
        } catch (Throwable) { $this->unavailable(); }
    }

    public function deleteCategory(array $route): void
    {
        if (!$this->guard('articles.edit') || !$this->csrf()) { return; }
        try {
            $id = (int) ($route['id'] ?? 0);
            $categories = new ArticleCategory();
            if ($categories->findCategory($id) === null) { $this->notFound(); return; }
            if ($categories->articleCount($id) > 0) {
                $this->screen('article-categories', [
                    'categories' => $categories->all(), 'record' => [], 'editing' => false,
                    'permissions' => $this->permissions(), 'errors' => ['Esta categoria possui artigos e não pode ser removida.'],
                ], 409);
                return;
            }
            $categories->deleteCategory($id);
            $this->flash('Categoria removida.');
            $this->redirect('admin/blog/categories');
        } catch (Throwable) { $this->unavailable(); }
    }

    private function validatedInput(): array
    {
        $title = trim($this->postString('title'));
        $slug = trim($this->postString('slug'));
        $excerpt = trim($this->postString('excerpt'));
        $content = $this->postString('content');
        $cover = trim($this->postString('cover_image'));
        $categoryId = filter_var($this->postString('category_id'), FILTER_VALIDATE_INT);
        if ($slug === '') { $slug = str_slug($title); }
        $errors = [];
        if ($title === '' || mb_strlen($title, 'UTF-8') > 200) { $errors[] = 'Informe um título com até 200 caracteres.'; }
        if ($slug === '' || strlen($slug) > 200 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) { $errors[] = 'Informe um slug válido, com minúsculas, números e hífens.'; }
        if ($excerpt === '' || mb_strlen($excerpt, 'UTF-8') > 500) { $errors[] = 'Informe um resumo com até 500 caracteres.'; }
        if ($categoryId === false || $categoryId < 1 || (new ArticleCategory())->findCategory((int) $categoryId) === null) { $errors[] = 'Selecione uma categoria existente.'; }
        if (strlen($content) > 1_000_000) { $errors[] = 'O conteúdo excede o limite de 1 MB.'; }
        if ($cover !== '' && (strlen($cover) > 500 || filter_var($cover, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($cover, PHP_URL_SCHEME)) !== 'https')) {
            $errors[] = 'A imagem de capa deve ser uma URL HTTPS válida. Uploads não estão disponíveis.';
        }

        try { $safeContent = (new HtmlSanitizer())->purify($content); }
        catch (Throwable) { $safeContent = ''; $errors[] = 'A sanitização do conteúdo está indisponível; nada foi salvo.'; }

        return [
            'record' => [
                'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt,
                'content' => $safeContent, 'cover_image' => $cover === '' ? null : $cover,
                'category_id' => $categoryId === false ? 0 : (int) $categoryId,
            ],
            'errors' => $errors,
        ];
    }

    private function form(?array $record, array $errors, int $status = 200): void
    {
        $editing = is_array($record) && isset($record['id']);
        try { $categories = (new ArticleCategory())->all(); }
        catch (Throwable) { $this->unavailable(); return; }
        $this->screen('article-form', [
            'record' => $record, 'errors' => $errors, 'editing' => $editing,
            'formAction' => $editing ? 'admin/blog/' . (int) $record['id'] : 'admin/blog',
            'categories' => $categories, 'permissions' => $this->permissions(),
        ], $status);
    }

    private function permissions(): array
    {
        return [
            'create' => $this->auth->can('articles.create'), 'edit' => $this->auth->can('articles.edit'),
            'publish' => $this->auth->can('articles.publish'), 'delete' => $this->auth->can('articles.delete'),
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
            'view' => $view, 'csrf' => $this->session->csrfToken(), 'actor' => $actor?->publicData(),
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

    private function flash(string $message): void
    {
        $this->session->set('flash', (new Message())->success($message));
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
