<?php

/**
 * Moves Code | Blog
 *
 * Apresenta a listagem pública de conteúdos do blog da Moves.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages\Blog
 */

$this->layout("default", ["head" => $head]);

?>
<section class="page-hero section-shell"><p class="eyebrow">PARA SEU PRÓXIMO PASSO</p><h1>Ideias para pensar.<br><span class="brand-gradient">Clareza para agir.</span></h1><p class="page-lead">Leituras sobre estratégia, tecnologia e criação de experiências digitais.</p></section>
<section class="section-shell page-section" aria-live="polite"><?php if ($posts === []): ?><div class="content-empty"><span aria-hidden="true">↗</span><h2>Conteúdo em preparação.</h2><p>O blog está pronto para receber as publicações reais do CMS Moves. Nenhum artigo fictício será exibido.</p><a class="text-link" href="<?= $this->e(url("contato")) ?>">Enquanto isso, converse com a Moves →</a></div><?php endif; ?></section>
