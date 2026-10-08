<?php

/**
 * Moves Code | Navigation
 *
 * Exibe a navegação principal acessível do tema Moves v1.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Components
 */

$links = [
    "/" => ["Início", url()],
    "/sobre" => ["Sobre", url("sobre")],
    "/solucoes" => ["Soluções", url("solucoes")],
    "/blog" => ["Blog", url("blog")],
];

?>
<nav id="main-nav" class="main-nav" aria-label="Navegação principal">
    <?php foreach ($links as $path => [$label, $href]): ?>
        <?php $active = $path === "/" ? $currentPath === "/" : str_starts_with($currentPath, $path); ?>
        <a href="<?= $this->e($href) ?>"<?= $active ? ' class="active" aria-current="page"' : "" ?>><?= $this->e($label) ?></a>
    <?php endforeach; ?>
    <a class="mobile-budget" href="<?= $this->e(url("contato")) ?>">Solicitar orçamento ↗</a>
</nav>
