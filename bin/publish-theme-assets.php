<?php

/**
 * Moves Code | Theme Asset Publisher
 *
 * Publica com segurança os arquivos estáticos dos temas da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Boot
 */

require dirname(__DIR__) . "/source/Boot/Config.php";

try {
    $type = $argv[1] ?? "web";
    if (!isset(APP_THEMES[$type])) {
        throw new InvalidArgumentException("Unknown theme type: {$type}");
    }

    $name = $argv[2] ?? APP_THEMES[$type]["name"];
    $version = $argv[3] ?? APP_THEMES[$type]["version"];

    foreach ([$type, $name, $version] as $identifier) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $identifier)) {
            throw new InvalidArgumentException("Invalid theme identifier.");
        }
    }

    $projectRoot = dirname(__DIR__);
    $containerRoot = realpath($projectRoot . "/container");
    $publicRoot = realpath($projectRoot . "/public");

    if ($containerRoot === false || $publicRoot === false) {
        throw new RuntimeException("Project asset directories are unavailable.");
    }

    $source = realpath("{$containerRoot}/{$type}/{$name}/{$version}/assets");
    if ($source === false || !is_dir($source)) {
        throw new RuntimeException("Theme asset source was not found.");
    }

    if (!str_starts_with($source . DIRECTORY_SEPARATOR, $containerRoot . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Theme asset source is outside the container directory.");
    }

    $destination = "{$publicRoot}/assets/themes/{$type}/{$name}/{$version}";
    if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create the public theme directory.");
    }

    $destinationRoot = realpath($destination);
    if (
        $destinationRoot === false
        || !str_starts_with($destinationRoot . DIRECTORY_SEPARATOR, $publicRoot . DIRECTORY_SEPARATOR)
    ) {
        throw new RuntimeException("Public theme destination is outside the public directory.");
    }

    $allowedExtensions = [
        "css", "js", "map",
        "png", "jpg", "jpeg", "gif", "webp", "svg", "ico",
        "json", "xml",
        "woff", "woff2", "ttf", "otf", "eot",
    ];
    $published = 0;
    $unchanged = 0;
    $skipped = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $relative = substr($file->getPathname(), strlen($source) + 1);
        $segments = preg_split('~[/\\\\]+~', $relative) ?: [];
        $extension = strtolower($file->getExtension());

        if (
            $file->isLink()
            || !$file->isFile()
            || array_filter($segments, static fn(string $segment): bool => str_starts_with($segment, "."))
            || !in_array($extension, $allowedExtensions, true)
        ) {
            $skipped++;
            continue;
        }

        $target = $destinationRoot . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        $targetDirectory = dirname($target);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Unable to create an asset directory.");
        }

        $targetDirectory = realpath($targetDirectory);
        if (
            $targetDirectory === false
            || !str_starts_with($targetDirectory . DIRECTORY_SEPARATOR, $destinationRoot . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException("Asset destination escaped the public theme directory.");
        }
        $target = $targetDirectory . DIRECTORY_SEPARATOR . basename($target);

        if (is_file($target) && hash_file("sha256", $file->getPathname()) === hash_file("sha256", $target)) {
            $unchanged++;
            continue;
        }

        $temporary = tempnam($targetDirectory, ".publish-");
        if ($temporary === false) {
            throw new RuntimeException("Unable to create a temporary asset file.");
        }

        try {
            if (!copy($file->getPathname(), $temporary) || !chmod($temporary, 0644) || !rename($temporary, $target)) {
                throw new RuntimeException("Unable to publish an asset.");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        $published++;
    }

    fwrite(
        STDOUT,
        "Theme {$type}/{$name}/{$version}: {$published} published, {$unchanged} unchanged, {$skipped} skipped." . PHP_EOL
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Asset publication failed: {$exception->getMessage()}" . PHP_EOL);
    exit(1);
}
