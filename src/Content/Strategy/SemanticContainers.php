<?php

declare(strict_types=1);

namespace ArticleExtractor\Content\Strategy;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\BlockExtractor;
use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Page;

final class SemanticContainers implements ContentStrategy
{
    public function name(): string
    {
        return 'semantic-container';
    }

    public function extract(Page $page): array
    {
        $best = [];
        $bestScore = -INF;
        foreach ($page->document->querySelectorAll('article, [role="main"], main') as $container) {
            $blocks = BlockExtractor::fromElement($container);
            if (!Quality::accepts($blocks)) {
                continue;
            }
            $score = $this->score($blocks);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$blocks, $score];
            }
        }

        return $best;
    }

    /**
     * Texto de corpo, com desconto pra lista de chamadas (muitos parágrafos curtos) e pra
     * página de índice (mais título do que parágrafo).
     *
     * @param list<Block> $blocks
     */
    private function score(array $blocks): float
    {
        $chars = Quality::bodyChars($blocks);
        $body = count(array_filter($blocks, static fn(Block $b): bool => $b->type->isBody()));
        $headings = count(array_filter($blocks, static fn(Block $b): bool => $b->type === BlockType::Heading));

        $teaserList = $body > 3 && $chars / $body < 80;
        $indexPage = $headings > 3 && $headings >= $body;

        return $chars * ($teaserList ? 0.3 : 1.0) * ($indexPage ? 0.2 : 1.0);
    }
}
