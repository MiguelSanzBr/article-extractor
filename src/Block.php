<?php

declare(strict_types=1);

namespace ArticleExtractor;

final readonly class Block
{
    public function __construct(
        public BlockType $type,
        public string $text,
    ) {}
}
