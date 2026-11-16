<?php

declare(strict_types=1);

namespace ArticleExtractor;

use DateTimeImmutable;

final readonly class Metadata
{
    /**
     * @param list<string> $authors
     * @param list<string> $tags
     */
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
        public array $authors = [],
        public ?DateTimeImmutable $publishedAt = null,
        public ?DateTimeImmutable $modifiedAt = null,
        public ?string $image = null,
        public ?string $siteName = null,
        public ?string $section = null,
        public array $tags = [],
        public ?string $language = null,
        public ?string $canonical = null,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Cascata campo a campo: o que já tem valor fica, o que está vazio vem de $next.
     */
    public function fillFrom(self $next): self
    {
        return new self(
            title: $this->title ?? $next->title,
            subtitle: $this->subtitle ?? $next->subtitle,
            authors: $this->authors !== [] ? $this->authors : $next->authors,
            publishedAt: $this->publishedAt ?? $next->publishedAt,
            modifiedAt: $this->modifiedAt ?? $next->modifiedAt,
            image: $this->image ?? $next->image,
            siteName: $this->siteName ?? $next->siteName,
            section: $this->section ?? $next->section,
            tags: $this->tags !== [] ? $this->tags : $next->tags,
            language: $this->language ?? $next->language,
            canonical: $this->canonical ?? $next->canonical,
        );
    }
}
