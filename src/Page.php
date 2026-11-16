<?php

declare(strict_types=1);

namespace ArticleExtractor;

use ArticleExtractor\Support\Json;
use ArticleExtractor\Support\Url;
use Dom\HTMLDocument;

/**
 * Uma página já parseada. O documento não é alterado depois do parse: quem precisa limpar
 * trabalha num clone, pra uma etapa nunca apagar o que a seguinte vai ler.
 */
final readonly class Page
{
    /** tipos schema.org que contam como matéria; igualdade exata, sem caixa */
    private const array ARTICLE_TYPES = [
        'article', 'newsarticle', 'reportagenewsarticle', 'analysisnewsarticle', 'opinionnewsarticle',
        'backgroundnewsarticle', 'reviewnewsarticle', 'blogposting', 'liveblogposting', 'report', 'techarticle',
    ];

    private function __construct(
        public HTMLDocument $document,
        public string $url,
        public string $baseUrl,
        public EmbeddedData $embedded,
    ) {}

    public static function parse(string $html, string $url, ?string $encoding = null): self
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, $encoding);
        $base = $document->querySelector('base[href]')?->getAttribute('href');
        $baseUrl = ($base === null ? null : Url::resolve($url, $base)) ?? $url;

        $jsonLd = self::jsonLd($document);

        return new self($document, $url, $baseUrl, new EmbeddedData(
            jsonLd: $jsonLd,
            article: self::firstArticle($jsonLd),
            nextPageProps: self::nextPageProps($document),
            fusion: self::fusion($document),
        ));
    }

    /**
     * @return list<array<mixed>>
     */
    private static function jsonLd(HTMLDocument $document): array
    {
        $items = [];
        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $data = Json::decode($script->textContent ?? '');
            if ($data === null) {
                continue;
            }
            $candidates = match (true) {
                is_array($data['@graph'] ?? null) => $data['@graph'],
                array_is_list($data) => $data,
                default => [$data],
            };
            foreach ($candidates as $candidate) {
                if (is_array($candidate)) {
                    $items[] = $candidate;
                }
            }
        }

        return $items;
    }

    /**
     * @param list<array<mixed>> $items
     *
     * @return array<mixed>|null
     */
    private static function firstArticle(array $items): ?array
    {
        foreach ($items as $item) {
            $types = is_array($item['@type'] ?? null) ? $item['@type'] : [$item['@type'] ?? null];
            foreach ($types as $type) {
                if (is_string($type) && in_array(strtolower($type), self::ARTICLE_TYPES, true)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @return array<mixed>|null
     */
    private static function nextPageProps(HTMLDocument $document): ?array
    {
        $data = Json::decode($document->querySelector('script#__NEXT_DATA__')->textContent ?? '');
        $props = $data['props'] ?? null;
        $pageProps = is_array($props) ? ($props['pageProps'] ?? null) : null;

        return is_array($pageProps) ? $pageProps : null;
    }

    /**
     * @return array<mixed>|null
     */
    private static function fusion(HTMLDocument $document): ?array
    {
        foreach ($document->querySelectorAll('script') as $script) {
            $source = $script->textContent ?? '';
            if (str_contains($source, 'Fusion.globalContent')) {
                $object = Json::objectAssignedTo($source, 'Fusion.globalContent');
                if ($object !== null) {
                    return $object;
                }
            }
        }

        return null;
    }
}
