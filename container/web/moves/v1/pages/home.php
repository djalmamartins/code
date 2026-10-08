<?php

/**
 * Moves Code | Home
 *
 * Apresenta a página inicial pública do site Moves.
 *
 * @author Djalma Martins
 * @package Moves\Web
 */

$this->layout("default", ["head" => $head]);

?>
<section aria-labelledby="home-title">
    <h1 id="home-title"><?= $this->e($heading) ?></h1>
    <p><?= $this->e($description) ?></p>
</section>
