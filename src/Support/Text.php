<?php

declare(strict_types=1);

namespace ArticleExtractor\Support;

final class Text
{
    /**
     * Decodifica entidades, troca qualquer espaço Unicode por espaço comum, colapsa e apara.
     * Texto que sobra vazio vira null, pra "ausente" ter um jeito só de ser escrito.
     */
    public static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = html_entity_decode(mb_scrub($text, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    public static function length(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }

    /**
     * 1 - distância de edição / tamanho do maior, por caractere e sem caixa.
     */
    public static function similarity(string $a, string $b): float
    {
        $a = mb_str_split(mb_strtolower($a, 'UTF-8'), 1, 'UTF-8');
        $b = mb_str_split(mb_strtolower($b, 'UTF-8'), 1, 'UTF-8');
        $longest = max(count($a), count($b));

        if ($a === [] || $b === []) {
            return 0.0;
        }

        return 1 - self::distance($a, $b) / $longest;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function distance(array $a, array $b): int
    {
        $alphabet = array_flip(array_values(array_unique([...$a, ...$b])));

        // até 256 caracteres distintos cabem em 1 byte cada, e aí o levenshtein nativo serve
        if (count($alphabet) <= 256) {
            return levenshtein(self::encode($a, $alphabet), self::encode($b, $alphabet));
        }

        $previous = range(0, count($b));
        foreach ($a as $i => $charA) {
            $current = [$i + 1];
            foreach ($b as $j => $charB) {
                $current[] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($charA === $charB ? 0 : 1));
            }
            $previous = $current;
        }

        return $previous[count($b)];
    }

    /**
     * @param list<string> $chars
     * @param array<string, int> $alphabet
     */
    private static function encode(array $chars, array $alphabet): string
    {
        $bytes = '';
        foreach ($chars as $char) {
            $bytes .= chr($alphabet[$char]);
        }

        return $bytes;
    }
}
