<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use DateTimeZone;

/**
 * Metadados do estado que apps em JS embutem na página: Arc XP (Fusion.globalContent)
 * e Next.js (__NEXT_DATA__). Nome do site não sai daqui: o Fusion só tem o id do CMS.
 */
final class EmbeddedApp implements MetadataSource
{
    private const array NEXT_ARTICLE_KEYS = ['article', 'post', 'content', 'data'];

    public function read(Page $page, DateTimeZone $assume): Metadata
    {
        if ($page->embedded->fusion !== null) {
            return $this->fusion($page->embedded->fusion, $page, $assume);
        }
        if ($page->embedded->nextPageProps !== null) {
            return $this->next($page->embedded->nextPageProps, $page, $assume);
        }

        return Metadata::empty();
    }

    /**
     * @param array<mixed> $content
     */
    private function fusion(array $content, Page $page, DateTimeZone $assume): Metadata
    {
        $tags = Fields::at($content, 'taxonomy', 'tags');

        return new Metadata(
            title: Fields::text(Fields::at($content, 'headlines', 'basic')),
            subtitle: Fields::text(Fields::at($content, 'description', 'basic')) ?? Fields::text(Fields::at($content, 'subheadlines', 'basic')),
            authors: Fields::people(Fields::at($content, 'credits', 'by')),
            publishedAt: Fields::date($content['first_publish_date'] ?? null, $assume) ?? Fields::date($content['display_date'] ?? null, $assume),
            modifiedAt: Fields::date($content['last_updated_date'] ?? null, $assume),
            image: Fields::url(Fields::at($content, 'promo_items', 'basic', 'url'), $page),
            section: Fields::text(Fields::at($content, 'taxonomy', 'primary_section', 'name')),
            tags: Fields::terms(array_map(static fn(mixed $tag): mixed => Fields::at($tag, 'text'), is_array($tags) ? $tags : [])),
            canonical: Fields::url($content['canonical_url'] ?? null, $page),
        );
    }

    /**
     * @param array<mixed> $pageProps
     */
    private function next(array $pageProps, Page $page, DateTimeZone $assume): Metadata
    {
        $article = $pageProps;
        foreach (self::NEXT_ARTICLE_KEYS as $key) {
            if (is_array($pageProps[$key] ?? null)) {
                $article = $pageProps[$key];
                break;
            }
        }

        return new Metadata(
            title: Fields::text($article['title'] ?? null) ?? Fields::text($article['headline'] ?? null),
            subtitle: Fields::text($article['description'] ?? null) ?? Fields::text($article['excerpt'] ?? null) ?? Fields::text($article['summary'] ?? null),
            authors: Fields::people($article['author'] ?? $article['authors'] ?? null),
            publishedAt: Fields::date($article['publishedAt'] ?? null, $assume)
                ?? Fields::date($article['published_at'] ?? null, $assume)
                ?? Fields::date($article['date'] ?? null, $assume),
            modifiedAt: Fields::date($article['updatedAt'] ?? null, $assume) ?? Fields::date($article['modified_at'] ?? null, $assume),
            image: Fields::image($article['image'] ?? $article['featured_image'] ?? $article['cover'] ?? null, $page),
        );
    }
}
