<?php

/**
 * Moves Code | Moves Web Controller
 *
 * Controla as páginas públicas do tema Moves v1.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Controllers
 */

namespace Moves\Themes\Web\MovesV1\Controllers;

use Moves\Core\Controller;
use Moves\Models\Page;
use Moves\Models\CmsContent;
use Moves\Support\HtmlSanitizer;
use MovesCode\Router\Router;
use MovesCode\View\Extension\Asset;
use Throwable;

class Web extends Controller
{
    /**
     * Inicializa as views e os assets do tema Moves v1.
     */
    public function __construct(?Router $router = null)
    {
        $themePath = dirname(__DIR__);
        $assetPath = $themePath . "/assets";

        parent::__construct($themePath);

        $this->view->loadExtension(new Asset(
            $assetPath,
            url("theme-assets/web/moves/v1")
        ));

        $this->view->share("currentPath", parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/");
    }

    /**
     * Renderiza a página inicial pública.
     */
    public function home(): void
    {
        $slide = null;
        try { $slide = (new CmsContent())->publishedSlides(gmdate('Y-m-d H:i:s'))[0] ?? null; } catch (Throwable) {}
        $this->render("pages/home", "Estratégia, Design e Tecnologia — MOVES", "Moves: sites, sistemas web, aplicativos e hospedagem para colocar ideias em movimento.", url(), ['slide' => $slide]);
    }

    /** Exibe perguntas frequentes ativas em uma estrutura nativa e acessível. */
    public function faqs(): void
    {
        $items = [];
        try {
            $items = (new CmsContent())->publicFaqs();
        } catch (Throwable) {
            // Keep the public route available until the additive CMS migration is applied.
        }
        try {
            foreach ($items as &$item) { $item['answer'] = (new HtmlSanitizer())->purify((string) $item['answer']); }
            unset($item);
            $this->render('pages/faq', 'Perguntas frequentes — MOVES', 'Respostas às dúvidas mais comuns sobre as soluções da Moves.', url('faq'), ['faqs' => $items]);
        } catch (Throwable) {
            http_response_code(503);
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Conteúdo temporariamente indisponível';
        }
    }

    /** Renderiza a apresentação institucional da Moves. */
    public function about(): void
    {
        $this->render("pages/sobre", "Sobre a Moves", "Estratégia, design e tecnologia trabalhando juntos para transformar desafios em experiências digitais.", url("sobre"));
    }

    /** Renderiza as soluções institucionais. */
    public function solutions(): void
    {
        $this->render("pages/solucoes", "Soluções digitais — MOVES", "Sites, sistemas web, aplicativos e hospedagem: conheça as soluções da Moves.", url("solucoes"));
    }

    /** Renderiza a página de contato sem realizar envio de dados. */
    public function contact(): void
    {
        $this->render("pages/contato", "Contato — MOVES", "Conte seu projeto para a Moves e prepare sua solicitação de proposta.", url("contato"));
    }

    /** Renderiza uma página publicada, sanitizando novamente o conteúdo persistido. */
    public function page(array $route): void
    {
        $slug = is_string($route['slug'] ?? null) ? $route['slug'] : '';
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $this->publicPageNotFound();
            return;
        }

        try {
            $page = (new Page())->findPublishedBySlug($slug, gmdate('Y-m-d H:i:s'));
            if ($page === null) {
                $this->publicPageNotFound();
                return;
            }
            $page['content'] = (new HtmlSanitizer())->purifyWithMediaImages((string) $page['content']);
            $description = mb_substr(trim(strip_tags($page['content'])), 0, 160, 'UTF-8');
            $this->render(
                'pages/page',
                (string) $page['title'] . ' — MOVES',
                $description !== '' ? $description : 'Conteúdo institucional da Moves.',
                url('pagina/' . rawurlencode($slug)),
                ['page' => $page]
            );
        } catch (Throwable) {
            http_response_code(503);
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Conteúdo temporariamente indisponível';
        }
    }

    /** Renderiza uma página usando o layout e o SEO compartilhados. */
    private function publicPageNotFound(): void
    {
        http_response_code(404);
        $this->render('pages/page', 'Página não encontrada — MOVES', 'A página solicitada não está disponível.', url(), ['page' => null]);
    }

    private function render(string $template, string $title, string $description, string $canonical, array $data = []): void
    {
        $head = $this->seo
            ->openGraph(APP_NAME, "pt_BR", "website")
            ->organization("Moves", url(), url("theme-assets/web/moves/v1/images/moves-logo.svg"), $description)
            ->optimize($title, $description, $canonical, url("theme-assets/web/moves/v1/images/hero-notebook.jpg"))
            ->render();

        header("Content-Type: text/html; charset=utf-8");
        echo $this->view->render($template, ["head" => $head] + $data);
    }
}
