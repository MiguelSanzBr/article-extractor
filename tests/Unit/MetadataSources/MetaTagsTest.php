<?php

declare(strict_types=1);

use ArticleExtractor\MetadataSources\MetaTags;

function metaTags(string $head, string $htmlAttrs = ''): ArticleExtractor\Metadata
{
    return (new MetaTags())->read(page($head, '', 'https://a.com/n/1', $htmlAttrs), utc());
}

it('lê Open Graph e article:*', function () {
    $meta = metaTags(
        '<meta property="og:title" content="Juros sobem">'
        . '<meta property="og:description" content="Banco central &amp; mercado">'
        . '<meta property="og:image" content="/fotos/a.jpg">'
        . '<meta property="og:site_name" content="Jornal Exemplo">'
        . '<meta property="article:published_time" content="2026-03-05T10:00:00-03:00">'
        . '<meta property="article:modified_time" content="2026-03-05T12:00:00-03:00">'
        . '<meta property="article:section" content="Economia">'
        . '<link rel="canonical" href="/n/1">',
    );

    expect($meta->title)->toBe('Juros sobem')
        ->and($meta->subtitle)->toBe('Banco central & mercado')
        ->and($meta->image)->toBe('https://a.com/fotos/a.jpg')
        ->and($meta->siteName)->toBe('Jornal Exemplo')
        ->and($meta->publishedAt?->format('c'))->toBe('2026-03-05T13:00:00+00:00')
        ->and($meta->modifiedAt?->format('c'))->toBe('2026-03-05T15:00:00+00:00')
        ->and($meta->section)->toBe('Economia')
        ->and($meta->canonical)->toBe('https://a.com/n/1');
});

it('aceita a meta por name quando o site usa name no lugar de property', function () {
    expect(metaTags('<meta name="og:title" content="Por name">')->title)->toBe('Por name');
});

it('usa a description comum, o application-name e o og:url como reserva', function () {
    $meta = metaTags('<meta name="description" content="Resumo"><meta name="application-name" content="App"><meta property="og:url" content="https://a.com/n/1?x=1">');

    expect($meta->subtitle)->toBe('Resumo')
        ->and($meta->siteName)->toBe('App')
        ->and($meta->canonical)->toBe('https://a.com/n/1?x=1');
});

it('descarta autor que é URL de perfil e fica com o nome', function () {
    $meta = metaTags('<meta property="article:author" content="https://site.com/autor/ana"><meta name="author" content="Ana Lima">');

    expect($meta->authors)->toBe(['Ana Lima']);
});

it('junta article:tag, keywords e news_keywords sem repetir', function () {
    $meta = metaTags('<meta property="article:tag" content="Juros"><meta property="article:tag" content="Copom"><meta name="keywords" content="juros, inflação"><meta name="news_keywords" content="Copom,  Selic ">');

    expect($meta->tags)->toBe(['Juros', 'Copom', 'inflação', 'Selic']);
});

it('normaliza o idioma do og:locale ou do html lang', function () {
    expect(metaTags('<meta property="og:locale" content="pt_BR">')->language)->toBe('pt-BR')
        ->and(metaTags('', 'lang="pt-br"')->language)->toBe('pt-BR')
        ->and(metaTags('', 'lang="EN"')->language)->toBe('en')
        ->and(metaTags('', 'lang="x"')->language)->toBeNull()
        ->and(metaTags('')->language)->toBeNull();
});

it('ignora meta vazia', function () {
    expect(metaTags('<meta property="og:title" content="   ">')->title)->toBeNull();
});
