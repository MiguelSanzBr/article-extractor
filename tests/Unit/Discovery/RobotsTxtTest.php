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

it('casa padrão com query', function () {
    $rules = RobotsTxt::parse("User-agent: *\nDisallow: /*?sessao=", UA);

    expect($rules->isAllowed('https://a.com/x?sessao=1'))->toBeFalse()
        ->and($rules->isAllowed('https://a.com/x?pagina=1'))->toBeTrue();
});

it('Allow mais curto não vence Disallow mais longo', function () {
    expect(RobotsTxt::parse("User-agent: *\nDisallow: /privado/x\nAllow: /privado", UA)->isAllowed('https://a.com/privado/x/1'))->toBeFalse();
});

it('acha o grupo com UA em caixa alta e espaço antes', function () {
    expect(RobotsTxt::parse("User-agent: article-extractor\nDisallow: /x", ' Article-Extractor/0.1')->isAllowed('https://a.com/x'))->toBeFalse();
});

it('regra vale pra todos os user-agent do grupo, inclusive o primeiro', function () {
    expect(RobotsTxt::parse("User-agent: article-extractor\nUser-agent: outro\nDisallow: /x", UA)->isAllowed('https://a.com/x'))->toBeFalse();
});

it('lê crawl-delay do primeiro grupo e ignora outra linha numérica', function () {
    expect(RobotsTxt::parse("User-agent: *\nCrawl-delay: 4", UA)->crawlDelay)->toBe(4.0)
        ->and(RobotsTxt::parse("User-agent: *\nCrawl-delay: 3\nRequest-rate: 10", UA)->crawlDelay)->toBe(3.0);
});

it('grupo de nome curto que casa vence o *', function () {
    expect(RobotsTxt::parse("User-agent: *\nDisallow: /\n\nUser-agent: a\nDisallow: /x", UA)->isAllowed('https://a.com/y'))->toBeTrue();
});

it('nome do grupo precisa casar com o começo do produto', function () {
    expect(RobotsTxt::parse("User-agent: extractor\nDisallow: /\n\nUser-agent: *\nDisallow:", UA)->isAllowed('https://a.com/x'))->toBeTrue();
});

it('junta grupos repetidos do mesmo nome', function () {
    $rules = RobotsTxt::parse("User-agent: *\nDisallow: /a\nCrawl-delay: 1\n\nUser-agent: *\nDisallow: /b\nCrawl-delay: 9", UA);

    expect($rules->isAllowed('https://a.com/a'))->toBeFalse()
        ->and($rules->isAllowed('https://a.com/b'))->toBeFalse()
        ->and($rules->isAllowed('https://a.com/c'))->toBeTrue()
        ->and($rules->crawlDelay)->toBe(1.0);
});

it('grupo do robô vence mesmo vindo antes do *', function () {
    $rules = RobotsTxt::parse("User-agent: article-extractor\nDisallow: /x\n\nUser-agent: *\nDisallow: /", UA);

    expect($rules->isAllowed('https://a.com/y'))->toBeTrue()
        ->and($rules->isAllowed('https://a.com/x'))->toBeFalse();
});

it('ignora regra antes de qualquer user-agent', function () {
    expect(RobotsTxt::parse("Disallow: /\nCrawl-delay: 7\nUser-agent: *\nDisallow: /x", UA))
        ->isAllowed('https://a.com/y')->toBeTrue()
        ->crawlDelay->toBeNull();
});

it('crawl-delay do grupo do robô vence o do *', function () {
    expect(RobotsTxt::parse("User-agent: *\nCrawl-delay: 9\n\nUser-agent: article-extractor\nCrawl-delay: 2", UA)->crawlDelay)->toBe(2.0);
});

it('crawl-delay: vale o primeiro do grupo, outra linha numérica não conta, e grupo sem delay não apaga o do outro', function () {
    expect(RobotsTxt::parse("User-agent: *\nCrawl-delay: 1\nCrawl-delay: 5", UA)->crawlDelay)->toBe(1.0)
        ->and(RobotsTxt::parse("User-agent: *\nRequest-rate: 10\nCrawl-delay: 3", UA)->crawlDelay)->toBe(3.0)
        ->and(RobotsTxt::parse("User-agent: *\nDisallow: /a\n\nUser-agent: *\nCrawl-delay: 4", UA)->crawlDelay)->toBe(4.0);
});

it('comentário no começo da linha apaga a linha toda', function () {
    expect(RobotsTxt::parse("User-agent: *\n# Disallow: /\nDisallow: /x # depois", UA))
        ->isAllowed('https://a.com/y')->toBeTrue()
        ->isAllowed('https://a.com/x')->toBeFalse();
});
