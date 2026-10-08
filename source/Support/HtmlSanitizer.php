<?php

/**
 * Moves Code | HTML Sanitizer
 *
 * Sanitiza HTML editorial antes do armazenamento e da exibição pública.
 *
 * @author Djalma Martins
 * @package Moves\Support
 */

namespace Moves\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', 'p,br,h2,h3,h4,strong,b,em,i,u,s,ul,ol,li,blockquote,pre,code,hr,a[href|title]');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('Attr.EnableID', false);
        $config->set('Cache.SerializerPath', $this->cacheDirectory());

        $this->purifier = new HTMLPurifier($config);
    }

    /** Remove elementos, atributos e esquemas que não fazem parte do HTML editorial permitido. */
    public function purify(string $html): string
    {
        return $this->purifier->purify($html);
    }

    private function cacheDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'moves-code-htmlpurifier-' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 16);

        if (is_link($directory)) {
            throw new \RuntimeException('HTML sanitizer cache is unavailable.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('HTML sanitizer cache is unavailable.');
        }
        chmod($directory, 0700);

        return $directory;
    }
}
