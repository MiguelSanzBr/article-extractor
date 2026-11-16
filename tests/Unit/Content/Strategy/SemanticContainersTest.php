<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\SemanticContainers;

it('acha o corpo em article, main ou role=main', function (string $tag) {
    $html = $tag === 'role' ? '<div role="main">' . paragraphs(4) . '</div>' : "<{$tag}>" . paragraphs(4) . "</{$tag}>";
    $strategy = new SemanticContainers();

    expect($strategy->name())->toBe('semantic-container')
        ->and($strategy->extract(page('', $html)))->toHaveCount(4);
})->with(['article', 'main', 'role']);

it('teaser de um parágrafo em article não vence', function () {
    $body = '<article class="card"><p>' . str_repeat('Chamada curta de outra matéria. ', 4) . '</p></article><div>' . paragraphs(5) . '</div>';

    expect((new SemanticContainers())->extract(page('', $body)))->toBe([]);
});

it('lista de chamadas curtas perde para o corpo de verdade', function () {
    $teasers = str_repeat('<p>Chamada curta para outra matéria.</p>', 10);
    $blocks = (new SemanticContainers())->extract(page('', "<article>{$teasers}</article><article>" . paragraphs(4) . '</article>'));

    expect($blocks)->toHaveCount(4)
        ->and($blocks[0]->text)->toStartWith('Parágrafo 1');
});

it('container cheio de títulos perde para o corpo de verdade', function () {
    $headings = '';
    for ($i = 1; $i <= 5; $i++) {
        $headings .= "<h2>Título de seção número {$i}</h2>";
    }
    $blocks = (new SemanticContainers())->extract(page('', "<article>{$headings}" . paragraphs(5, 'Lista') . '</article><article>' . paragraphs(4) . '</article>'));

    expect($blocks[0]->text)->toStartWith('Parágrafo 1');
});
