<?php

declare(strict_types=1);

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\BlockExtractor;

const LONG = 'Este parágrafo tem texto suficiente para contar como corpo.';

/** @return list<array{string, string}> */
function blocksOf(string $body, string $selector = 'article'): array
{
    $container = page('', $body)->document->querySelector($selector);
    assert($container !== null);

    return array_map(static fn(Block $b): array => [$b->type->value, $b->text], BlockExtractor::fromElement($container));
}

it('extrai blocos de vários tipos na ordem do documento', function () {
    $blocks = blocksOf('<article><h2>Contexto</h2><p>' . LONG . '</p><ul><li>Primeiro item da lista com texto</li><li>Segundo item da lista com texto</li></ul>'
        . '<blockquote><p>Uma citação longa o bastante pra contar.</p></blockquote><figure><figcaption>Legenda da foto com bastante texto</figcaption></figure><h5>Nota</h5></article>');

    expect($blocks)->toBe([
        ['heading', 'Contexto'],
        ['paragraph', LONG],
        ['list_item', 'Primeiro item da lista com texto'],
        ['list_item', 'Segundo item da lista com texto'],
        ['quote', 'Uma citação longa o bastante pra contar.'],
        ['caption', 'Legenda da foto com bastante texto'],
        ['heading', 'Nota'],
    ]);
});

it('usa os parágrafos de dentro de item e citação sem duplicar', function () {
    expect(blocksOf('<article><ul><li><p>Item com parágrafo dentro dele aqui</p></li></ul><blockquote>Texto solto da citação com tamanho bom</blockquote></article>'))
        ->toBe([['list_item', 'Item com parágrafo dentro dele aqui'], ['quote', 'Texto solto da citação com tamanho bom']]);
});

it('não repete o texto da sublista no item de fora', function () {
    expect(blocksOf('<article><ul><li>Item de fora com texto bastante<ul><li>Item de dentro com texto bastante</li></ul></li></ul></article>'))
        ->toBe([['list_item', 'Item de fora com texto bastante'], ['list_item', 'Item de dentro com texto bastante']]);
});

it('descarta texto curto demais', function () {
    expect(blocksOf('<article><h2>Ok</h2><h3>Sim</h3><p>Curto demais.</p></article>'))->toBe([['heading', 'Sim']]);
});

it('remove parágrafo repetido', function () {
    expect(blocksOf('<article><p>' . LONG . '</p><p>' . LONG . '</p></article>'))->toBe([['paragraph', LONG]]);
});

it('pula ruído, chamada, carimbo de data e bloco que é link', function () {
    $blocks = blocksOf('<article>'
        . '<div class="share-bar"><p>Compartilhe esta matéria com seus amigos agora</p></div>'
        . '<p>Leia também: outra matéria qualquer</p>'
        . '<p>Publicado em 05/03/2026 10:30</p>'
        . '<a href="/x"><h2>Manchete de outra matéria</h2><p>Teaser de outra matéria com texto</p></a>'
        . '<h2><a href="/y">Título que é todo link aqui</a></h2>'
        . '<p>Veja: <a href="/z">uma matéria relacionada com título comprido</a></p>'
        . '<p>' . LONG . ' Tem <a href="/w">um link</a> no meio.</p>'
        . '</article>');

    expect($blocks)->toBe([['paragraph', LONG . ' Tem um link no meio.']]);
});

it('não olha a classe do próprio container', function () {
    expect(blocksOf('<div class="sidebar"><p>' . LONG . '</p></div>', 'div'))->toBe([['paragraph', LONG]]);
});

it('não altera o container original', function () {
    $page = page('', '<article><aside>lateral</aside><p>' . LONG . '</p></article>');
    $container = $page->document->querySelector('article');
    assert($container !== null);
    $before = $page->document->saveHtml($container);

    BlockExtractor::fromElement($container);

    expect($page->document->saveHtml($container))->toBe($before);
});

it('extrai de fragmento HTML', function () {
    expect(array_map(static fn(Block $b): string => $b->type->value, BlockExtractor::fromHtmlFragment('<p>' . LONG . '</p><h2>Seção</h2>')))
        ->toBe(['paragraph', 'heading']);
});

it('diz quais tipos são corpo', function () {
    expect(BlockType::Paragraph->isBody())->toBeTrue()
        ->and(BlockType::ListItem->isBody())->toBeTrue()
        ->and(BlockType::Quote->isBody())->toBeTrue()
        ->and(BlockType::Heading->isBody())->toBeFalse()
        ->and(BlockType::Caption->isBody())->toBeFalse();
});

it('mantém parágrafo longo mesmo quando quase todo o texto é link', function () {
    $linked = str_repeat('texto do link que é bem comprido ', 7);

    expect(blocksOf("<article><p>Ver <a href=\"/x\">{$linked}</a></p></article>"))->toHaveCount(1);
});

it('item, citação e legenda com texto além do parágrafo viram só o parágrafo', function () {
    expect(blocksOf('<article><ul><li><p>Parágrafo dentro do item da lista</p> e um texto solto depois</li></ul>'
        . '<blockquote><p>Parágrafo dentro da citação do texto</p> — Fulano de Tal, autor</blockquote>'
        . '<figure><figcaption><p>Parágrafo dentro da legenda da foto</p> Foto: Agência Exemplo</figcaption></figure></article>'))
        ->toBe([
            ['list_item', 'Parágrafo dentro do item da lista'],
            ['quote', 'Parágrafo dentro da citação do texto'],
            ['caption', 'Parágrafo dentro da legenda da foto'],
        ]);
});

it('citação com lista dentro não repete o texto dos itens', function () {
    expect(blocksOf('<article><blockquote>Citação com uma lista logo abaixo dela<ul><li>Primeiro item da lista citada</li></ul></blockquote></article>'))
        ->toBe([['quote', 'Citação com uma lista logo abaixo dela'], ['list_item', 'Primeiro item da lista citada']]);
});

it('título é link a partir de 80% do texto em link direto', function () {
    expect(blocksOf('<article><h2><a href="/x">ABCDEFGH</a>IJ</h2></article>'))->toBe([])
        ->and(blocksOf('<article><h2><a href="/x">ABCDEFG</a>HIJ</h2></article>'))->toBe([['heading', 'ABCDEFGHIJ']])
        ->and(blocksOf('<article><h2><span><a href="/x">Título todo em link aninhado</a></span></h2></article>'))->toBe([['heading', 'Título todo em link aninhado']]);
});

it('parágrafo conta link aninhado e ignora link vazio', function () {
    expect(blocksOf('<article><p>Veja <strong><a href="/x">uma matéria relacionada com título comprido</a></strong></p></article>'))->toBe([])
        ->and(blocksOf('<article><p><a href="#"></a><a href="#"></a>Um parágrafo curto com ícones de link</p></article>'))->toHaveCount(1);
});

it('parágrafo de exatamente 200 caracteres não é bloco de link', function () {
    $linked = str_repeat('l', 196);

    expect(blocksOf("<article><p>Ver <a href=\"/x\">{$linked}</a></p></article>"))->toHaveCount(1);
});
