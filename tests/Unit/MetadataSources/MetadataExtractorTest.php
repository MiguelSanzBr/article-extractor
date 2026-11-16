<?php

declare(strict_types=1);

use ArticleExtractor\MetadataSources\MetadataExtractor;

function extractMeta(string $head, string $body = ''): ArticleExtractor\Metadata
{
    return MetadataExtractor::default()->extract(page($head, $body), utc());
}

it('og:title vence o h1', function () {
    expect(extractMeta('<meta property="og:title" content="Do OG">', '<h1>Do h1</h1>')->title)->toBe('Do OG');
});

it('sem meta, o headline do JSON-LD vence o h1', function () {
    expect(extractMeta(ld(['@type' => 'NewsArticle', 'headline' => 'Do JSON-LD']), '<h1>Do h1</h1>')->title)->toBe('Do JSON-LD');
});

it('campo ruim no JSON-LD cai para a fonte seguinte', function () {
    $meta = extractMeta(
        ld(['@type' => 'NewsArticle', 'headline' => ['x'], 'datePublished' => 20260305, 'description' => 'Ok &amp; certo']),
        '<h1>Título do h1</h1><article><time datetime="2026-03-05T10:00:00Z">x</time></article>',
    );

    expect($meta->title)->toBe('Título do h1')
        ->and($meta->publishedAt?->format('c'))->toBe('2026-03-05T10:00:00+00:00')
        ->and($meta->subtitle)->toBe('Ok & certo');
});

it('tudo vazio para HTML vazio', function () {
    expect(MetadataExtractor::default()->extract(ArticleExtractor\Page::parse('', 'https://a.com/'), utc()))
        ->toEqual(ArticleExtractor\Metadata::empty());
});
