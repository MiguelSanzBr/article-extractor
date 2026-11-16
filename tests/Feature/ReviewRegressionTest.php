<?php

declare(strict_types=1);

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\PostProcessor;
use ArticleExtractor\Discovery\FeedParser;
use ArticleExtractor\Discovery\RobotsTxt;
use ArticleExtractor\Extractor;
use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use ArticleExtractor\Support\Json;
use ArticleExtractor\Support\Url;

function timed(callable $run): float
{
    $start = microtime(true);
    $run();

    return microtime(true) - $start;
}

it('página plana com 40 mil parágrafos sai em menos de 2 s', function () {
    $body = implode('', array_map(static fn(int $i): string => "<p>Parágrafo número {$i} do texto.</p>", range(1, 40_000)));

    expect(timed(fn() => Extractor::create()->extract("<html><body>{$body}</body></html>", 'https://a.test/x')))->toBeLessThan(2.0);
})->group('slow');

it('linha fina de 10 mil caracteres não trava a comparação com o corpo', function () {
    $description = str_repeat('Texto longo da linha fina repetido muitas vezes. ', 205);
    $body = implode('', array_map(static fn(int $i): string => '<p>' . str_repeat("Parágrafo {$i} longo do corpo. ", 330) . '</p>', range(1, 5)));
    $html = "<html><head><meta property=\"og:description\" content=\"{$description}\"></head><body><div class=\"entry-content\">{$body}</div></body></html>";

    expect(timed(fn() => Extractor::create()->extract($html, 'https://a.test/x')))->toBeLessThan(2.0);
})->group('slow');

it('corpo de um parágrafo longo igual à descrição continua sendo corpo', function () {
    $text = str_repeat('Uma frase do corpo que também virou a descrição da página. ', 30);
    $html = '<html><head>' . ld(['@type' => 'NewsArticle', 'headline' => 'T', 'description' => $text, 'articleBody' => $text]) . '</head><body></body></html>';

    expect(Extractor::create()->extract($html, 'https://a.test/x')->strategy)->toBe('json-ld');
});

it('aninhamento profundo sem fechamento sai em menos de 2 s', function (bool $withLinks) {
    $link = $withLinks ? ' <a href="/x">um link</a>' : '';
    $chain = implode('', array_map(static fn(int $i): string => "<div><p>Trecho {$i} da matéria com texto suficiente{$link}.</p>", range(1, 8000)));

    expect(timed(fn() => Extractor::create()->extract("<html><body>{$chain}</body></html>", 'https://a.test/x')))->toBeLessThan(2.0);
})->with([false, true])->group('slow');

it('sequência longa de chamadas não deixa o pós-processamento quadrático', function () {
    $blocks = [new Block(BlockType::Paragraph, str_repeat('p', 150))];
    for ($i = 0; $i < 20_000; $i++) {
        $blocks[] = new Block(BlockType::Heading, "Chamada {$i}");
        $blocks[] = new Block(BlockType::Paragraph, "Teaser curto {$i}.");
    }

    expect(timed(fn() => PostProcessor::process($blocks, Metadata::empty())))->toBeLessThan(2.0);
})->group('slow');

it('lê JSON-LD com quebra de linha crua dentro de string', function () {
    $json = '{"@type":"NewsArticle","headline":"Manchete","author":{"name":"Ana Lima"},"datePublished":"2026-03-05T10:00:00Z",'
        . '"articleBody":"' . str_repeat('Primeiro parágrafo do corpo com bastante texto aqui. ', 4) . "\n\n" . str_repeat('Segundo parágrafo do corpo com bastante texto. ', 4) . '"}';
    $article = Extractor::create()->extract("<html><head><script type=\"application/ld+json\">{$json}</script></head><body></body></html>", 'https://a.test/x');

    expect($article->metadata->title)->toBe('Manchete')
        ->and($article->metadata->authors)->toBe(['Ana Lima'])
        ->and($article->metadata->publishedAt?->format('c'))->toBe('2026-03-05T10:00:00+00:00')
        ->and($article->strategy)->toBe('json-ld')
        ->and($article->blocks)->toHaveCount(2);
});

