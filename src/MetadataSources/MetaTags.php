<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use DateTimeZone;

final class MetaTags implements MetadataSource
{
    public function read(Page $page, DateTimeZone $assume): Metadata
    {
        $canonical = $page->document->querySelector('link[rel~="canonical"]')?->getAttribute('href');

        return new Metadata(
            title: Fields::meta($page, 'og:title'),
            subtitle: Fields::meta($page, 'og:description') ?? Fields::meta($page, 'description'),
            authors: Fields::people([...Fields::metaAll($page, 'article:author'), ...Fields::metaAll($page, 'author')]),
            publishedAt: Fields::date(Fields::meta($page, 'article:published_time'), $assume),
            modifiedAt: Fields::date(Fields::meta($page, 'article:modified_time'), $assume),
            image: Fields::url(Fields::meta($page, 'og:image'), $page),
            siteName: Fields::meta($page, 'og:site_name') ?? Fields::meta($page, 'application-name'),
            section: Fields::meta($page, 'article:section'),
            tags: Fields::terms([...Fields::metaAll($page, 'article:tag'), ...Fields::metaAll($page, 'keywords'), ...Fields::metaAll($page, 'news_keywords')]),
            language: Fields::language(Fields::meta($page, 'og:locale') ?? $page->document->documentElement?->getAttribute('lang')),
            canonical: Fields::url($canonical, $page) ?? Fields::url(Fields::meta($page, 'og:url'), $page),
        );
    }
}
