<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\EmbeddedAppBody;

const SENTENCE = 'Frase longa do corpo vinda do estado da aplicação, com texto suficiente';

function fusionPage(array $elements): ArticleExtractor\Page
{
    return page('', '<script>Fusion.globalContent=' . json_encode(['content_elements' => $elements]) . ';</script>');
}

it('converte os content_elements do Arc XP', function () {
    $strategy = new EmbeddedAppBody();
    $blocks = $strategy->extract(fusionPage([
        ['type' => 'text', 'content' => SENTENCE . ' <b>um</b>.'],
        ['type' => 'header', 'content' => 'Contexto'],
        ['type' => 'list', 'items' => [['content' => SENTENCE . ' dois.'], SENTENCE . ' três.']],
        ['type' => 'image', 'url' => '/f.jpg'],
        ['type' => 'raw_html', 'content' => '<p>' . SENTENCE . ' quatro.</p>'],
        ['type' => 'text', 'content' => 'Leia também: outra'],
        ['type' => 'text', 'content' => 7],
    ]));

    expect($strategy->name())->toBe('embedded-app')
        ->and(blockTypes($blocks))->toBe(['paragraph', 'heading', 'list_item', 'list_item', 'paragraph'])
        ->and($blocks[0]->text)->toBe(SENTENCE . ' um.');
});

it('lê o corpo HTML do artigo no __NEXT_DATA__', function () {
    $content = '<p>' . SENTENCE . ' um.</p><h2>Seção</h2><p>' . SENTENCE . ' dois.</p><p>' . SENTENCE . ' três.</p><p>' . SENTENCE . ' quatro.</p>';
    $page = page('', '<script id="__NEXT_DATA__" type="application/json">' . json_encode(['props' => ['pageProps' => ['post' => ['content' => $content]]]]) . '</script>');

    expect(blockTypes((new EmbeddedAppBody())->extract($page)))->toBe(['paragraph', 'heading', 'paragraph', 'paragraph', 'paragraph']);
});

it('lê corpo em texto puro no __NEXT_DATA__', function () {
    $body = implode("\n", [SENTENCE . ' um.', SENTENCE . ' dois.', SENTENCE . ' três.', SENTENCE . ' quatro.']);
    $page = page('', '<script id="__NEXT_DATA__" type="application/json">' . json_encode(['props' => ['pageProps' => ['article' => ['body' => $body]]]]) . '</script>');

    expect((new EmbeddedAppBody())->extract($page))->toHaveCount(4);
});

it('não devolve nada sem objeto de artigo ou sem payload', function (string $body) {
    expect((new EmbeddedAppBody())->extract(page('', $body)))->toBe([]);
})->with([
    '<script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"title":"Só título"}}}</script>',
    '<script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"post":{"content":""}}}}</script>',
    '<script>Fusion.globalContent={"headlines":{"basic":"Sem corpo"}};</script>',
    '<p>nada</p>',
]);
