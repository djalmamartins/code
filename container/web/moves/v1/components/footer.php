<?php

/**
 * Moves Code | Footer
 *
 * Exibe o rodapé institucional compartilhado do tema Moves v1.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Components
 */

?>
<footer class="footer">
    <div class="footer-top">
        <a href="<?= $this->e(url()) ?>" aria-label="Moves — início"><img class="brand-logo" src="<?= $this->e($this->asset("images/moves-logo.svg")) ?>" alt="MOVES" width="194" height="28" loading="lazy"></a>
        <p>Tecnologia. Pessoas. Resultados.<br>Sempre em movimento.</p>
        <nav aria-label="Rodapé"><a href="<?= $this->e(url()) ?>">Início</a><a href="<?= $this->e(url("sobre")) ?>">Sobre</a><a href="<?= $this->e(url("solucoes")) ?>">Soluções</a><a href="<?= $this->e(url("blog")) ?>">Blog</a></nav>
    </div>
    <div class="footer-bottom"><a href="mailto:contato@moves.com.br">contato@moves.com.br</a><span>Conselheiro Lafaiete · MG</span><a href="<?= $this->e(url("contato")) ?>">Contato</a><span>© <?= date("Y") ?> Moves. Todos os direitos reservados.</span></div>
</footer>
