<?php

/**
 * Moves Code | Header
 *
 * Exibe o cabeçalho compartilhado do tema Moves v1.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Components
 */

?>
<header class="site-header">
    <a class="brand" href="<?= $this->e(url()) ?>" aria-label="Moves — início">
        <img class="brand-logo" src="<?= $this->e($this->asset("images/moves-logo.svg")) ?>" alt="MOVES" width="194" height="28">
    </a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" type="button">Menu <span aria-hidden="true">☰</span></button>
    <?php $this->insert("components/navigation") ?>
    <a class="button button-primary small" href="<?= $this->e(url("contato")) ?>">Solicitar orçamento ↗</a>
</header>
