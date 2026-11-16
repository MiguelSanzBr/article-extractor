<?php

declare(strict_types=1);

use ArticleExtractor\MetadataSources\JsonLd;

function jsonLdMeta(array $article): ArticleExtractor\Metadata
{
    return (new JsonLd())->read(page(ld(['@type' => 'NewsArticle'] + $article)), utc());
}

it('lê os campos do artigo', function () {
    $meta = jsonLdMeta([
        'headline' => 'Manchete',
        'description' => 'Ok &amp; certo',
        'datePublished' => '2026-03-05T10:00:00Z',
        'dateModified' => '2026-03-06T10:00:00Z',
        'publisher' => ['@type' => 'Organization', 'name' => 'Jornal'],
        'articleSection' => ['Economia', 'Mercados'],
        'keywords' => 'juros, selic',
        'inLanguage' => 'pt-BR',
        'mainEntityOfPage' => ['@id' => 'https://a.com/n/1'],
    ]);

    expect($meta->title)->toBe('Manchete')
        ->and($meta->subtitle)->toBe('Ok & certo')
        ->and($meta->publishedAt?->format('c'))->toBe('2026-03-05T10:00:00+00:00')
        ->and($meta->modifiedAt?->format('c'))->toBe('2026-03-06T10:00:00+00:00')
        ->and($meta->siteName)->toBe('Jornal')
        ->and($meta->section)->toBe('Economia, Mercados')
        ->and($meta->tags)->toBe(['juros', 'selic'])
        ->and($meta->language)->toBe('pt-BR')
        ->and($meta->canonical)->toBe('https://a.com/n/1');
});

it('guarda todos os autores', function (mixed $author, array $expected) {
    expect(jsonLdMeta(['author' => $author])->authors)->toBe($expected);
})->with([
    'lista de objetos' => [[['@type' => 'Person', 'name' => 'Ana Lima'], ['name' => 'Bruno Reis']], ['Ana Lima', 'Bruno Reis']],
    'objeto' => [['name' => 'Ana Lima'], ['Ana Lima']],
    'texto' => ['Ana Lima', ['Ana Lima']],
    'lista de textos repetidos' => [['Ana Lima', 'Ana Lima', 42], ['Ana Lima']],
]);

it('lê a imagem em qualquer formato', function (mixed $image) {
    expect(jsonLdMeta(['image' => $image])->image)->toBe('https://a.com/f.jpg');
})->with([
    'texto' => ['https://a.com/f.jpg'],
    'objeto' => [['@type' => 'ImageObject', 'url' => 'https://a.com/f.jpg']],
    'objeto com lista' => [['url' => ['https://a.com/f.jpg', 'https://a.com/g.jpg']]],
    'lista' => [['/f.jpg', '/g.jpg']],
    'lista de objetos' => [[['url' => '/f.jpg']]],
]);

it('usa mainEntityOfPage em texto ou url como canonical', function () {
    expect(jsonLdMeta(['mainEntityOfPage' => 'https://a.com/n/2'])->canonical)->toBe('https://a.com/n/2')
        ->and(jsonLdMeta(['url' => '/n/3'])->canonical)->toBe('https://a.com/n/3');
});

it('um campo de tipo estranho não derruba os outros', function () {
    $meta = jsonLdMeta(['headline' => ['x'], 'datePublished' => 20260305, 'description' => 'Ainda lido', 'image' => 7, 'publisher' => 'Jornal']);

    expect($meta->title)->toBeNull()
        ->and($meta->publishedAt)->toBeNull()
        ->and($meta->image)->toBeNull()
        ->and($meta->siteName)->toBeNull()
        ->and($meta->subtitle)->toBe('Ainda lido');
});

it('devolve vazio sem artigo JSON-LD', function () {
    expect((new JsonLd())->read(page(ld(['@type' => 'Organization', 'name' => 'X'])), utc())->title)->toBeNull();
});
