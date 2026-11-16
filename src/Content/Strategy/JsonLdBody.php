<?php

declare(strict_types=1);

namespace ArticleExtractor\Content\Strategy;

use ArticleExtractor\Content\BlockExtractor;
use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Page;

final class JsonLdBody implements ContentStrategy
{
    public function name(): string
    {
        return 'json-ld';
    }

    public function extract(Page $page): array
    {
        $body = $page->embedded->article['articleBody'] ?? null;
        foreach (is_string($body) ? [] : $page->embedded->jsonLd as $item) {
            if (is_string($item['articleBody'] ?? null)) {
                $body = $item['articleBody'];
                break;
            }
        }
        $blocks = is_string($body) ? BlockExtractor::fromText($body) : [];

        return Quality::accepts($blocks) ? $blocks : [];
    }
}
