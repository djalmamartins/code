<?php

/**
 * Moves Code | Two-factor Login
 *
 * Solicita o segundo fator durante a autenticação.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout('default', ['head' => $head]);

?>
<section class="login-section section-shell">
    <div class="login-card">
        <p class="eyebrow">VERIFICAÇÃO DE SEGURANÇA</p>
        <h1>Confirme sua identidade</h1>
        <p class="page-lead">Informe o código do aplicativo autenticador ou um código de recuperação.</p>
        <?php if ($error !== ''): ?><p class="form-error" role="alert"><?= $this->e($error) ?></p><?php endif; ?>
        <form action="<?= $this->e(url('login/2fa')) ?>" method="post">
            <input type="hidden" name="_token" value="<?= $this->e($csrfToken) ?>">
            <label for="code">Código de verificação</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="64" required autofocus>
            <button class="button button-primary" type="submit">Continuar</button>
        </form>
    </div>
</section>
