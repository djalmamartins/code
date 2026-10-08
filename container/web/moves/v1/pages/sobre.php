<?php

/**
 * Moves Code | Sobre
 *
 * Apresenta a identidade, abordagem e processo de trabalho da Moves.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout("default", ["head" => $head]);

?>
<section class="page-hero section-shell"><p class="eyebrow">SOMOS A MOVES</p><h1>Ideias ganham força<br>quando entram em <span class="brand-gradient">movimento.</span></h1><p class="page-lead">Estratégia, design e tecnologia trabalhando juntos para transformar desafios em experiências digitais claras e úteis.</p></section>
<section class="section-shell about-editorial about-page"><div class="about-manifesto">MAIS QUE PROJETOS.<br>CONSTRUÍMOS<br>PARCERIAS.<span></span></div><div class="about-editorial-image"><img src="<?= $this->e($this->asset("images/team.jpg")) ?>" alt="Equipe colaborando ao redor de uma mesa" width="1400" height="2099"></div><div class="about-editorial-copy"><p class="eyebrow">NOSSA ABORDAGEM</p><h2>Construímos com você,<br>do desafio à <span class="brand-gradient">evolução.</span></h2><p>Começamos entendendo o contexto, organizamos o caminho e conectamos as escolhas de design às possibilidades da tecnologia. O resultado é uma solução preparada para cumprir seu papel e continuar evoluindo.</p></div></section>
<section class="section-shell process block"><div class="section-heading"><div><p class="eyebrow">NOSSO PROCESSO</p><h2>Clareza em cada<br><span class="brand-gradient">próximo passo.</span></h2></div></div><div class="process-grid"><article><span>01</span><h3>Descobrir</h3><p>Contexto, desafios e oportunidades.</p></article><article><span>02</span><h3>Estruturar</h3><p>Estratégia, prioridades e escopo.</p></article><article><span>03</span><h3>Criar</h3><p>Conteúdo, interface e experiência.</p></article><article><span>04</span><h3>Desenvolver</h3><p>Tecnologia, integração e qualidade.</p></article><article><span>05</span><h3>Evoluir</h3><p>Acompanhamento e próximos ciclos.</p></article></div></section>
<section class="cta"><div class="cta-inner"><div><p class="eyebrow">VAMOS CONVERSAR?</p><h2>Qual é o seu<br>próximo movimento?</h2></div><a class="button button-primary large" href="<?= $this->e(url("contato")) ?>">Converse com a Moves ↗</a></div></section>
