<?php

declare(strict_types=1);

namespace ArticleExtractor;

use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\PostProcessor;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Content\Strategy\DensityHeuristic;
use ArticleExtractor\Content\Strategy\EmbeddedAppBody;
use ArticleExtractor\Content\Strategy\JsonLdBody;
use ArticleExtractor\Content\Strategy\ParagraphFallback;
use ArticleExtractor\Content\Strategy\PrioritySelectors;
use ArticleExtractor\Content\Strategy\SemanticContainers;
use ArticleExtractor\MetadataSources\MetadataExtractor;
use ArticleExtractor\Support\Url;
use DateTimeZone;

final readonly class Extractor
{
    /**
     * @param list<ContentStrategy> $strategies em ordem; vence a primeira com corpo
     * @param DateTimeZone $assumeTimezone fuso de data que a página publica sem fuso
     */
    public function __construct(
        private MetadataExtractor $metadata,
        private array $strategies,
        private DateTimeZone $assumeTimezone,
    ) {}

    public static function create(?DateTimeZone $assumeTimezone = null): self
    {
        return new self(
            MetadataExtractor::default(),
            [new PrioritySelectors(), new SemanticContainers(), new JsonLdBody(), new EmbeddedAppBody(), new DensityHeuristic(), new ParagraphFallback()],
            $assumeTimezone ?? new DateTimeZone('UTC'),
        );
    }

    /**
     * @param string $html bytes da página; sem $encoding, vale o <meta charset> ou UTF-8
     *
     * @throws \InvalidArgumentException quando a URL não é http(s) com host
     */
    public function extract(string $html, string $url, ?string $encoding = null): Article
    {
        Url::normalize($url); // valida: lança pra URL que não é http(s)

        $page = Page::parse($html, $url, $encoding);
        $metadata = $this->metadata->extract($page, $this->assumeTimezone);
        foreach ($this->strategies as $strategy) {
            // pós-processar pode tirar o corpo do piso (ex.: só sobrou o título repetido): aí vale a próxima
            $blocks = PostProcessor::process($strategy->extract($page), $metadata);
            if (Quality::accepts($blocks)) {
                return new Article($url, $metadata, $blocks, $strategy->name());
            }
        }

        return new Article($url, $metadata, [], null);
    }
}
