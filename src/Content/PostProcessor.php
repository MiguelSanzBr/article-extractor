<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Metadata;
use ArticleExtractor\Support\Text;

final class PostProcessor
{
    private const float SAME_LENGTH = 0.9;

    /** linha fina tem algumas frases; descrição maior que isso é o próprio corpo, não repetição dele */
    private const int MAX_REFERENCE = 500;

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
     * Título e linha fina costumam vir repetidos como primeiro parágrafo. Repetir é ter o
     * mesmo texto depois de tirar caixa e pontuação, ou um conter o outro com tamanhos
     * quase iguais. Linear: nada de distância de edição.
     *
     * @param array<string> $references
     */
    private static function repeatsAny(string $text, array $references): bool
    {
        $text = self::letters($text);
        foreach ($references as $reference) {
            $reference = self::letters($reference);
            if (mb_strlen($reference) > self::MAX_REFERENCE) {
                continue;
            }
            $similarLength = min(strlen($text), strlen($reference)) / max(1, strlen($text), strlen($reference)) > self::SAME_LENGTH;
            if ($similarLength && (str_contains($text, $reference) || str_contains($reference, $text))) {
                return true;
            }
        }

        return false;
    }

    private static function letters(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text));
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
        $pairs = self::pairChains($blocks);
        $kept = [];
        for ($i = 0, $count = count($blocks); $i < $count;) {
            $end = $i + 2 * $pairs[$i];
            $after = $blocks[$end] ?? null;
            if ($pairs[$i] >= 2
                && ($blocks[$i - 1] ?? null)?->type === BlockType::Paragraph
                && $after?->type === BlockType::Paragraph
                && Text::length($after->text) > self::BODY_AFTER_TEASERS_MIN) {
                $i = $end;

                continue;
            }
            $kept[] = $blocks[$i++];
        }

        return $kept;
    }

    /**
     * Quantos pares (título, parágrafo curto) seguidos começam em cada posição, contados de
     * trás pra frente numa passada só.
     *
     * @param list<Block> $blocks
     *
     * @return array<int, int>
     */
    private static function pairChains(array $blocks): array
    {
        $pairs = [];
        foreach (array_reverse(array_keys($blocks)) as $i) {
            $next = $blocks[$i + 1] ?? null;
            $isPair = $blocks[$i]->type === BlockType::Heading
                && $next?->type === BlockType::Paragraph
                && Text::length($next->text) < self::TEASER_MAX;
            $pairs[$i] = $isPair ? 1 + ($pairs[$i + 2] ?? 0) : 0;
        }

        return $pairs;
    }
}
