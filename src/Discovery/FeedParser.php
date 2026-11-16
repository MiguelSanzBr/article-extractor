<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

use ArticleExtractor\Support\Dates;
use ArticleExtractor\Support\Text;
use ArticleExtractor\Support\Url;
use DateTimeImmutable;
use DateTimeZone;
use Dom\Element;
use Dom\XMLDocument;
use DOMException;
use ValueError;

/**
 * RSS 1.0 e 2.0, Atom, sitemap, sitemap de notícias e índice de sitemaps. Elementos são
 * achados pelo nome local, então namespace extra (media:, image:, news:) não muda nada.
 * Entidade externa nunca é expandida.
 */
final class FeedParser
{
    private const array ROOTS = ['rss', 'RDF', 'feed', 'urlset', 'sitemapindex'];

    /**
     * @throws FeedParseException quando não é XML ou não é feed nem sitemap
     */
    public static function parse(string $xml, string $feedUrl, ?DateTimeZone $assume = null): FeedDocument
    {
        $assume ??= new DateTimeZone('UTC');
        $root = self::root(ltrim((string) preg_replace('/^\xEF\xBB\xBF/', '', $xml)));

        if ($root->localName === 'sitemapindex') {
            return new FeedDocument([], self::sitemaps($root, $feedUrl, $assume));
        }

        $items = [];
        foreach (self::descendants($root, ['item', 'entry', 'url']) as $element) {
            $item = match ($element->localName) {
                'entry' => self::atomEntry($element, $feedUrl, $assume),
                'url' => self::sitemapUrl($element, $feedUrl, $assume),
                default => self::rssItem($element, $feedUrl, $assume),
            };
            if ($item !== null) {
                $items[] = $item;
            }
        }
        // sort do PHP é estável: entre itens sem data vale a ordem do documento
        usort($items, static fn(FeedItem $a, FeedItem $b): int => self::newestFirst($a->publishedAt, $b->publishedAt));

        return new FeedDocument($items, []);
    }

    private static function root(string $xml): Element
    {
        try {
            $root = XMLDocument::createFromString($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)->documentElement;
        } catch (DOMException|ValueError $error) {
            throw new FeedParseException('não é XML bem formado', previous: $error);
        }
        if ($root === null || !in_array($root->localName, self::ROOTS, true)) {
            throw new FeedParseException('XML não é feed nem sitemap');
        }

        return $root;
    }

    private static function rssItem(Element $item, string $feedUrl, DateTimeZone $assume): ?FeedItem
    {
        $guid = self::child($item, 'guid');
        $link = self::text(self::child($item, 'link'))
            ?? ($guid?->getAttribute('isPermaLink') === 'false' ? null : self::text($guid));

        return self::item($link, self::child($item, 'title'), self::child($item, 'pubDate', 'date'), $feedUrl, $assume);
    }

    private static function atomEntry(Element $entry, string $feedUrl, DateTimeZone $assume): ?FeedItem
    {
        $href = null;
        foreach (self::children($entry, 'link') as $link) {
            if (in_array($link->getAttribute('rel'), [null, 'alternate'], true)) {
                $href = $link->getAttribute('href');
                break;
            }
        }

        return self::item($href, self::child($entry, 'title'), self::child($entry, 'published', 'updated'), $feedUrl, $assume);
    }

    private static function sitemapUrl(Element $url, string $feedUrl, DateTimeZone $assume): ?FeedItem
    {
        $news = self::child($url, 'news');
        $date = ($news === null ? null : self::child($news, 'publication_date')) ?? self::child($url, 'lastmod');

        return self::item(self::text(self::child($url, 'loc')), $news === null ? null : self::child($news, 'title'), $date, $feedUrl, $assume);
    }

    private static function item(?string $link, ?Element $title, ?Element $date, string $feedUrl, DateTimeZone $assume): ?FeedItem
    {
        $url = $link === null ? null : Url::resolve($feedUrl, $link);

        return $url === null ? null : new FeedItem($url, self::text($title), Dates::parse(self::text($date), $assume));
    }

    /**
     * @return list<SitemapRef>
     */
    private static function sitemaps(Element $root, string $feedUrl, DateTimeZone $assume): array
    {
        $refs = [];
        foreach (self::descendants($root, ['sitemap']) as $sitemap) {
            $url = Url::resolve($feedUrl, self::text(self::child($sitemap, 'loc')) ?? '');
            if ($url !== null) {
                $refs[] = new SitemapRef($url, Dates::parse(self::text(self::child($sitemap, 'lastmod')), $assume));
            }
        }
        usort($refs, static fn(SitemapRef $a, SitemapRef $b): int => self::newestFirst($a->lastModified, $b->lastModified));

        return $refs;
    }

    private static function newestFirst(?DateTimeImmutable $a, ?DateTimeImmutable $b): int
    {
        return match (true) {
            $a === null && $b === null => 0,
            $a === null => 1,
            $b === null => -1,
            default => $b <=> $a,
        };
    }

    /**
     * @param list<string> $names
     *
     * @return list<Element>
     */
    private static function descendants(Element $root, array $names): array
    {
        return array_values(array_filter(
            iterator_to_array($root->getElementsByTagNameNS('*', '*'), false),
            static fn(Element $element): bool => in_array($element->localName, $names, true),
        ));
    }

    /**
     * Primeiro filho direto com algum dos nomes, na ordem de preferência dos nomes.
     */
    private static function child(Element $parent, string ...$names): ?Element
    {
        foreach ($names as $name) {
            $found = self::children($parent, $name)[0] ?? null;
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return list<Element>
     */
    private static function children(Element $parent, string $name): array
    {
        $found = [];
        for ($child = $parent->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            if ($child->localName === $name) {
                $found[] = $child;
            }
        }

        return $found;
    }

    private static function text(?Element $element): ?string
    {
        return Text::clean($element?->textContent);
    }
}
