<?php

declare(strict_types=1);

use ArticleExtractor\Page;

function pageWithHead(string $head, string $body = '<p>corpo</p>', string $url = 'https://a.com/x/y'): Page
{
    return Page::parse("<!doctype html><html><head>{$head}</head><body>{$body}</body></html>", $url);
}

function ldJson(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data) . '</script>';
}

it('acha o artigo dentro de @graph', function () {
    $page = pageWithHead(ldJson(['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Organization', 'name' => 'Jornal'],
        ['@type' => 'NewsArticle', 'headline' => 'Manchete'],
    ]]));

    expect($page->embedded->article['headline'] ?? null)->toBe('Manchete')
        ->and($page->embedded->jsonLd)->toHaveCount(2);
});

it('reconhece @type em lista e sem diferenciar caixa', function (mixed $type) {
    $page = pageWithHead(ldJson(['@type' => $type, 'headline' => 'X']));

    expect($page->embedded->article)->not->toBeNull();
})->with([[['Article', 'Thing']], ['newsarticle'], ['LiveBlogPosting'], ['ReportageNewsArticle']]);

it('não trata organização como artigo', function () {
    $page = pageWithHead(ldJson(['@type' => 'Organization', 'name' => 'Jornal']));

    expect($page->embedded->article)->toBeNull()
        ->and($page->embedded->jsonLd)->toHaveCount(1);
});

it('não aceita tipo que só contém a palavra Article', function () {
    $page = pageWithHead(ldJson(['@type' => 'ArticleCollection', 'headline' => 'X']));

    expect($page->embedded->article)->toBeNull();
});

it('ignora JSON-LD inválido e lê o seguinte', function () {
    $page = pageWithHead('<script type="application/ld+json">{quebrado</script>' . ldJson(['@type' => 'BlogPosting', 'headline' => 'Ok']));

    expect($page->embedded->article['headline'] ?? null)->toBe('Ok');
});

it('achata lista de topo e ignora item que não é objeto', function () {
    $page = pageWithHead(ldJson([['@type' => 'WebPage'], 'texto', ['@type' => 'Article', 'headline' => 'Y']]));

    expect($page->embedded->jsonLd)->toHaveCount(2)
        ->and($page->embedded->article['headline'] ?? null)->toBe('Y');
});

it('lê pageProps do __NEXT_DATA__', function () {
    $page = pageWithHead('', '<script id="__NEXT_DATA__" type="application/json">' . json_encode(['props' => ['pageProps' => ['post' => ['title' => 'T']]]]) . '</script>');

    expect($page->embedded->nextPageProps)->toBe(['post' => ['title' => 'T']]);
});

it('fica sem pageProps quando o __NEXT_DATA__ não tem', function () {
    $page = pageWithHead('', '<script id="__NEXT_DATA__" type="application/json">{"props":{}}</script>');

    expect($page->embedded->nextPageProps)->toBeNull();
});

it('lê o Fusion.globalContent', function () {
    $page = pageWithHead('<script>var x = 1;</script><script>Fusion.globalContent={"headlines":{"basic":"Título"}};Fusion.x=2;</script>');

    expect($page->embedded->fusion['headlines'] ?? null)->toBe(['basic' => 'Título']);
});

it('usa o <base href> como base das URLs relativas', function () {
    expect(pageWithHead('<base href="/edicao/">')->baseUrl)->toBe('https://a.com/edicao/')
        ->and(pageWithHead('')->baseUrl)->toBe('https://a.com/x/y');
});

it('faz parse de HTML vazio sem lançar', function () {
    $page = Page::parse('', 'https://a.com/');

    expect($page->document->body)->not->toBeNull()
        ->and($page->embedded->jsonLd)->toBe([])
        ->and($page->embedded->article)->toBeNull()
        ->and($page->embedded->nextPageProps)->toBeNull()
        ->and($page->embedded->fusion)->toBeNull();
});

it('não remove os scripts do documento ao ler os payloads', function () {
    $page = pageWithHead(ldJson(['@type' => 'Article', 'headline' => 'Z']));

    expect($page->document->querySelector('script[type="application/ld+json"]'))->not->toBeNull();
});

it('respeita a codificação informada', function () {
    $page = Page::parse("<p>a\xE7\xE3o</p>", 'https://a.com/', 'windows-1252');

    expect($page->document->querySelector('p')?->textContent)->toBe('ação');
});
