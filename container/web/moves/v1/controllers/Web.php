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
        $projectPath = dirname(__DIR__, 5);
        $assetPath = $projectPath
            . "/public/"
            . trim(APP_THEME_ASSET_BASE, "/")
            . "/web/moves/v1";

        parent::__construct($themePath);

        $this->view->loadExtension(new Asset(
            $assetPath,
            theme(null, "web", "moves", "v1")
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
