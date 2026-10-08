<?php

/**
 * Moves Code | Home
 *
 * Apresenta a página institucional principal do tema Moves v1.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout("default", ["head" => $head]);

?>
<section class="reference-hero">
    <img class="reference-hero-image" src="<?= $this->e($this->asset("images/hero-notebook.jpg")) ?>"
         alt="Profissional usando um notebook" width="1400" height="933" fetchpriority="high">
    <div class="section-shell reference-hero-content reveal">
        <div class="hero-index" aria-hidden="true">01</div>
        <div><p class="eyebrow">MOVES · ESTRATÉGIA, DESIGN E TECNOLOGIA</p>
            <h1>Sua próxima ideia.<br>Uma nova <span class="brand-gradient">presença<span
                            class="mobile-break"><br></span> digital.</span></h1><a class="hero-portfolio-link"
                                                                                    href="<?= $this->e(url("solucoes")) ?>">EXPLORE
                AS POSSIBILIDADES <span>↗</span></a></div>
    </div>
</section>
<section class="reference-overlap">
    <div><span class="reference-symbol" aria-hidden="true">↗</span>
        <p>Design que conecta.<br>Tecnologia que faz acontecer.</p></div>
    <div><p class="eyebrow">SITES COM PROPÓSITO</p>
        <p>Uma experiência clara para apresentar seu negócio e iniciar novas conversas.</p></div>
</section>
<section class="stats band stats-results" aria-label="Moves em números">
    <div><strong data-count="150" data-prefix="+" data-suffix=""
                 aria-label="+0">+0</strong><span>projetos entregues</span></div>
    <div><strong data-count="12" data-prefix="+" data-suffix=""
                 aria-label="+0">+0</strong><span>anos de experiência</span></div>
    <div><strong data-count="60" data-prefix="+" data-suffix=""
                 aria-label="+0">+0</strong><span>clientes atendidos</span></div>
    <div><strong data-count="98" data-prefix="" data-suffix="%" aria-label="0%">0%</strong><span>de satisfação</span>
    </div>
    <div class="territory"><p>Soluções<br>em todo<br>o Brasil</p><span class="brazil-reference"
                                                                       aria-hidden="true"></span></div>
</section>
<section class="section-shell block" id="services">
    <div class="section-heading">
        <div><p class="eyebrow">O QUE FAZEMOS</p>
            <h2>Soluções completas<br>para o seu <span class="brand-gradient">próximo passo.</span></h2></div>
        <a class="text-link" href="<?= $this->e(url("solucoes")) ?>">Conheça as soluções →</a></div>
    <div class="services-grid">
        <article class="service-card card-1"><span class="service-icon">01</span>
            <h3>Criação de Sites</h3>
            <p>Sites institucionais e experiências digitais que geram resultados.</p><a
                    href="<?= $this->e(url("solucoes#criacao-de-sites")) ?>"
                    aria-label="Conhecer Criação de Sites">↗</a></article>
        <article class="service-card card-2"><span class="service-icon">02</span>
            <h3>Sistemas Web</h3>
            <p>Plataformas e integrações sob medida para o seu negócio.</p><a
                    href="<?= $this->e(url("solucoes#sistemas-web")) ?>" aria-label="Conhecer Sistemas Web">↗</a>
        </article>
        <article class="service-card card-apps"><span class="service-icon">03</span>
            <h3>Aplicativos</h3>
            <p>Experiências móveis com foco nos fluxos que seu público precisa realizar.</p><a
                    href="<?= $this->e(url("solucoes#aplicativos")) ?>" aria-label="Conhecer Aplicativos">↗</a>
        </article>
        <article class="service-card card-hosting"><span class="service-icon">04</span>
            <h3>Hospedagem</h3>
            <p>Ambientes planejados para as necessidades técnicas da sua operação.</p><a
                    href="<?= $this->e(url("solucoes#hospedagem")) ?>" aria-label="Conhecer Hospedagem">↗</a></article>
    </div>
</section>
<section class="section-shell process block">
    <div class="section-heading">
        <div><p class="eyebrow">COMO TRABALHAMOS</p>
            <h2>Da ideia ao resultado,<br>sem perder o <span class="brand-gradient">movimento.</span></h2></div>
    </div>
    <div class="process-grid">
        <article><span>01</span>
            <h3>Descobrir</h3>
            <p>Entendemos seu desafio, mercado e oportunidades.</p></article>
        <article><span>02</span>
            <h3>Estruturar</h3>
            <p>Definimos a estratégia e o melhor caminho.</p></article>
        <article><span>03</span>
            <h3>Criar</h3>
            <p>Transformamos ideias em experiências.</p></article>
        <article><span>04</span>
            <h3>Desenvolver</h3>
            <p>Damos vida ao projeto com tecnologia.</p></article>
        <article><span>05</span>
            <h3>Evoluir</h3>
            <p>Acompanhamos, otimizamos e geramos resultados.</p></article>
    </div>
</section>
<section class="section-shell about-editorial" id="about">
    <div class="about-manifesto">MAIS QUE PROJETOS.<br>CONSTRUÍMOS<br>PARCERIAS.<span></span></div>
    <div class="about-editorial-image"><img src="<?= $this->e($this->asset("images/team.jpg")) ?>"
                                            alt="Profissionais colaborando em um projeto digital" width="1400"
                                            height="2099" loading="lazy"></div>
    <div class="about-editorial-copy"><p class="eyebrow">SOMOS A MOVES</p>
        <h2>Uma equipe que conecta ideias e <span class="brand-gradient">ama</span> o que faz.</h2>
        <p>Unimos estratégia, design e tecnologia para construir soluções com você, do primeiro desafio à próxima
            evolução.</p><a class="text-link" href="<?= $this->e(url("sobre")) ?>">Conheça a Moves →</a></div>
</section>
<section class="section-shell block" id="content">
    <div class="section-heading">
        <div><p class="eyebrow">CONTEÚDO</p>
            <h2>Insights para<br>você ir <span class="brand-gradient">mais longe.</span></h2></div>
        <a class="text-link" href="<?= $this->e(url("blog")) ?>">Acessar o blog →</a></div>
    <div class="content-empty compact"><p>Novos conteúdos serão publicados aqui após a integração com o CMS Moves.</p>
    </div>
</section>
<section class="cta" id="contact">
    <div class="cta-inner">
        <div><p class="eyebrow">VAMOS CONVERSAR?</p>
            <h2>Tem algo que precisa<br>sair do lugar?</h2>
            <p>Vamos colocar sua ideia em movimento.</p></div>
        <a class="button button-primary large" href="<?= $this->e(url("contato")) ?>">Começar uma conversa ↗</a></div>
</section>
