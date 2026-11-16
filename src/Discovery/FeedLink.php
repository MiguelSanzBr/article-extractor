<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

final readonly class FeedLink
{
    public function __construct(
        public string $url,
        public FeedType $type,
    ) {}
}
