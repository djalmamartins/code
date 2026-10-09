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
    private ?HTMLPurifier $mediaPurifier = null;

    public function __construct()
    {
        $this->purifier = new HTMLPurifier($this->configuration(false));
    }

    /** Remove elementos, atributos e esquemas que não fazem parte do HTML editorial permitido. */
    public function purify(string $html): string
    {
        return $this->purifier->purify($html);
    }

    /** Sanitiza conteúdo editorial permitindo somente imagens da biblioteca Moves. */
    public function purifyWithMediaImages(string $html): string
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException('A extensão DOM é necessária para sanitizar imagens editoriais.');
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="moves-editorial-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
            if (!$loaded) {
                throw new \RuntimeException('Não foi possível analisar o conteúdo editorial.');
            }

            $images = $document->getElementsByTagName('img');
            for ($index = $images->length - 1; $index >= 0; $index--) {
                $image = $images->item($index);
                if (!$image instanceof \DOMElement) { continue; }
                $source = $image->getAttribute('src');
                if (preg_match('~^/(?:admin/media/([1-9][0-9]*)/file|media/([1-9][0-9]*))$~D', $source, $match) !== 1) {
                    $image->parentNode?->removeChild($image);
                    continue;
                }

                $mediaId = $match[1] !== '' ? $match[1] : $match[2];
                $image->setAttribute('src', '/media/' . $mediaId);
                $alt = mb_substr($image->getAttribute('alt'), 0, 300, 'UTF-8');
                while ($image->attributes->length > 0) {
                    $image->removeAttributeNode($image->attributes->item(0));
                }
                $image->setAttribute('src', '/media/' . $mediaId);
                $image->setAttribute('alt', $alt);
            }

            $root = $document->getElementById('moves-editorial-root');
            if (!$root instanceof \DOMElement) {
                throw new \RuntimeException('Não foi possível processar o conteúdo editorial.');
            }
            $content = '';
            foreach ($root->childNodes as $child) {
                $content .= $document->saveHTML($child);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $this->mediaPurifier ??= new HTMLPurifier($this->configuration(true));

        return $this->mediaPurifier->purify($content);
    }

    private function configuration(bool $allowMediaImages): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $allowed = 'p,br,h2,h3,h4,strong,b,em,i,u,s,ul,ol,li,blockquote,pre,code,hr,a[href|title]';
        if ($allowMediaImages) {
            $allowed .= ',img[src|alt]';
        }
        $config->set('HTML.Allowed', $allowed);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('Attr.EnableID', false);
        $config->set('Cache.SerializerPath', $this->cacheDirectory());

        return $config;
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
