<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/health') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'application' => 'Moves Code',
        'php' => PHP_VERSION,
        'timestamp' => gmdate(DATE_ATOM),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Moves Code</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0b1020; color: #eef2ff; }
        main { width: min(42rem, calc(100% - 3rem)); padding: 2.5rem; border: 1px solid #334155; border-radius: 1rem; background: #111827; }
        h1 { margin-top: 0; }
        code { color: #86efac; }
        a { color: #93c5fd; }
    </style>
</head>
<body>
<main>
    <h1>Moves Code</h1>
    <p>Ambiente local PHP pronto para o início do desenvolvimento.</p>
    <p>PHP: <code><?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>Diagnóstico: <a href="/health"><code>/health</code></a></p>
</main>
</body>
</html>
