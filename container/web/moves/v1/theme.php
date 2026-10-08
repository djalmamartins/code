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
use Moves\Themes\Web\MovesV1\Controllers\Blog;
use Moves\Themes\Web\MovesV1\Controllers\Auth;
use Moves\Themes\Web\MovesV1\Controllers\ThemeAsset;
use MovesCode\Router\Router;

return [
    "type" => "web",
    "name" => "moves",
    "version" => "v1",
    "controller" => Web::class,
    "routes" => static function (Router $router): void {
        $router->get("/", Web::class . ":home", "web.home");
        $router->get("/sobre", Web::class . ":about", "web.about");
        $router->get("/solucoes", Web::class . ":solutions", "web.solutions");
        $router->get("/contato", Web::class . ":contact", "web.contact");
        $router->get("/blog", Blog::class . ":index", "web.blog");
        $router->get("/blog/categoria/{slug}", Blog::class . ":category", "web.blog.category");
        $router->get("/blog/{slug}", Blog::class . ":article", "web.blog.article");
        $router->get("/login", Auth::class . ":showLogin", "web.login");
        $router->post("/login", Auth::class . ":login", "web.login.submit");
        $router->post("/logout", Auth::class . ":logout", "web.logout");
        $router->get("/admin", Auth::class . ":admin", "web.admin");
        $router->get("/theme-assets/web/moves/v1/{directory}/{file}", ThemeAsset::class . ":show", "web.theme.asset");
    },
];
