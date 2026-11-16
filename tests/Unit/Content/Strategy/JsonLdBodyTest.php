<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\JsonLdBody;

$body = "Primeiro parágrafo do corpo vindo do JSON-LD, com bastante texto para contar como parte do corpo.\n\n"
    . "Segundo parágrafo do corpo vindo do JSON-LD, também com bastante texto aqui para somar no corpo.\n"
    . "curto\n"
    . 'Terceiro parágrafo do corpo vindo do JSON-LD, fechando a matéria com mais texto e detalhes finais sobre o assunto.';

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

it('tira tag inline do corpo em texto e aceita linha de 20 caracteres', function () {
    $body = "Texto com <b>negrito</b> no meio do parágrafo, longo o bastante para contar no corpo da matéria.\n"
        . str_repeat('x', 20) . "\n" . str_repeat('y', 19) . "\n"
        . str_repeat('Mais uma frase longa para passar do piso de trezentos caracteres. ', 4);
    $blocks = (new JsonLdBody())->extract(page(ld(['@type' => 'NewsArticle', 'articleBody' => $body])));

    expect($blocks[0]->text)->toStartWith('Texto com negrito no meio')
        ->and($blocks[1]->text)->toBe(str_repeat('x', 20))
        ->and($blocks)->toHaveCount(3);
});

it('prefere o articleBody do artigo e, sem artigo, o primeiro item com corpo', function () use ($body) {
    $other = str_replace('JSON-LD', 'outro item', $body);
    $third = str_replace('JSON-LD', 'terceiro item', $body);

    expect((new JsonLdBody())->extract(page(ld(['@graph' => [['@type' => 'WebPage', 'articleBody' => $other], ['@type' => 'NewsArticle', 'articleBody' => $body]]])))[0]->text)->toContain('JSON-LD')
        ->and((new JsonLdBody())->extract(page(ld(['@graph' => [['@type' => 'WebPage', 'articleBody' => $other], ['@type' => 'WebSite', 'articleBody' => $third]]])))[0]->text)->toContain('outro item');
});
