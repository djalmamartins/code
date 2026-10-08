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
use MovesCode\Router\Router;
use MovesCode\View\Extension\Asset;

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
        $this->render("pages/home", "Estratégia, Design e Tecnologia — MOVES", "Moves: sites, sistemas web, aplicativos e hospedagem para colocar ideias em movimento.", url());
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

    /** Renderiza uma página usando o layout e o SEO compartilhados. */
    private function render(string $template, string $title, string $description, string $canonical): void
    {
        $head = $this->seo
            ->openGraph(APP_NAME, "pt_BR", "website")
            ->organization("Moves", url(), url("theme-assets/web/moves/v1/images/moves-logo.svg"), $description)
            ->optimize($title, $description, $canonical, url("theme-assets/web/moves/v1/images/hero-notebook.jpg"))
            ->render();

        header("Content-Type: text/html; charset=utf-8");
        echo $this->view->render($template, ["head" => $head]);
    }
}
