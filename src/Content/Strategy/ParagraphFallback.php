<?php

declare(strict_types=1);

namespace ArticleExtractor\Content\Strategy;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\BlockExtractor;
use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Page;

/**
 * Último recurso: todo <p> da página fora de ruído.
 */
final class ParagraphFallback implements ContentStrategy
{
    public function name(): string
    {
        return 'paragraph-fallback';
    }

    public function extract(Page $page): array
    {
        $blocks = array_values(array_filter(
            BlockExtractor::fromElement($page->body()),
            static fn(Block $block): bool => $block->type === BlockType::Paragraph,
        ));

        return Quality::accepts($blocks) ? $blocks : [];
    }
}
