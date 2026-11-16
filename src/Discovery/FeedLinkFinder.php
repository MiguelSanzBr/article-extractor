<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

use ArticleExtractor\Page;
use ArticleExtractor\Support\Url;

final class FeedLinkFinder
{
    private const string LINKS = 'link[rel~="alternate"][type="application/rss+xml"], link[rel~="alternate"][type="application/atom+xml"]';

    /**
     * Todos os feeds que a home anuncia, na ordem, menos o de comentários.
     *
     * @return list<FeedLink>
     */
    public static function find(string $html, string $pageUrl): array
    {
        $page = Page::parse($html, $pageUrl);
        $feeds = [];
        foreach ($page->document->querySelectorAll(self::LINKS) as $link) {
            $href = $link->getAttribute('href') ?? '';
            $url = Url::resolve($page->baseUrl, $href);
            if ($url === null || isset($feeds[$url]) || preg_match('/comment|coment/iu', $href . ' ' . $link->getAttribute('title')) === 1) {
                continue;
            }
            $feeds[$url] = new FeedLink($url, str_contains((string) $link->getAttribute('type'), 'atom') ? FeedType::Atom : FeedType::Rss);
        }

        return array_values($feeds);
    }
}
