<?php

/**
 * Moves Code | Moves Blog Controller
 *
 * Apresenta o blog do tema Moves v1 enquanto aguarda a fonte de conteúdo do CMS.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Controllers
 */

namespace Moves\Themes\Web\MovesV1\Controllers;

use Moves\Core\Controller;
use Moves\Models\Article;
use Moves\Models\ArticleCategory;
use Moves\Support\HtmlSanitizer;
use MovesCode\Pager\Pager;
use MovesCode\Router\Router;
use MovesCode\View\Extension\Asset;
use Throwable;

class Blog extends Controller
{
    public function __construct(?Router $router = null)
    {
        $themePath = dirname(__DIR__);
        parent::__construct($themePath);
        $this->view->loadExtension(new Asset(
            $themePath . "/assets",
            url("theme-assets/web/moves/v1")
        ));
        $this->view->share("currentPath", parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/");
    }

    /** Exibe artigos publicados e disponíveis até o momento atual. */
    public function index(): void
    {
        $this->listing(null, 'Blog — MOVES', url('blog'));
    }

    /** Exibe um artigo publicado pelo slug público. */
    public function article(array $data): void
    {
        $slug = is_string($data['slug'] ?? null) ? $data['slug'] : '';
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) { $this->notFound('Artigo não encontrado', ''); return; }
        try {
            $article = (new Article())->findPublishedBySlug($slug, gmdate('Y-m-d H:i:s'));
            if ($article === null) { $this->notFound('Artigo não encontrado', $slug); return; }
            $article['content'] = (new HtmlSanitizer())->purify((string) $article['content']);
            $description = trim((string) $article['excerpt']);
            $this->render('pages/blog/post', $article['title'] . ' — MOVES', $description,
                url('blog/' . rawurlencode($slug)), ['article' => $article]);
        } catch (Throwable) { $this->unavailable(); }
    }

    /** Exibe apenas artigos publicados de uma categoria existente. */
    public function category(array $data): void
    {
        $slug = is_string($data['slug'] ?? null) ? $data['slug'] : '';
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) { $this->notFound('Categoria não encontrada', ''); return; }
        try {
            $category = (new ArticleCategory())->findBySlug($slug);
            if ($category === null || (new Article())->countPublishedInCategory(gmdate('Y-m-d H:i:s'), (int) $category['id']) === 0) {
                $this->notFound('Categoria não encontrada', $slug);
                return;
            }
            $this->listing((int) $category['id'], (string) $category['name'] . ' — Blog MOVES', url('blog/categoria/' . rawurlencode($slug)), $category);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function listing(?int $categoryId, string $title, string $canonical, ?array $category = null): void
    {
        try {
            $now = gmdate('Y-m-d H:i:s');
            $page = max(1, min(100000, (int) ($_GET['page'] ?? 1)));
            $articles = new Article();
            $pager = (new Pager($canonical . (str_contains($canonical, '?') ? '&' : '?') . 'page={page}'))
                ->pager($articles->countPublished($now, $categoryId), 10, $page);
            $description = is_array($category) && trim((string) ($category['description'] ?? '')) !== ''
                ? (string) $category['description']
                : 'Conteúdos da Moves sobre estratégia, tecnologia e experiências digitais.';
            $this->render('pages/blog/index', $title, $description, $canonical, [
                'posts' => $articles->publishedList($now, $pager->limit(), $pager->offset(), $categoryId),
                'category' => $category,
                'pagination' => $pager->render(),
            ]);
        } catch (Throwable) { $this->unavailable(); }
    }

    private function notFound(string $title, string $slug): void
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $slug = "";
        }

        http_response_code(404);
        $this->render("pages/blog/artigo", $title . " — MOVES", "O conteúdo solicitado não está disponível.", url("blog"), [
            "pageTitle" => $title,
            "slug" => $slug,
        ]);
    }

    private function render(string $template, string $title, string $description, string $canonical, array $data = []): void
    {
        $head = $this->seo
            ->openGraph(APP_NAME, "pt_BR", "website")
            ->optimize($title, $description, $canonical, url("theme-assets/web/moves/v1/images/hero-notebook.jpg"))
            ->render();

        header("Content-Type: text/html; charset=utf-8");
        echo $this->view->render($template, ["head" => $head] + $data);
    }

    private function unavailable(): void
    {
        http_response_code(503);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Conteúdo temporariamente indisponível';
    }
}
