<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\DensityHeuristic;
use ArticleExtractor\Content\Strategy\EmbeddedAppBody;
use ArticleExtractor\Content\Strategy\JsonLdBody;
use ArticleExtractor\Content\Strategy\ParagraphFallback;
use ArticleExtractor\Content\Strategy\PrioritySelectors;
use ArticleExtractor\Content\Strategy\SemanticContainers;
use ArticleExtractor\Page;

function allStrategies(): array
{
    return [new PrioritySelectors(), new SemanticContainers(), new JsonLdBody(), new EmbeddedAppBody(), new DensityHeuristic(), new ParagraphFallback()];
}

it('nenhuma estratégia altera o documento da página', function () {
    $page = page(ld(['@type' => 'NewsArticle', 'articleBody' => 'x']), '<nav>menu</nav><article><aside>lado</aside><div class="entry-content">' . paragraphs(4) . '<div class="share">x</div></div></article><script>Fusion.globalContent={"a":1};</script>');
    $before = $page->document->saveHtml();

    foreach (allStrategies() as $strategy) {
        $strategy->extract($page);
    }

    expect($page->document->saveHtml())->toBe($before);
});

it('roda as seis estratégias numa página de 3 MB em menos de 2 s', function () {
    $sentence = str_repeat('Texto longo de parágrafo para encher a página de teste de desempenho. ', 6);
    $html = '';
    for ($i = 0; $i < 2000; $i++) {
        $html .= str_repeat('<div>', 8) . "<p>{$i} a {$sentence}</p><p>{$i} b {$sentence}</p><p>{$i} c {$sentence}</p>" . str_repeat('</div>', 8);
    }
    expect(strlen($html))->toBeGreaterThan(2_500_000);
    $page = Page::parse("<!doctype html><html><body>{$html}</body></html>", 'https://a.com/');

    $start = microtime(true);
    foreach (allStrategies() as $strategy) {
        $strategy->extract($page);
    }

    expect(microtime(true) - $start)->toBeLessThan(2.0);
})->group('slow');