it('lê JSON-LD embrulhado em CDATA ou comentário e @graph como objeto', function (string $script) {
    expect(Page::parse("<html><head><script type=\"application/ld+json\">{$script}</script></head></html>", 'https://a.test/')->embedded->article['headline'] ?? null)->toBe('Ok');
})->with([
    '/*<![CDATA[*/{"@type":"NewsArticle","headline":"Ok"}/*]]>*/',
    '<!-- {"@type":"NewsArticle","headline":"Ok"} -->',
    '{"@context":"https://schema.org","@graph":{"@type":"NewsArticle","headline":"Ok"}}',
]);

it('robots.txt com BOM não libera tudo', function () {
    expect(RobotsTxt::parse("\u{FEFF}User-agent: *\nDisallow: /\n", 'article-extractor/0.1')->isAllowed('https://a.com/x'))->toBeFalse();
});

it('robots.txt casa o nome do produto inteiro, com ou sem versão', function () {
    $ua = 'article-extractor/0.1 (+https://x)';

    expect(RobotsTxt::parse("User-agent: *\nDisallow: /\n\nUser-agent: a\nDisallow:", $ua)->isAllowed('https://a.com/y'))->toBeFalse()
        ->and(RobotsTxt::parse("User-agent: *\nDisallow: /\n\nUser-agent: Article-Extractor/1.0\nDisallow:", $ua)->isAllowed('https://a.com/y'))->toBeTrue()
        ->and(RobotsTxt::parse("User-agent: *\nCrawl-delay: 0", $ua)->crawlDelay)->toBe(0.0);
});

it('item RSS com atom:link antes do link continua valendo', function () {
    $xml = '<rss xmlns:atom="http://www.w3.org/2005/Atom"><channel><item><atom:link rel="self" href="https://a.example/api"/><link>https://a.example/noticia/x</link></item></channel></rss>';

    expect(FeedParser::parse($xml, 'https://a.example/feed')->items[0]->url ?? null)->toBe('https://a.example/noticia/x');
});

it('feed com & solto, entidade HTML e byte inválido ainda é lido', function () {
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><rss><channel><item><title>Copa&nbsp;do mundo & mais \xC3</title><link>https://a.example/n?x=1&y=2</link></item></channel></rss>";
    $item = FeedParser::parse($xml, 'https://a.example/feed')->items[0];

    expect($item->url)->toBe('https://a.example/n?x=1&y=2')
        ->and($item->title)->toStartWith('Copa do mundo & mais');
});

it('Fusion.globalContentConfig não é confundido com Fusion.globalContent', function () {
    expect(Json::objectAssignedTo('Fusion.globalContentConfig={"a":1};Fusion.globalContent = {"b":2};', 'Fusion.globalContent'))->toBe(['b' => 2]);
});

it('só aceita URL http(s) como resultado', function (string $reference) {
    expect(Url::resolve('https://a.com/x', $reference))->toBeNull();
})->with(['file:///etc/passwd', 'vbscript:msgbox(1)', 'ftp://a.com/f.jpg']);

it('conserta só o necessário: entidades XML ficam, entidade desconhecida vira texto, latin1 declarado não é tocado', function () {
    $xml = '<rss><channel><item><title>A &amp; B&nbsp;C &naoexiste; D & E</title><link>https://a.example/n</link></item></channel></rss>';
    $latin1 = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?><rss><channel><item><title>A\xE7\xE3o & cia</title><link>https://a.example/n</link></item></channel></rss>";

    expect(FeedParser::parse($xml, 'https://a.example/feed')->items[0]->title)->toBe('A & B C &naoexiste; D & E')
        ->and(FeedParser::parse($latin1, 'https://a.example/feed')->items[0]->title)->toBe('Ação & cia');
});

it('conserta JSON-LD com aspas escapadas e quebra de linha na mesma string', function () {
    expect(Json::decode("{\"a\":\"diz \\\"oi\\\"\nfim\"}"))->toBe(['a' => "diz \"oi\"\nfim"]);
});
