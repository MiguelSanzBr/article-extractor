<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use ArticleExtractor\Support\Text;
use DateTimeImmutable;
use DateTimeZone;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * Último recurso: o que dá pra tirar do HTML visível quando a página não declara nada.
 */
final class HtmlFallback implements MetadataSource
{
    private const string SUBTITLES = '[itemprop~="alternativeHeadline"], .subtitle, .subtitulo, .standfirst, .deck, .lead, .excerpt, .summary';

    private const string AUTHORS = '[itemprop~="author"] [itemprop~="name"], [rel~="author"], .byline .author';

    private const string BREADCRUMBS = '.breadcrumb a, .breadcrumbs a, [itemtype*="BreadcrumbList"] a';

    private const array NOT_A_SECTION = ['home', 'início', 'inicio', 'principal'];

    public function read(Page $page, DateTimeZone $assume): Metadata
    {
        $document = $page->document;
        [$titleFromTag, $siteName] = $this->splitTitle(Text::clean($document->title));
        $container = $document->querySelector('article, main');

        return new Metadata(
            title: Text::clean($document->querySelector('h1')?->textContent) ?? $titleFromTag,
            subtitle: $this->subtitle($document),
            authors: $this->authors($document),
            publishedAt: $container === null ? null : $this->date($container, $assume),
            image: $this->image($page),
            siteName: $siteName,
            section: $this->section($document),
        );
    }

    /**
     * "Título | Site" → [Título, Site]. O separador precisa de espaço dos dois lados:
     * "Covid-19 avança" fica inteiro.
     *
     * @return array{?string, ?string}
     */
    private function splitTitle(?string $title): array
    {
        if ($title === null || preg_match('/^(.*\S)\s+[|\-–—]\s+(\S.*)$/u', $title, $m) !== 1) {
            return [$title, null];
        }
        $length = Text::length($m[2]);

        return [$m[1], $length > 2 && $length < 60 ? $m[2] : null];
    }

    private function subtitle(HTMLDocument $document): ?string
    {
        foreach ($document->querySelectorAll(self::SUBTITLES) as $element) {
            $text = Text::clean($element->textContent);
            if ($text !== null && Text::length($text) > 20 && Text::length($text) < 500) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function authors(HTMLDocument $document): array
    {
        $names = [];
        foreach ($document->querySelectorAll(self::AUTHORS) as $element) {
            $names[] = $element->getAttribute('content') ?? $element->textContent;
        }

        return Fields::people($names);
    }

    /**
     * Só dentro do artigo: o primeiro <time> da página costuma ser de outra matéria na lateral.
     */
    private function date(Element $container, DateTimeZone $assume): ?DateTimeImmutable
    {
        $date = Fields::date($container->querySelector('time[datetime]')?->getAttribute('datetime'), $assume);
        $item = $container->querySelector('[itemprop~="datePublished"]');

        return $date ?? ($item === null ? null : Fields::date($item->getAttribute('content') ?? $item->getAttribute('datetime') ?? $item->textContent, $assume));
    }

    /**
     * Atributos de carregamento preguiçoso antes do src: o src costuma ser um placeholder data:.
     */
    private function image(Page $page): ?string
    {
        foreach ($page->document->querySelectorAll('article img, main img, figure img') as $img) {
            $srcset = $img->getAttribute('srcset');
            $candidates = [
                $img->getAttribute('data-src'),
                $img->getAttribute('data-lazy-src'),
                $srcset === null ? null : explode(' ', trim(explode(',', $srcset)[0]))[0],
                $img->getAttribute('src'),
            ];
            foreach ($candidates as $candidate) {
                $url = Fields::url($candidate, $page);
                if ($url !== null) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function section(HTMLDocument $document): ?string
    {
        $crumbs = [];
        foreach ($document->querySelectorAll(self::BREADCRUMBS) as $link) {
            $text = Text::clean($link->textContent);
            if ($text !== null && Text::length($text) > 1 && Text::length($text) < 50 && !in_array(mb_strtolower($text), self::NOT_A_SECTION, true)) {
                $crumbs[] = $text;
            }
        }
        $crumbs = array_slice(array_unique($crumbs), 0, 3);

        return $crumbs === [] ? null : implode(', ', $crumbs);
    }
}
