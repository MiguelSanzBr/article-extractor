<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\JsonLdBody;

$body = "Primeiro parágrafo do corpo vindo do JSON-LD, com bastante texto para contar.\n\n"
    . "Segundo parágrafo do corpo vindo do JSON-LD, também com bastante texto aqui.\n"
    . "curto\n"
    . 'Terceiro parágrafo do corpo vindo do JSON-LD, fechando a matéria com mais texto e detalhes finais.';

it('quebra o articleBody em parágrafos', function () use ($body) {
    $strategy = new JsonLdBody();
    $blocks = $strategy->extract(page(ld(['@type' => 'NewsArticle', 'articleBody' => $body]), '<article class="card"><p>Teaser curto da matéria aqui.</p></article>'));

    expect($strategy->name())->toBe('json-ld')
        ->and(blockTypes($blocks))->toBe(['paragraph', 'paragraph', 'paragraph']);
});

it('lê articleBody em HTML', function () use ($body) {
    $html = '<p>' . str_replace("\n", '</p><p>', $body) . '</p><h2>Seção do meio</h2>';

    expect(blockTypes((new JsonLdBody())->extract(page(ld(['@type' => 'Article', 'articleBody' => $html])))))
        ->toBe(['paragraph', 'paragraph', 'paragraph', 'heading']);
});

it('usa articleBody de item que não é do tipo artigo', function () use ($body) {
    expect((new JsonLdBody())->extract(page(ld(['@type' => 'WebPage', 'articleBody' => $body]))))->toHaveCount(3);
});

it('não devolve nada sem articleBody em texto ou abaixo do piso', function (array $item) {
    expect((new JsonLdBody())->extract(page(ld($item))))->toBe([]);
})->with([
    [['@type' => 'NewsArticle', 'headline' => 'Sem corpo']],
    [['@type' => 'NewsArticle', 'articleBody' => ['lista']]],
    [['@type' => 'NewsArticle', 'articleBody' => 'Corpo curto demais, só uma linha de teaser.']],
]);
