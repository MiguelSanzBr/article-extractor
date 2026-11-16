<?php

declare(strict_types=1);

use ArticleExtractor\MetadataSources\EmbeddedApp;

function embeddedMeta(string $body): ArticleExtractor\Metadata
{
    return (new EmbeddedApp())->read(page('', $body), utc());
}

function nextData(array $pageProps): string
{
    return '<script id="__NEXT_DATA__" type="application/json">' . json_encode(['props' => ['pageProps' => $pageProps]]) . '</script>';
}

it('lê o Fusion.globalContent do Arc XP', function () {
    $fusion = [
        'headlines' => ['basic' => 'Manchete Arc'],
        'subheadlines' => ['basic' => 'Linha fina'],
        'credits' => ['by' => [['name' => 'Ana Lima'], ['name' => 'Bruno Reis'], ['type' => 'author']]],
        'display_date' => '2026-03-05T10:00:00Z',
        'last_updated_date' => '2026-03-05T11:00:00Z',
        'promo_items' => ['basic' => ['url' => '/foto.jpg']],
        'taxonomy' => ['primary_section' => ['name' => 'Política'], 'tags' => [['text' => 'Congresso'], ['slug' => 'sem-text']]],
        'canonical_url' => '/politica/2026/03/05/manchete-arc',
    ];
    $meta = embeddedMeta('<script>Fusion.globalContent=' . json_encode($fusion) . ';</script>');

    expect($meta->title)->toBe('Manchete Arc')
        ->and($meta->subtitle)->toBe('Linha fina')
        ->and($meta->authors)->toBe(['Ana Lima', 'Bruno Reis'])
        ->and($meta->publishedAt?->format('c'))->toBe('2026-03-05T10:00:00+00:00')
        ->and($meta->modifiedAt?->format('c'))->toBe('2026-03-05T11:00:00+00:00')
        ->and($meta->image)->toBe('https://a.com/foto.jpg')
        ->and($meta->section)->toBe('Política')
        ->and($meta->tags)->toBe(['Congresso'])
        ->and($meta->canonical)->toBe('https://a.com/politica/2026/03/05/manchete-arc')
        ->and($meta->siteName)->toBeNull();
});

it('prefere description.basic e first_publish_date no Fusion', function () {
    $meta = embeddedMeta('<script>Fusion.globalContent=' . json_encode(['description' => ['basic' => 'Desc'], 'subheadlines' => ['basic' => 'Sub'], 'first_publish_date' => '2026-01-01T00:00:00Z', 'display_date' => '2026-02-01T00:00:00Z']) . ';</script>');

    expect($meta->subtitle)->toBe('Desc')
        ->and($meta->publishedAt?->format('Y-m-d'))->toBe('2026-01-01');
});

it('lê o objeto do artigo no __NEXT_DATA__', function () {
    $meta = embeddedMeta(nextData(['post' => [
        'title' => 'Manchete Next',
        'excerpt' => 'Resumo',
        'authors' => [['name' => 'Ana Lima']],
        'publishedAt' => '2026-03-05T10:00:00Z',
        'updatedAt' => '2026-03-06T10:00:00Z',
        'featured_image' => ['src' => '/img.jpg'],
    ]]));

    expect($meta->title)->toBe('Manchete Next')
        ->and($meta->subtitle)->toBe('Resumo')
        ->and($meta->authors)->toBe(['Ana Lima'])
        ->and($meta->publishedAt?->format('Y-m-d'))->toBe('2026-03-05')
        ->and($meta->modifiedAt?->format('Y-m-d'))->toBe('2026-03-06')
        ->and($meta->image)->toBe('https://a.com/img.jpg');
});

it('usa o próprio pageProps quando não há objeto de artigo', function () {
    expect(embeddedMeta(nextData(['headline' => 'Direto', 'date' => '2026-03-05']))->title)->toBe('Direto');
});

it('devolve vazio sem payload embutido', function () {
    expect(embeddedMeta('<p>nada</p>')->title)->toBeNull();
});
