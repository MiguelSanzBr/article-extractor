<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

use DateTimeImmutable;

final readonly class FeedItem
{
    public function __construct(
        public string $url,
        public ?string $title,
        public ?DateTimeImmutable $publishedAt,
    ) {}
}
