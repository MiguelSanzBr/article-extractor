<?php

declare(strict_types=1);

use ArticleExtractor\Discovery\FeedItem;
use ArticleExtractor\Discovery\FeedParseException;
use ArticleExtractor\Discovery\FeedParser;

function feed(string $name, string $url = 'https://feed.example/feed'): ArticleExtractor\Discovery\FeedDocument
{
    return FeedParser::parse((string) file_get_contents(__DIR__ . "/../../Fixtures/xml/{$name}.xml"), $url);
}

/** @return list<string> */
function urls(array $items): array
{
    return array_map(static fn (FeedItem $i): string => $i->url, $items);
}

it('lê RSS 2.0, usa guid permalink e põe o mais novo primeiro', function () {
    $doc = feed('rss2');

    expect(urls($doc->items))->toBe([
        'https://diario.example/cidade/2026/03/05/bibliotecas-domingo',
        'https://diario.example/cidade/2026/03/04/so-com-guid',
        'https://diario.example/cultura/2026/03/01/feira-do-livro',
    ])
        ->and($doc->items[0]->title)->toBe('Bibliotecas abrem aos domingos')
        ->and($doc->items[0]->publishedAt?->format('c'))->toBe('2026-03-05T12:30:00+00:00')
        ->and($doc->sitemaps)->toBe([]);
});

it('lê RSS 1.0 (RDF)', function () {
    $doc = feed('rss1-rdf');

    expect($doc->items)->toHaveCount(2)
        ->and($doc->items[0]->title)->toBe('Feira de ciências reúne mil estudantes')
        ->and($doc->items[0]->publishedAt?->format('c'))->toBe('2026-03-02T12:00:00+00:00');
});

it('lê Atom com namespace extra, só o link alternate e URL relativa', function () {
    $doc = feed('atom-media', 'https://tech.example/feed.atom');

    expect(urls($doc->items))->toBe([
        'https://tech.example/politica/lei-de-dados',
        'https://tech.example/hardware/chip-nacional',
        'https://tech.example/startups/investimento',
    ]);
});

it('lê sitemap do Yoast sem confundir título de imagem com título da matéria', function () {
    $doc = feed('sitemap-yoast');

    expect($doc->items)->toHaveCount(3)
        ->and($doc->items[0]->url)->toBe('https://diario.example/cidade/2026/03/05/bibliotecas-domingo')
        ->and($doc->items[0]->title)->toBeNull()
        ->and($doc->items[2]->publishedAt?->format('Y-m-d'))->toBe('2026-03-01');
});

it('lê título e data do sitemap de notícias', function () {
    $doc = feed('sitemap-news');

    expect($doc->items[0]->title)->toBe('Câmara aprova plano de mobilidade')
        ->and($doc->items[0]->publishedAt?->format('c'))->toBe('2026-03-03T18:00:00+00:00')
        ->and($doc->items[1]->publishedAt?->format('c'))->toBe('2026-03-03T00:10:00+00:00');
});

it('lê índice de sitemap com o mais recente primeiro', function () {
    $doc = feed('sitemap-index', 'https://diario.example/sitemap_index.xml');

    expect(array_map(static fn ($s) => $s->url, $doc->sitemaps))->toBe([
        'https://diario.example/post-sitemap3.xml',
        'https://diario.example/post-sitemap2.xml',
        'https://diario.example/post-sitemap1.xml',
        'https://diario.example/page-sitemap.xml',
    ])
        ->and($doc->sitemaps[0]->lastModified?->format('Y-m-d'))->toBe('2026-03-05')
        ->and($doc->sitemaps[3]->lastModified)->toBeNull()
        ->and($doc->items)->toBe([]);
});

it('aceita BOM, resolve link relativo e põe sem data no fim', function () {
    $doc = feed('bom-relative', 'https://radio.example/rss/');

    expect(urls($doc->items))->toBe([
        'https://radio.example/rss/noticia/nova',
        'https://radio.example/noticia/antiga',
        'https://radio.example/noticia/sem-data-nenhuma',
        'https://radio.example/noticia/data-quebrada',
    ])
        ->and($doc->items[2]->publishedAt)->toBeNull();
});

it('recusa o que não é feed nem sitemap', function (string $xml) {
    FeedParser::parse($xml, 'https://a.example/feed');
})->throws(FeedParseException::class)->with([
    'HTML de soft-404' => ['<html><body><p>Página não encontrada</p></body></html>'],
    'XML truncado' => ['<rss><channel><item><title>x</title>'],
    'XML de outro tipo' => ['<?xml version="1.0"?><config><a>1</a></config>'],
    'vazio' => ['   '],
]);

it('não expande entidade externa', function () {
    $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY e SYSTEM "file:///etc/hostname">]><rss><channel><item><title>&e;</title><link>https://a.example/noticia/x</link></item></channel></rss>';

    expect(FeedParser::parse($xml, 'https://a.example/feed')->items[0]->title)->toBeNull();
});
