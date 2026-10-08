<?php

/**
 * Moves Code | CMS Assets
 *
 * Publica somente os arquivos estáticos autorizados dos pacotes do CMS.
 *
 * @author Djalma Martins
 * @package Moves\\Themes\\Cms\\DefaultV1\\Controllers
 */

namespace Moves\Themes\Cms\DefaultV1\Controllers;

use Moves\Core\Session;
use Moves\Models\Auth;
use MovesCode\Router\Router;

class Assets
{
    public function __construct(?Router $router = null)
    {
    }

    /** Entrega CSS, JavaScript e fontes allowlisted somente a usuários administrativos. */
    public function show(array $data): void
    {
        $auth = new Auth(new Session());
        if (!$auth->can('admin.access')) {
            http_response_code(403);
            header('Cache-Control: no-store');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Acesso negado';
            return;
        }

        $package = (string) ($data['package'] ?? '');
        $directory = (string) ($data['directory'] ?? '');
        $file = (string) ($data['file'] ?? '');
        $allowed = [
            'wysiwyg' => [
                'css' => ['app.css'],
                'js' => ['app.js'],
            ],
            'icons' => [
                'css' => ['moves-icons.css'],
                'fonts' => ['moves-icons.eot', 'moves-icons.svg', 'moves-icons.ttf', 'moves-icons.woff', 'moves-icons.woff2'],
            ],
        ];

        if (!isset($allowed[$package][$directory]) || !in_array($file, $allowed[$package][$directory], true)) {
            $this->notFound();
            return;
        }

        $packageDirectory = $package === 'wysiwyg' ? 'moves-editor' : 'moves-icons';
        $sourceFile = $package === 'wysiwyg'
            ? ($directory === 'js' ? 'moves-editor.js' : 'moves-editor.css')
            : $file;
        $root = realpath(dirname(__DIR__) . '/assets/vendor/' . $packageDirectory . '/v1.0.0');
        $asset = realpath(dirname(__DIR__) . '/assets/vendor/' . $packageDirectory . '/v1.0.0/' . $directory . '/' . $sourceFile);
        if ($root === false || $asset === false || !is_file($asset) || !str_starts_with($asset, $root . DIRECTORY_SEPARATOR)) {
            $this->notFound();
            return;
        }

        $mime = match (strtolower(pathinfo($asset, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'text/javascript; charset=utf-8',
            'eot' => 'application/vnd.ms-fontobject',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($asset));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($asset);
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não encontrado';
    }
}
