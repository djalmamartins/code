<?php

/**
 * Moves Code | Theme Asset Controller
 *
 * Entrega imagens e fontes públicas do tema Moves v1 a partir do container.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Controllers
 */

namespace Moves\Themes\Web\MovesV1\Controllers;

use MovesCode\Router\Router;

class ThemeAsset
{
    public function __construct(?Router $router = null)
    {
    }

    /** Entrega somente arquivos estáticos autorizados do tema. */
    public function show(array $data): void
    {
        $directory = $data["directory"] ?? "";
        $file = $data["file"] ?? "";
        $extensions = [
            "images" => ["png", "jpg", "jpeg", "gif", "webp", "svg", "ico"],
            "fonts" => ["woff", "woff2", "ttf", "otf", "eot"],
        ];

        if (!isset($extensions[$directory])
            || preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $file) !== 1
            || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $extensions[$directory], true)
        ) {
            $this->notFound();
            return;
        }

        $assetRoot = realpath(dirname(__DIR__) . "/assets");
        $asset = realpath(dirname(__DIR__) . "/assets/{$directory}/{$file}");

        if ($assetRoot === false
            || $asset === false
            || !is_file($asset)
            || !str_starts_with($asset, $assetRoot . DIRECTORY_SEPARATOR)
        ) {
            $this->notFound();
            return;
        }

        $mime = match (strtolower(pathinfo($asset, PATHINFO_EXTENSION))) {
            "svg" => "image/svg+xml",
            "png" => "image/png",
            "jpg", "jpeg" => "image/jpeg",
            "gif" => "image/gif",
            "webp" => "image/webp",
            "ico" => "image/x-icon",
            "woff" => "font/woff",
            "woff2" => "font/woff2",
            "ttf" => "font/ttf",
            "otf" => "font/otf",
            "eot" => "application/vnd.ms-fontobject",
        };

        header("Content-Type: {$mime}");
        header("Content-Length: " . filesize($asset));
        header("Cache-Control: public, max-age=86400");
        readfile($asset);
    }

    private function notFound(): void
    {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo "Not Found";
    }
}
