<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\PrioritySelectors;

it('acha o corpo por seletor conhecido', function () {
    $strategy = new PrioritySelectors();

    expect($strategy->name())->toBe('priority-selector')
        ->and($strategy->extract(page('', '<div class="entry-content">' . paragraphs(4) . '</div>')))->toHaveCount(4);
});

it('entre vários candidatos fica com o de mais texto', function () {
    $blocks = (new PrioritySelectors())->extract(page('', '<div class="article-body">' . paragraphs(4, 'Curto') . '</div><div itemprop="articleBody">' . paragraphs(6, 'Longo') . '</div>'));

    expect($blocks)->toHaveCount(6)
        ->and($blocks[0]->text)->toStartWith('Longo 1');
});

it('não devolve nada abaixo do piso ou sem seletor conhecido', function (string $body) {
    expect((new PrioritySelectors())->extract(page('', $body)))->toBe([]);
})->with([
    '<div class="entry-content">' . '<p>Só um parágrafo curto de teaser aqui, nada mais.</p>' . '</div>',
    '<div class="qualquer">' . paragraphs(6) . '</div>',
]);
