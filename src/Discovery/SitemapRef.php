<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

use DateTimeImmutable;

/** sitemap filho listado num índice de sitemaps */
final readonly class SitemapRef
{
    public function __construct(
        public string $url,
        public ?DateTimeImmutable $lastModified,
    ) {}
}
