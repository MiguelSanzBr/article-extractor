<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Metadata;
use ArticleExtractor\Support\Text;

final class PostProcessor
{
    private const float SAME_AS_METADATA = 0.9;

    private const int TEASER_MAX = 200;

    private const int BODY_AFTER_TEASERS_MIN = 100;

    /**
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    public static function process(array $blocks, Metadata $metadata): array
    {
        $references = array_filter([$metadata->title, $metadata->subtitle]);
        $blocks = array_values(array_filter($blocks, static fn(Block $block): bool => !self::repeatsAny($block->text, $references)));

        return self::withoutTeaserRuns($blocks);
    }

    /**
     * Título e linha fina costumam vir repetidos como primeiro parágrafo.
     *
     * @param array<string> $references
     */
    private static function repeatsAny(string $text, array $references): bool
    {
        foreach ($references as $reference) {
            [$a, $b] = [Text::length($text), Text::length($reference)];
            // a semelhança nunca passa de menor/maior; abaixo do limite nem calcula
            if (min($a, $b) / max($a, $b) > self::SAME_AS_METADATA && Text::similarity($text, $reference) > self::SAME_AS_METADATA) {
                return true;
            }
        }

        return false;
    }

    /**
     * Widget de "relacionadas" no meio do texto: 2+ pares (título, parágrafo curto) entre
     * um parágrafo antes e um parágrafo longo depois. Um par só é seção com introdução.
     *
     * @param list<Block> $blocks
     *
     * @return list<Block>
     */
    private static function withoutTeaserRuns(array $blocks): array
    {
        $kept = [];
        for ($i = 0, $count = count($blocks); $i < $count;) {
            $end = self::teaserRunEnd($blocks, $i);
            if ($end !== null) {
                $i = $end;

                continue;
            }
            $kept[] = $blocks[$i++];
        }

        return $kept;
    }

    /**
     * @param list<Block> $blocks
     */
    private static function teaserRunEnd(array $blocks, int $start): ?int
    {
        if (($blocks[$start - 1] ?? null)?->type !== BlockType::Paragraph) {
            return null;
        }
        $end = $start;
        while (($blocks[$end] ?? null)?->type === BlockType::Heading
            && ($blocks[$end + 1] ?? null)?->type === BlockType::Paragraph
            && Text::length($blocks[$end + 1]->text) < self::TEASER_MAX) {
            $end += 2;
        }
        $after = $blocks[$end] ?? null;
        $isRun = ($end - $start) / 2 >= 2 && $after?->type === BlockType::Paragraph && Text::length($after->text) > self::BODY_AFTER_TEASERS_MIN;

        return $isRun ? $end : null;
    }
}
