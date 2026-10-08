<?php

/**
 * Moves Code | Blog Not Found
 *
 * Informa que um artigo ou categoria do blog não foi encontrado.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages\Blog
 */

$this->layout("default", ["head" => $head]);

?>
<section class="page-hero section-shell error-page"><p class="eyebrow">ERRO 404</p><h1><?= $this->e($pageTitle) ?>.</h1><p class="page-lead">O conteúdo solicitado não está disponível. Ele pode ainda não ter sido publicado ou ter mudado de endereço.</p><a class="button button-primary" href="<?= $this->e(url("blog")) ?>">Voltar ao blog ↗</a></section>
