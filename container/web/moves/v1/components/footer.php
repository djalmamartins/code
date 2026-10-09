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
        <p><?= $this->e($siteSettings['site_tagline'] ?? 'Tecnologia. Pessoas. Resultados. Sempre em movimento.') ?></p>
        <nav aria-label="Rodapé"><a href="<?= $this->e(url()) ?>">Início</a><a href="<?= $this->e(url("sobre")) ?>">Sobre</a><a href="<?= $this->e(url("solucoes")) ?>">Soluções</a><a href="<?= $this->e(url("blog")) ?>">Blog</a><a href="<?= $this->e(url("faq")) ?>">FAQ</a></nav>
    </div>
    <div class="footer-bottom"><?php $contactEmail = $siteSettings['contact_email'] ?? 'contato@moves.com.br'; $contactAddress = $siteSettings['contact_address'] ?? 'Conselheiro Lafaiete · MG'; ?><?php if ($contactEmail !== ''): ?><a href="mailto:<?= $this->e($contactEmail) ?>"><?= $this->e($contactEmail) ?></a><?php endif; ?><?php if (($siteSettings['contact_phone'] ?? '') !== ''): ?><span><?= $this->e($siteSettings['contact_phone']) ?></span><?php endif; ?><?php if ($contactAddress !== ''): ?><span><?= $this->e($contactAddress) ?></span><?php endif; ?><a href="<?= $this->e(url("contato")) ?>">Contato</a><span>© <?= date("Y") ?> <?= $this->e($siteSettings['site_name'] ?? 'Moves') ?>. Todos os direitos reservados.</span></div>
</footer>
