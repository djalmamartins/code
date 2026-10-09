<?php

/**
 * Moves Code | FAQ Public
 *
 * Exibe respostas públicas sanitizadas em elementos expansíveis acessíveis.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout('default', ['head' => $head]);
$groupedFaqs = [];
foreach (($faqs ?? []) as $faq) { $groupedFaqs[$faq['category'] ?: 'Geral'][] = $faq; }

?>
<section class="page-hero section-shell"><p class="eyebrow">AJUDA</p><h1>Perguntas frequentes</h1><p class="page-lead">Respostas para dúvidas comuns sobre os serviços e o trabalho da Moves.</p></section>
<section class="section-shell page-section faq-public" aria-label="Perguntas e respostas">
    <?php if ($groupedFaqs === []): ?><div class="content-empty"><h2>Estamos preparando esta seção.</h2><p>Em breve, novas respostas estarão disponíveis.</p></div><?php else: ?>
        <?php foreach ($groupedFaqs as $category => $items): ?><section class="faq-category"><h2><?= $this->e($category) ?></h2><?php foreach ($items as $faq): ?><details class="faq-item"><summary><?= $this->e($faq['question']) ?></summary><div class="faq-answer editorial-content"><?= $faq['answer'] ?></div></details><?php endforeach; ?></section><?php endforeach; ?>
    <?php endif; ?>
</section>
