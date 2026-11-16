<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

use ArticleExtractor\Support\Url;

/**
 * Onde feed e sitemap costumam morar quando a home não anuncia nenhum.
 */
final class FeedLocations
{
    private const array PATHS = [
        ['/feed', FeedType::Rss],
        ['/rss', FeedType::Rss],
        ['/feed.xml', FeedType::Rss],
        ['/rss.xml', FeedType::Rss],
        ['/atom.xml', FeedType::Atom],
        ['/index.xml', FeedType::Rss],
        ['/feeds/posts/default', FeedType::Atom],
        ['/sitemap-news.xml', FeedType::Sitemap],
        ['/news-sitemap.xml', FeedType::Sitemap],
        ['/sitemap_index.xml', FeedType::Sitemap],
        ['/sitemap.xml', FeedType::Sitemap],
    ];

    /**
     * @return list<FeedLink>
     *
     * @throws \InvalidArgumentException quando a home não é http(s)
     */
    public static function candidates(string $homepage): array
    {
        $origin = (string) preg_replace('~^(https?://[^/?#]+).*$~i', '$1', Url::normalize($homepage));

        return array_map(static fn(array $path): FeedLink => new FeedLink($origin . $path[0], $path[1]), self::PATHS);
    }
}
