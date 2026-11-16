<?php

declare(strict_types=1);

namespace ArticleExtractor\Content\Strategy;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\BlockExtractor;
use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\NoiseRules;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Page;
use ArticleExtractor\Support\Text;

/**
 * Corpo que vem no estado de apps em JS: content_elements do Arc XP e o objeto do artigo
 * no __NEXT_DATA__. Serve quando o DOM chega quase vazio.
 */
final class EmbeddedAppBody implements ContentStrategy
{
    private const array NEXT_ARTICLE_KEYS = ['article', 'post', 'content', 'data'];

    private const array NEXT_BODY_KEYS = ['body', 'content', 'text', 'articleBody'];

    public function name(): string
    {
        return 'embedded-app';
    }

    public function extract(Page $page): array
    {
        $blocks = $page->embedded->fusion === null ? [] : $this->fusion($page->embedded->fusion);
        if ($blocks === [] && $page->embedded->nextPageProps !== null) {
            $blocks = $this->next($page->embedded->nextPageProps);
        }

        return Quality::accepts($blocks) ? $blocks : [];
    }

    /**
     * @param array<mixed> $content
     *
     * @return list<Block>
     */
    private function fusion(array $content): array
    {
        $blocks = [];
        foreach (is_array($content['content_elements'] ?? null) ? $content['content_elements'] : [] as $element) {
            $type = is_array($element) ? ($element['type'] ?? null) : null;
            $html = is_array($element) ? ($element['content'] ?? null) : null;
            $found = match ($type) {
                'text' => [self::block(BlockType::Paragraph, $html)],
                'header' => [self::block(BlockType::Heading, $html)],
                'list' => array_map(
                    static fn(mixed $item): ?Block => self::block(BlockType::ListItem, is_array($item) ? ($item['content'] ?? null) : $item),
                    is_array($element['items'] ?? null) ? $element['items'] : [],
                ),
                'raw_html' => is_string($html) ? BlockExtractor::fromHtmlFragment($html) : [],
                default => [],
            };
            array_push($blocks, ...array_filter($found));
        }

        return $blocks;
    }

    /**
     * @param array<mixed> $pageProps
     *
     * @return list<Block>
     */
    private function next(array $pageProps): array
    {
        foreach (self::NEXT_ARTICLE_KEYS as $key) {
            if (is_array($pageProps[$key] ?? null)) {
                foreach (self::NEXT_BODY_KEYS as $bodyKey) {
                    $body = $pageProps[$key][$bodyKey] ?? null;
                    if (is_string($body) && trim($body) !== '') {
                        return BlockExtractor::fromText($body);
                    }
                }

                return [];
            }
        }

        return [];
    }

    private static function block(BlockType $type, mixed $html): ?Block
    {
        $text = is_string($html) ? Text::clean(strip_tags($html)) : null;
        if ($text === null || Text::length($text) < ($type === BlockType::Heading ? 3 : 20) || NoiseRules::isBoilerplate($text)) {
            return null;
        }

        return new Block($type, $text);
    }
}
