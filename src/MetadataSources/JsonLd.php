<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use DateTimeZone;

final class JsonLd implements MetadataSource
{
    public function read(Page $page, DateTimeZone $assume): Metadata
    {
        $article = $page->embedded->article;
        if ($article === null) {
            return Metadata::empty();
        }

        $sections = Fields::terms($article['articleSection'] ?? null);

        return new Metadata(
            title: Fields::text($article['headline'] ?? null),
            subtitle: Fields::text($article['description'] ?? null),
            authors: Fields::people($article['author'] ?? null),
            publishedAt: Fields::date($article['datePublished'] ?? null, $assume),
            modifiedAt: Fields::date($article['dateModified'] ?? null, $assume),
            image: Fields::image($article['image'] ?? null, $page),
            siteName: Fields::text(Fields::at($article, 'publisher', 'name')),
            section: $sections === [] ? null : implode(', ', $sections),
            tags: Fields::terms($article['keywords'] ?? null),
            language: Fields::language(Fields::text($article['inLanguage'] ?? null)),
            canonical: Fields::url(Fields::at($article, 'mainEntityOfPage', '@id') ?? $article['mainEntityOfPage'] ?? null, $page)
                ?? Fields::url($article['url'] ?? null, $page),
        );
    }
}
