<?php

declare(strict_types=1);

use ArticleExtractor\MetadataSources\HtmlFallback;

function htmlMeta(string $head, string $body, string $url = 'https://a.com/n/1'): ArticleExtractor\Metadata
{
    return (new HtmlFallback())->read(page($head, $body, $url), utc());
}

it('usa o primeiro h1 como título', function () {
    expect(htmlMeta('<title>Outro | Site</title>', '<h1> Título  do h1 </h1>')->title)->toBe('Título do h1');
});

it('corta o nome do site do <title> só em separador com espaço', function () {
    $meta = htmlMeta('<title>Covid-19 avança no país | Jornal Exemplo</title>', '');

    expect($meta->title)->toBe('Covid-19 avança no país')
        ->and($meta->siteName)->toBe('Jornal Exemplo')
        ->and(htmlMeta('<title>Covid-19 avança</title>', '')->title)->toBe('Covid-19 avança')
        ->and(htmlMeta('<title>Covid-19 avança</title>', '')->siteName)->toBeNull()
        ->and(htmlMeta('<title>A - B - Jornal</title>', '')->title)->toBe('A - B');
});

it('não usa como site um segmento longo demais', function () {
    expect(htmlMeta('<title>Título — ' . str_repeat('x', 70) . '</title>', '')->siteName)->toBeNull();
});

it('acha a linha fina perto do título', function () {
    expect(htmlMeta('', '<h1>T</h1><p class="lead">Curta</p><p class="standfirst">Uma linha fina com tamanho bom para resumo</p>')->subtitle)
        ->toBe('Uma linha fina com tamanho bom para resumo');
});

it('lê autores de itemprop, rel=author e byline', function () {
    $meta = htmlMeta('', '<div itemprop="author"><span itemprop="name">Ana Lima</span></div><a rel="author" href="/a/b">Bruno Reis</a><div class="byline"><span class="author">Ana Lima</span></div><meta itemprop="name" content="Fora">');

    expect($meta->authors)->toBe(['Ana Lima', 'Bruno Reis']);
});

it('lê o itemprop name pelo atributo content', function () {
    expect(htmlMeta('', '<div itemprop="author"><meta itemprop="name" content="Carla Dias"></div>')->authors)->toBe(['Carla Dias']);
});

it('só lê data de dentro do artigo, nunca da barra lateral', function () {
    $meta = htmlMeta('', '<aside><time datetime="2020-01-01T00:00:00Z">velho</time></aside><article><time datetime="2026-03-05T10:00:00-03:00">hoje</time></article>');

    expect($meta->publishedAt?->format('c'))->toBe('2026-03-05T13:00:00+00:00')
        ->and(htmlMeta('', '<aside><time datetime="2020-01-01">x</time></aside>')->publishedAt)->toBeNull();
});

it('lê datePublished por content, datetime ou texto', function (string $body) {
    expect(htmlMeta('', "<main>{$body}</main>")->publishedAt?->format('Y-m-d'))->toBe('2026-03-05');
})->with([
    '<span itemprop="datePublished" content="2026-03-05">x</span>',
    '<span itemprop="datePublished" datetime="2026-03-05">x</span>',
    '<span itemprop="datePublished">05/03/2026</span>',
]);

it('pula placeholder data: e acha a imagem preguiçosa', function () {
    expect(htmlMeta('', '<article><img src="data:image/gif;base64,R0" data-src="/fotos/a.jpg"></article>')->image)->toBe('https://a.com/fotos/a.jpg')
        ->and(htmlMeta('', '<figure><img srcset="/p.jpg 480w, /g.jpg 1024w"></figure>')->image)->toBe('https://a.com/p.jpg')
        ->and(htmlMeta('', '<main><img data-lazy-src="l.jpg"></main>', 'https://a.com/n/1')->image)->toBe('https://a.com/n/l.jpg')
        ->and(htmlMeta('', '<article><img src="data:x"><img src="/b.jpg"></article>')->image)->toBe('https://a.com/b.jpg')
        ->and(htmlMeta('', '<img src="/fora.jpg">')->image)->toBeNull();
});

it('monta a seção pelo breadcrumb sem home e sem repetir', function () {
    $meta = htmlMeta('', '<nav class="breadcrumb"><a>Home</a><a>Economia</a><a>Economia</a><a>Juros</a><a>Mercados</a><a>Ações</a><a>X</a></nav>');

    expect($meta->section)->toBe('Economia, Juros, Mercados')
        ->and(htmlMeta('', '')->section)->toBeNull();
});

it('devolve vazio para HTML vazio', function () {
    $meta = htmlMeta('', '');

    expect($meta->title)->toBeNull()
        ->and($meta->authors)->toBe([])
        ->and($meta->image)->toBeNull();
});
