<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\DensityHeuristic;

it('acha o corpo num div sem classe conhecida', function () {
    $strategy = new DensityHeuristic();
    $body = '<article class="card"><p>Teaser curto de outra matéria aqui.</p></article><div class="x9">' . paragraphs(5) . '</div>';

    expect($strategy->name())->toBe('density')
        ->and($strategy->extract(page('', $body)))->toHaveCount(5);
});

it('escolhe o container do corpo e deixa a coluna de links de fora', function () {
    $links = str_repeat('<p><a href="/x">Outra matéria com título bem comprido para chamar atenção</a></p>', 8);
    $blocks = (new DensityHeuristic())->extract(page('', '<div id="wrap"><div class="col-a">' . paragraphs(4) . "</div><div class=\"col-b\">{$links}</div></div>"));

    expect($blocks)->toHaveCount(4)
        ->and(implode(' ', array_map(static fn($b) => $b->text, $blocks)))->not->toContain('Outra matéria');
});

it('soma no avô os parágrafos que vêm cada um num wrapper', function () {
    $wrapped = '';
    for ($i = 1; $i <= 6; $i++) {
        $wrapped .= "<div class=\"paragraph\"><p>Trecho {$i} da matéria com bastante texto para contar no corpo da página aqui.</p></div>";
    }

    expect((new DensityHeuristic())->extract(page('', "<div class=\"corpo\">{$wrapped}</div>")))->toHaveCount(6);
});

it('não devolve nada quando nenhum candidato passa no piso', function () {
    expect((new DensityHeuristic())->extract(page('', '<div><p>Curto.</p><p>Pouco texto aqui mesmo.</p></div>')))->toBe([]);
});
