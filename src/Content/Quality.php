<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Block;
use ArticleExtractor\Support\Text;

/**
 * Piso de corpo: abaixo disso é chamada, teaser ou paywall, não matéria.
 */
final class Quality
{
    public const int MIN_BODY_CHARS = 300;

    /**
     * @param list<Block> $blocks
     */
    public static function accepts(array $blocks): bool
    {
        return self::bodyChars($blocks) >= self::MIN_BODY_CHARS;
    }

    /**
     * @param list<Block> $blocks
     */
    public static function bodyChars(array $blocks): int
    {
        $chars = 0;
        foreach ($blocks as $block) {
            if ($block->type->isBody()) {
                $chars += Text::length($block->text);
            }
        }

        return $chars;
    }
}
