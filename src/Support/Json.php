<?php

declare(strict_types=1);

namespace ArticleExtractor\Support;

final class Json
{
    /**
     * @return array<mixed>|null
     */
    public static function decode(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }
        $value = json_decode($json, true);
        // HTML de verdade traz JSON quebrado: quebra de linha crua dentro de string, CDATA, comentário
        $value = is_array($value) ? $value : json_decode(self::repair($json), true);

        return is_array($value) ? $value : null;
    }

    private static function repair(string $json): string
    {
        $json = (string) preg_replace('~^\s*(?:<!--|/\*\s*<!\[CDATA\[\s*\*/|<!\[CDATA\[)|(?:-->|/\*\s*\]\]>\s*\*/|\]\]>)\s*$~', '', $json);

        $repaired = '';
        $inString = false;
        for ($i = 0, $length = strlen($json); $i < $length; $i++) {
            $char = $json[$i];
            if ($inString && $char === '\\') {
                $repaired .= $char . ($json[++$i] ?? '');

                continue;
            }
            if ($char === '"') {
                $inString = !$inString;
            }
            // caractere de controle cru só é inválido dentro de string: lá vira \u00XX
            $repaired .= $inString && ord($char) < 0x20 ? sprintf('\\u%04x', ord($char)) : $char;
        }

        return $repaired;
    }

    /**
     * Acha "<marcador> = {...}" dentro de um script e decodifica o objeto. A varredura conta
     * chaves fora de string JSON, então "}" dentro de texto não fecha o objeto antes da hora.
     *
     * @return array<mixed>|null
     */
    public static function objectAssignedTo(string $source, string $marker): ?array
    {
        if (preg_match('/' . preg_quote($marker, '/') . '\s*=\s*/', $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $start = $match[0][1] + strlen($match[0][0]);
        if (($source[$start] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        for ($i = $start, $length = strlen($source); $i < $length; $i++) {
            $char = $source[$i];
            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }
            } elseif ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                return self::decode(substr($source, $start, $i - $start + 1));
            }
        }

        return null;
    }
}
