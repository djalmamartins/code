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

    <?= $this->section("head") ?>
</head>
<body>
    <main>
        <?= $this->section("content") ?>
    </main>

    <?= $this->section("scripts") ?>
</body>
</html>
