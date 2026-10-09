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
<section class="page-hero section-shell"><p class="eyebrow"><?= $this->e($category['name'] ?? 'PARA SEU PRÓXIMO PASSO') ?></p><h1><?= isset($category) ? $this->e($category['name']) : 'Ideias para pensar.<br><span class="brand-gradient">Clareza para agir.</span>' ?></h1><p class="page-lead"><?= $this->e($category['description'] ?? 'Leituras sobre estratégia, tecnologia e criação de experiências digitais.') ?></p><?php if (isset($category)): ?><a class="text-link" href="<?= $this->e(url('blog')) ?>">Ver todos os artigos →</a><?php endif; ?></section>
<section class="section-shell page-section blog-list" aria-live="polite">
    <?php if ($posts === []): ?><div class="content-empty"><span aria-hidden="true">↗</span><h2>Conteúdo em preparação.</h2><p>Nenhum artigo publicado está disponível nesta categoria.</p><a class="text-link" href="<?= $this->e(url('contato')) ?>">Converse com a Moves →</a></div>
    <?php else: ?><div class="blog-grid"><?php foreach ($posts as $post): ?><article class="blog-card"><?php if (is_string($post['cover_image'] ?? null) && preg_match('~^/media/[1-9][0-9]*$~D', $post['cover_image']) === 1): ?><a class="blog-card-image" href="<?= $this->e(url('blog/' . rawurlencode($post['slug']))) ?>"><img src="<?= $this->e($post['cover_image']) ?>" alt="" loading="lazy"></a><?php endif; ?><div class="blog-card-body"><a class="blog-category" href="<?= $this->e(url('blog/categoria/' . rawurlencode($post['category_slug']))) ?>"><?= $this->e($post['category_name']) ?></a><h2><a href="<?= $this->e(url('blog/' . rawurlencode($post['slug']))) ?>"><?= $this->e($post['title']) ?></a></h2><p><?= $this->e($post['excerpt']) ?></p><div class="blog-meta"><span><?= $this->e($post['author_name']) ?></span><time datetime="<?= $this->e(gmdate(DATE_ATOM, strtotime($post['published_at'] . ' UTC'))) ?>"><?= $this->e(date('d/m/Y', strtotime($post['published_at'] . ' UTC'))) ?></time></div></div></article><?php endforeach; ?></div><?= $pagination ?? '' ?><?php endif; ?>
</section>
