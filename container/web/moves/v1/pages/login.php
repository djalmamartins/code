<?php

/**
 * Moves Code | Login
 *
 * Apresenta o formulário de acesso à área administrativa.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout('default', ['head' => $head]);

?>
<section class="page-hero section-shell">
    <p class="eyebrow">ÁREA RESTRITA</p>
    <h1>Acesse sua <span class="brand-gradient">conta.</span></h1>
    <p class="page-lead">Entre com suas credenciais para continuar.</p>
</section>
<section class="section-shell page-section">
    <form class="contact-layout" action="<?= $this->e(url('login')) ?>" method="post" autocomplete="on">
        <input type="hidden" name="_token" value="<?= $this->e($csrfToken) ?>">
        <?php if ($error !== ''): ?><p role="alert"><?= $this->e($error) ?></p><?php endif; ?>
        <label>E-mail<input type="email" name="email" autocomplete="username" required maxlength="254" value="<?= $this->e($email) ?>"></label>
        <label>Senha<input type="password" name="password" autocomplete="current-password" required maxlength="4096"></label>
        <button class="button button-primary" type="submit">Entrar</button>
    </form>
</section>
