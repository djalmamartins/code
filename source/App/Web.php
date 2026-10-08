<?php

/**
 * Moves Code | Web
 *
 * Controla as páginas públicas do site Moves.
 *
 * @author Djalma Martins
 * @package Moves\App
 */

namespace Moves\App;

use Moves\Core\Controller;
use MovesCode\Router\Router;
use MovesCode\View\Extension\Asset;

class Web extends Controller
{
    /**
     * Inicializa o tema e os assets das páginas públicas.
     */
    public function __construct(?Router $router = null)
    {
        $theme = APP_THEMES["web"];
        $themePath = dirname(__DIR__, 2) . "/container/web/{$theme["name"]}/{$theme["version"]}";
        $assetPath = dirname(__DIR__, 2)
            . "/public/"
            . trim(APP_THEME_ASSET_BASE, "/")
            . "/web/{$theme["name"]}/{$theme["version"]}";

        parent::__construct($themePath);

        $this->view->loadExtension(new Asset(
            $assetPath,
            theme(null, "web", $theme["name"], $theme["version"])
        ));
    }

    /**
     * Renderiza a página inicial pública.
     */
    public function home(): void
    {
        $title = APP_NAME;
        $description = "Plataforma PHP modular para sites, CMS e aplicativos.";
        $head = $this->seo
            ->openGraph(APP_NAME, "pt_BR", "website")
            ->optimize($title, $description, url(), "")
            ->render();

        header("Content-Type: text/html; charset=utf-8");
        echo $this->view->render("pages/home", [
            "head" => $head,
            "heading" => $title,
            "description" => $description,
        ]);
    }
}
