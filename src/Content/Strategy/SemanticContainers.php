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
        $bestScore = 0.0;
        foreach ($page->document->querySelectorAll('article, [role="main"], main') as $container) {
            $blocks = BlockExtractor::fromElement($container);
            $score = Quality::accepts($blocks) ? $this->score($blocks) : 0.0;
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

        $score = $chars * 1.0;
        if ($body > 3 && $chars / $body < 80) {
            $score *= 0.3;
        }
        if ($headings > 3 && $headings >= $body) {
            $score *= 0.2;
        }

        return $score;
    }
}
