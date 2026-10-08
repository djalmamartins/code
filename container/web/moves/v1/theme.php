<?php

/**
 * Moves Code | Moves Web Theme
 *
 * Define a identidade e as rotas públicas do tema Moves v1.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1
 */

use Moves\Themes\Web\MovesV1\Controllers\Web;
use MovesCode\Router\Router;

return [
    "type" => "web",
    "name" => "moves",
    "version" => "v1",
    "controller" => Web::class,
    "routes" => static function (Router $router): void {
        $router->get("/", Web::class . ":home", "web.home");
    },
];
