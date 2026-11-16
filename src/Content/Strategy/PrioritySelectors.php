<?php

declare(strict_types=1);

namespace ArticleExtractor\Content\Strategy;

use ArticleExtractor\Content\BlockExtractor;
use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Page;

/**
 * Classes e atributos que CMSs comuns (WordPress, temas de portal, schema.org) usam pro
 * corpo da matéria. Nenhum é de um site só.
 */
final class PrioritySelectors implements ContentStrategy
{
    private const string SELECTORS = '[itemprop~="articleBody"], .article-body, .article__body, .articleBody, .article-content, '
        . '.article__content, .entry-content, .post-content, .post-body, .story-body, .story-content, .news-body, '
        . '.content-body, .materia-texto, .texto-materia, .noticia-texto';

    public function name(): string
    {
        return 'priority-selector';
    }

    public function extract(Page $page): array
    {
        $best = [];
        $bestChars = 0;
        foreach ($page->document->querySelectorAll(self::SELECTORS) as $container) {
            $blocks = BlockExtractor::fromElement($container);
            $chars = Quality::bodyChars($blocks);
            if ($chars > $bestChars) {
                [$best, $bestChars] = [$blocks, $chars];
            }
        }

        return Quality::accepts($best) ? $best : [];
    }
}
