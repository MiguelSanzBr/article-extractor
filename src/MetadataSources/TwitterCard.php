<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use DateTimeZone;

final class TwitterCard implements MetadataSource
{
    public function read(Page $page, DateTimeZone $assume): Metadata
    {
        return new Metadata(
            title: Fields::meta($page, 'twitter:title'),
            subtitle: Fields::meta($page, 'twitter:description'),
            image: Fields::url(Fields::meta($page, 'twitter:image'), $page),
        );
    }
}
