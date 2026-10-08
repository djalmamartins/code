<?php

/**
 * Moves Code | Admin
 *
 * Exibe a área administrativa mínima protegida por autenticação.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout('default', ['head' => $head]);

?>
<section class="page-hero section-shell">
    <p class="eyebrow">ÁREA RESTRITA</p>
    <h1>Olá, <span class="brand-gradient"><?= $this->e($user['name'] ?? '') ?></span></h1>
    <p class="page-lead">Você está autenticado como <?= $this->e($user['email'] ?? '') ?>.</p>
    <form action="<?= $this->e(url('logout')) ?>" method="post">
        <input type="hidden" name="_token" value="<?= $this->e($csrfToken) ?>">
        <button class="button button-ghost" type="submit">Sair</button>
    </form>
</section>
