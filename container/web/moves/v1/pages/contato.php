<?php

/**
 * Moves Code | Contato
 *
 * Prepara uma solicitação de contato sem transmitir dados ao servidor.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout("default", ["head" => $head]);

?>
<section class="page-hero section-shell"><p class="eyebrow">VAMOS CONVERSAR</p><h1>Todo movimento<br><span class="brand-gradient">começa com uma ideia.</span></h1><p class="page-lead">Conte o que você quer construir. Vamos entender o desafio e conversar sobre os próximos passos.</p></section>
<section class="section-shell contact-layout"><aside><p class="eyebrow">CONTATO DIRETO</p><h2>Qual é o seu<br>próximo passo?</h2><a class="contact-email" href="mailto:contato@moves.com.br">contato@moves.com.br ↗</a><p>Conselheiro Lafaiete · MG<br>Projetos digitais, de onde você estiver.</p><div class="contact-note">Prefere escrever com calma? Preencha o resumo ao lado para preparar uma mensagem no seu aplicativo de e-mail.</div></aside><form id="contact-form"><div class="form-row"><label>Seu nome<input name="nome" autocomplete="name" required maxlength="100" placeholder="Como podemos chamar você?"></label><label>E-mail<input type="email" name="email" autocomplete="email" required maxlength="200" placeholder="voce@empresa.com.br"></label></div><div class="form-row"><label>Empresa <span>(opcional)</span><input name="empresa" autocomplete="organization" maxlength="120" placeholder="Nome da sua empresa"></label><label>Qual serviço você procura?<select name="servico" required><option value="">Selecione um serviço</option><option value="criacao-de-sites">Criação de Sites</option><option value="sistemas-web">Sistemas Web</option><option value="aplicativos">Aplicativos</option><option value="hospedagem">Hospedagem</option><option value="outro">Quero conversar sobre uma ideia</option></select></label></div><label>Conte sobre o projeto<textarea name="mensagem" rows="6" required minlength="20" maxlength="5000" placeholder="O que você precisa resolver? Tem um prazo ou alguma referência?"></textarea></label><button class="button button-primary" type="submit">Preparar e-mail ↗</button><p class="form-help">Nenhuma mensagem é enviada automaticamente. Você revisa e envia pelo seu aplicativo de e-mail.</p><div id="form-result" role="status" hidden><h3>Seu resumo está pronto.</h3><p>Abra seu aplicativo de e-mail para revisar e enviar. Se ele não abrir, copie o resumo abaixo.</p><textarea id="message-preview" readonly aria-label="Resumo da solicitação"></textarea><div class="hero-actions"><a id="email-link" class="button button-primary">Abrir aplicativo de e-mail ↗</a><button id="copy-message" class="button button-ghost" type="button">Copiar resumo</button></div></div></form></section>
