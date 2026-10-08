<?php

/**
 * Moves Code | Public Page
 *
 * Exibe conteúdo editorial publicado e previamente sanitizado.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1
 */

$e = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

?>
<?php $this->layout('default', ['head' => $head ?? '']); ?>
<section class="section-shell editorial-page-shell">
    <?php if (is_array($page ?? null)): ?>
        <article class="editorial-page">
            <header class="section-header"><h1><?= $e($page['title']) ?></h1></header>
            <div class="editorial-content"><?= $page['content'] ?></div>
        </article>
    <?php else: ?>
        <section class="section-header"><h1>Página não encontrada</h1><p>O conteúdo solicitado não está disponível.</p><a class="button" href="<?= $e(url()) ?>">Voltar ao início</a></section>
    <?php endif; ?>
</section>
