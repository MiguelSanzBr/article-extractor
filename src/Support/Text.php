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
}
