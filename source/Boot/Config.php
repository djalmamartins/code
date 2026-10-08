<?php

/**
 * Moves Code | Configuration
 *
 * Define as configurações básicas do ambiente da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

define("APP_NAME", "Moves Code");
define("APP_URL", "https://code.lab");
define("APP_ENV", "local");

define("APP_THEME_ASSET_BASE", "assets/themes");
define("APP_THEMES", [
    "web" => ["name" => "moves", "version" => "v1"],
    "cms" => ["name" => "default", "version" => "v1"],
    "app" => ["name" => "client", "version" => "v1"],
    "mail" => ["name" => "default", "version" => "v1"],
]);
