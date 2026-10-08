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
use MovesCode\Router\Router;
use MovesCode\View\Extension\Asset;

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

    /** Exibe a listagem, atualmente em estado vazio até a integração com o CMS. */
    public function index(): void
    {
        $description = "Conteúdos da Moves sobre estratégia, tecnologia e experiências digitais.";
        $this->render("pages/blog/index", "Blog — MOVES", $description, url("blog"), ["posts" => []]);
    }

    /** Retorna 404 para artigos ainda não fornecidos pelo CMS. */
    public function article(array $data): void
    {
        $this->notFound("Artigo não encontrado", $data["slug"] ?? "");
    }

    /** Retorna 404 para categorias ainda não fornecidas pelo CMS. */
    public function category(array $data): void
    {
        $this->notFound("Categoria não encontrada", $data["slug"] ?? "");
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
}
