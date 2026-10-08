<?php

/**
 * Moves Code | Theme Asset Publisher
 *
 * Gera os bundles públicos comprimidos do tema ativo da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

use MovesCode\Compress\CSS;
use MovesCode\Compress\JS;

require dirname(__DIR__) . "/vendor/autoload.php";
require dirname(__DIR__) . "/source/Boot/Config.php";

try {
    $type = $argv[1] ?? "web";
    if (!isset(APP_THEMES[$type])) {
        throw new InvalidArgumentException("Unknown theme type: {$type}");
    }

    $name = $argv[2] ?? APP_THEMES[$type]["name"];
    $version = $argv[3] ?? APP_THEMES[$type]["version"];

    foreach ([$type, $name, $version] as $identifier) {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $identifier) !== 1) {
            throw new InvalidArgumentException("Invalid theme identifier.");
        }
    }

    $projectRoot = dirname(__DIR__);
    $containerRoot = realpath($projectRoot . "/container");
    $publicRoot = realpath($projectRoot . "/public");
    $assetRoot = realpath("{$projectRoot}/container/{$type}/{$name}/{$version}/assets");

    if ($containerRoot === false || $publicRoot === false || $assetRoot === false) {
        throw new RuntimeException("Theme asset directories are unavailable.");
    }

    if (!str_starts_with($assetRoot . DIRECTORY_SEPARATOR, $containerRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Theme asset source is outside the container directory.");
    }

    $cssSource = "{$assetRoot}/css/styles.css";
    $jsSource = "{$assetRoot}/js/script.js";
    if (!is_file($cssSource) || !is_file($jsSource)) {
        throw new RuntimeException("Theme CSS or JavaScript source was not found.");
    }

    $targetRoot = $publicRoot . "/assets";
    $bundle = $type === "web" ? "moves" : "{$type}-{$name}";
    $cssTarget = $targetRoot . "/{$bundle}.min.css";
    $jsTarget = $targetRoot . "/{$bundle}.min.js";

    (new CSS($cssSource))->minify($cssTarget);
    (new JS($jsSource))->minify($jsTarget);
    chmod($cssTarget, 0644);
    chmod($jsTarget, 0644);

    fwrite(STDOUT, sprintf(
        "Theme %s/%s/%s: %s.min.css (%d bytes), %s.min.js (%d bytes).%s",
        $type,
        $name,
        $version,
        $bundle,
        filesize($cssTarget),
        $bundle,
        filesize($jsTarget),
        PHP_EOL
    ));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Asset publication failed: {$exception->getMessage()}" . PHP_EOL);
    exit(1);
}
