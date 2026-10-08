<?php

function url(?string $path = null): string
{
    $base = rtrim(APP_URL, "/");

    return $path === null || $path === ""
        ? $base
        : $base . "/" . ltrim($path, "/");
}
