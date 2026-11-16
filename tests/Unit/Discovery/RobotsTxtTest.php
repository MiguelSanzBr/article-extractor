<?php

declare(strict_types=1);

use ArticleExtractor\Discovery\RobotsTxt;

const UA = 'article-extractor/0.1 (+https://github.com/MiguelSanzBr/news-extractor-api)';

it('usa o grupo do próprio robô em vez do *', function () {
    $rules = RobotsTxt::parse("User-agent: *\nDisallow: /\n\n# nosso robô\nUser-agent: Article-Extractor\nDisallow: /privado/ # área fechada\nCrawl-delay: 5\n", UA);

    expect($rules->isAllowed('https://a.com/noticia/x'))->toBeTrue()
        ->and($rules->isAllowed('https://a.com/privado/x'))->toBeFalse()
        ->and($rules->crawlDelay)->toBe(5.0);
});

it('cai no grupo * quando não há grupo do robô', function () {
    $rules = RobotsTxt::parse("User-agent: outro\nDisallow: /\n\nUser-agent: *\nDisallow: /busca\nCrawl-delay: 2.5", UA);

    expect($rules->isAllowed('https://a.com/busca?q=x'))->toBeFalse()
        ->and($rules->isAllowed('https://a.com/noticia'))->toBeTrue()
        ->and($rules->crawlDelay)->toBe(2.5);
});

it('junta vários user-agent no mesmo grupo', function () {
    $rules = RobotsTxt::parse("User-agent: outro\nUser-agent: article-extractor\nDisallow: /x\n", UA);

    expect($rules->isAllowed('https://a.com/x/1'))->toBeFalse();
});

it('regra mais longa vence e empate fica com Allow', function () {
    $rules = RobotsTxt::parse("User-agent: *\nDisallow: /noticias/\nAllow: /noticias/$\nDisallow: /*.pdf$\nAllow: /a\nDisallow: /a", UA);

    expect($rules->isAllowed('https://a.com/noticias/'))->toBeTrue()
        ->and($rules->isAllowed('https://a.com/noticias/x'))->toBeFalse()
        ->and($rules->isAllowed('https://a.com/docs/r.pdf'))->toBeFalse()
        ->and($rules->isAllowed('https://a.com/docs/r.pdf?v=2'))->toBeTrue()
        ->and($rules->isAllowed('https://a.com/a'))->toBeTrue()
        ->and($rules->isAllowed('https://a.com'))->toBeTrue();
});

it('Disallow vazio libera tudo e robots vazio também', function () {
    expect(RobotsTxt::parse("User-agent: *\nDisallow:\n", UA)->isAllowed('https://a.com/x'))->toBeTrue()
        ->and(RobotsTxt::parse('', UA)->isAllowed('https://a.com/x'))->toBeTrue()
        ->and(RobotsTxt::parse('', UA)->crawlDelay)->toBeNull();
});

it('lista todos os sitemaps válidos', function () {
    $rules = RobotsTxt::parse("sitemap: https://a.com/sitemap.xml\nSitemap: nada\nSITEMAP: https://a.com/news.xml\nCrawl-delay: x", UA);

    expect($rules->sitemaps)->toBe(['https://a.com/sitemap.xml', 'https://a.com/news.xml'])
        ->and($rules->crawlDelay)->toBeNull();
});
