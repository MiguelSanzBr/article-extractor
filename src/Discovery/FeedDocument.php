<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

final readonly class FeedDocument
{
    /**
     * @param list<FeedItem> $items do mais novo pro mais velho; sem data no fim
     * @param list<SitemapRef> $sitemaps só num índice de sitemaps; o mais recente primeiro
     */
    public function __construct(
        public array $items,
        public array $sitemaps,
    ) {}
}
