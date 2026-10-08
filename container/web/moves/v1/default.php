<?php

/**
 * Moves Code | Default
 *
 * Define o layout principal das páginas públicas do site Moves.
 *
 * @author Djalma Martins
 * @package Moves\Web
 */

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if (!empty($head)): ?>
        <?= $head ?>
    <?php else: ?>
        <title><?= $this->e($title ?? "Moves Code") ?></title>
        <?php if (!empty($description)): ?>
            <meta name="description" content="<?= $this->e($description) ?>">
        <?php endif; ?>
    <?php endif; ?>

    <meta name="theme-color" content="#090b0e">
    <link rel="icon" href="<?= $this->e($this->asset("images/favicon.png")) ?>" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $this->e($this->asset("css/styles.css")) ?>">
    <?= $this->section("head") ?>
</head>
<body>
    <div class="noise" aria-hidden="true"></div>
    <div class="cursor-glow" aria-hidden="true"></div>
    <a class="skip-link" href="#main">Pular para o conteúdo</a>
    <?php $this->insert("components/header") ?>
    <main id="main">
        <?= $this->section("content") ?>
    </main>
    <?php $this->insert("components/footer") ?>
    <script src="<?= $this->e($this->asset("js/script.js")) ?>" defer></script>
    <?= $this->section("scripts") ?>
</body>
</html>
