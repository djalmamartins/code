<?php

/**
 * Moves Code | Soluções
 *
 * Apresenta as frentes de atuação institucional da Moves.
 *
 * @author Djalma Martins
 * @package Moves\Themes\Web\MovesV1\Pages
 */

$this->layout("default", ["head" => $head]);
$solutions = [
    ["criacao-de-sites", "card-1", "01", "CRIAÇÃO DE SITES", "Sua marca merece uma", "presença à altura.", "Sites institucionais e landing pages que apresentam seu negócio com clareza e ajudam o visitante a dar o próximo passo.", ["Arquitetura de informação e organização do conteúdo", "Design responsivo para celular, tablet e computador", "Desenvolvimento com atenção a desempenho e acessibilidade", "Estrutura técnica para indexação em buscadores"]],
    ["sistemas-web", "card-2", "02", "SISTEMAS WEB", "Menos barreiras.", "Mais possibilidades.", "Transformamos processos do seu negócio em ferramentas práticas, com regras e integrações planejadas para sua operação.", ["Mapeamento de processos e perfis de acesso", "Painéis, cadastros e fluxos sob medida", "Integração com APIs e ferramentas existentes", "Documentação e planejamento de evolução"]],
    ["aplicativos", "card-apps", "03", "APLICATIVOS", "Sua ideia, mais perto", "das pessoas.", "Planejamos experiências para dispositivos móveis com foco nos fluxos que o seu público precisa realizar.", ["Definição do produto e público", "Protótipos dos principais fluxos", "Desenvolvimento e integrações conforme o escopo", "Preparação para distribuição e evolução"]],
    ["hospedagem", "card-hosting", "04", "HOSPEDAGEM", "Uma base para sua", "presença digital.", "Planejamos o ambiente de hospedagem de acordo com as necessidades técnicas do seu site e da sua operação.", ["Análise dos requisitos do site", "Orientação sobre domínio e certificado", "Configuração de ambiente conforme a proposta", "Definição de rotinas de atualização e cópias de segurança"]],
];

?>
<section class="page-hero section-shell services-hero"><p class="eyebrow">NOSSAS SOLUÇÕES</p><h1>Tecnologia para cada<br><span class="brand-gradient">próximo passo.</span></h1><p class="page-lead">Da presença digital às ferramentas do dia a dia. Conheça as quatro frentes de trabalho da Moves.</p></section>
<nav class="section-shell service-jump" aria-label="Explorar soluções"><a href="#criacao-de-sites">Criação de Sites ↓</a><a href="#sistemas-web">Sistemas Web ↓</a><a href="#aplicativos">Aplicativos ↓</a><a href="#hospedagem">Hospedagem ↓</a></nav>
<?php foreach ($solutions as [$id, $class, $number, $label, $title, $highlight, $description, $items]): ?>
    <section class="section-shell service-fold" id="<?= $this->e($id) ?>">
        <div class="service-fold-photo <?= $this->e($class) ?>" role="img" aria-label="Imagem ilustrativa de <?= $this->e(str_title(mb_strtolower($label))) ?>"><span class="service-fold-number" aria-hidden="true"><?= $this->e($number) ?></span><span class="service-fold-label" aria-hidden="true"><?= $this->e(str_title(mb_strtolower($label))) ?></span></div>
        <div class="service-fold-copy"><p class="eyebrow"><?= $this->e($number . " / " . $label) ?></p><h2><?= $this->e($title) ?><br><span class="brand-gradient"><?= $this->e($highlight) ?></span></h2><p><?= $this->e($description) ?></p><ul><?php foreach ($items as $item): ?><li><?= $this->e($item) ?></li><?php endforeach; ?></ul><a class="text-link" href="<?= $this->e(url("contato?servico=" . $id)) ?>">Conversar sobre <?= $this->e(mb_strtolower($label)) ?> ↗</a></div>
    </section>
<?php endforeach; ?>
<section class="cta"><div class="cta-inner"><div><p class="eyebrow">SEU PRÓXIMO MOVIMENTO</p><h2>Vamos tirar sua<br>ideia do papel?</h2><p>Conte seu desafio. Construímos o próximo passo juntos.</p></div><a class="button button-primary large" href="<?= $this->e(url("contato")) ?>">Conversar sobre meu projeto ↗</a></div></section>
