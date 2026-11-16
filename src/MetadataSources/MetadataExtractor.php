<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Metadata;
use ArticleExtractor\Page;
use DateTimeZone;

final readonly class MetadataExtractor
{
    /**
     * @param list<MetadataSource> $sources em ordem de confiança: a primeira com valor vence, campo a campo
     */
    public function __construct(private array $sources) {}

    public static function default(): self
    {
        return new self([new MetaTags(), new JsonLd(), new TwitterCard(), new EmbeddedApp(), new HtmlFallback()]);
    }

    public function extract(Page $page, DateTimeZone $assume): Metadata
    {
        $metadata = Metadata::empty();
        foreach ($this->sources as $source) {
            $metadata = $metadata->fillFrom($source->read($page, $assume));
        }

        return $metadata;
    }
}
