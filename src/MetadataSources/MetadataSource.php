<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use DateTimeZone;

interface MetadataSource
{
    /**
     * Lê o que esta fonte sabe; o que ela não sabe fica nulo pra próxima fonte preencher.
     */
    public function read(Page $page, DateTimeZone $assume): Metadata;
}
