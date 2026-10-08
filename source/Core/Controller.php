<?php

/**
 * Moves Code | Controller
 *
 * Fornece a estrutura base para os controladores da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */

namespace Moves\Core;

use MovesCode\Model\Message;
use MovesCode\Seo\Seo;
use MovesCode\View\Engine;

class Controller
{
    protected Engine $view;

    protected Seo $seo;

    protected Message $message;

    /**
     * Inicializa os recursos compartilhados pelos controladores.
     */
    public function __construct(?string $pathToViews = null)
    {
        $this->view = new Engine($pathToViews);
        $this->seo = new Seo();
        $this->message = new Message();
    }
}
