<?php

/**
 * Moves Code | Published Blog Article
 *
 * Renderiza artigo publicado com conteúdo sanitizado no servidor.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages\Blog
 */

$this->layout('default', ['head' => $head]);

?>
<article class="section-shell editorial-page-shell public-article">
    <header class="section-header">
        <a class="blog-category" href="<?= $this->e(url('blog/categoria/' . rawurlencode($article['category_slug']))) ?>"><?= $this->e($article['category_name']) ?></a>
        <h1><?= $this->e($article['title']) ?></h1>
        <p class="page-lead"><?= $this->e($article['excerpt']) ?></p>
        <p class="blog-meta"><span><?= $this->e($article['author_name']) ?></span><time datetime="<?= $this->e(gmdate(DATE_ATOM, strtotime($article['published_at'] . ' UTC'))) ?>"><?= $this->e(date('d/m/Y', strtotime($article['published_at'] . ' UTC'))) ?></time></p>
        <?php if (is_string($article['cover_image'] ?? null) && preg_match('~^/media/[1-9][0-9]*$~D', $article['cover_image']) === 1): ?><img class="article-cover" src="<?= $this->e($article['cover_image']) ?>" alt="" loading="eager"><?php endif; ?>
    </header>
    <div class="editorial-content"><?= $article['content'] ?></div>
    <footer class="article-footer"><a class="text-link" href="<?= $this->e(url('blog')) ?>">← Voltar ao blog</a></footer>
</article>
