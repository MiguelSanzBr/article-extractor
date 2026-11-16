<?php

declare(strict_types=1);

namespace ArticleExtractor;

final readonly class Article
{
    /**
     * @param list<Block> $blocks
     * @param string|null $strategy nome da estratégia que achou o corpo; null quando não achou
     */
    public function __construct(
        public string $url,
        public Metadata $metadata,
        public array $blocks,
        public ?string $strategy,
    ) {}

    public function hasBody(): bool
    {
        return $this->blocks !== [];
    }

    public function text(): string
    {
        return implode("\n\n", array_map(
            static fn(Block $block): string => ($block->type === BlockType::ListItem ? '- ' : '') . $block->text,
            $this->blocks,
        ));
    }

    /**
     * HTML montado a partir dos blocos, com todo texto escapado: seguro pra exibir sem
     * sanitizar de novo, venha o corpo do DOM, do JSON-LD ou do estado de app.
     */
    public function html(): string
    {
        $html = '';
        $inList = false;
        foreach ($this->blocks as $block) {
            $isItem = $block->type === BlockType::ListItem;
            $html .= match (true) {
                $isItem && !$inList => '<ul>',
                !$isItem && $inList => '</ul>',
                default => '',
            };
            $inList = $isItem;
            $text = htmlspecialchars($block->text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $html .= match ($block->type) {
                BlockType::Paragraph => "<p>{$text}</p>",
                BlockType::Heading => "<h2>{$text}</h2>",
                BlockType::ListItem => "<li>{$text}</li>",
                BlockType::Quote => "<blockquote><p>{$text}</p></blockquote>",
                BlockType::Caption => "<figcaption>{$text}</figcaption>",
            };
        }

        return $html . ($inList ? '</ul>' : '');
    }

    public function wordCount(): int
    {
        return preg_match_all("/[\\p{L}\\p{N}]+(?:['’-][\\p{L}\\p{N}]+)*/u", $this->text()) ?: 0;
    }

    /**
     * @return array{url: string, title: ?string, subtitle: ?string, authors: list<string>, published_at: ?string, modified_at: ?string, image: ?string, site_name: ?string, section: ?string, tags: list<string>, language: ?string, canonical: ?string, strategy: ?string, word_count: int, text: string, html: string, blocks: list<array{type: string, text: string}>}
     */
    public function toArray(): array
    {
        $meta = $this->metadata;

        return [
            'url' => $this->url,
            'title' => $meta->title,
            'subtitle' => $meta->subtitle,
            'authors' => $meta->authors,
            'published_at' => $meta->publishedAt?->format('Y-m-d\TH:i:s\Z'),
            'modified_at' => $meta->modifiedAt?->format('Y-m-d\TH:i:s\Z'),
            'image' => $meta->image,
            'site_name' => $meta->siteName,
            'section' => $meta->section,
            'tags' => $meta->tags,
            'language' => $meta->language,
            'canonical' => $meta->canonical,
            'strategy' => $this->strategy,
            'word_count' => $this->wordCount(),
            'text' => $this->text(),
            'html' => $this->html(),
            'blocks' => array_map(static fn(Block $block): array => ['type' => $block->type->value, 'text' => $block->text], $this->blocks),
        ];
    }
}
