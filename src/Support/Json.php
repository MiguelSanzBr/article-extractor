<?php

declare(strict_types=1);

namespace ArticleExtractor\Support;

final class Json
{
    /**
     * @return array<mixed>|null
     */
    public static function decode(string $json): ?array
    {
        $value = json_decode($json, true);

        return is_array($value) ? $value : null;
    }

    /**
     * Acha "<marcador> = {...}" dentro de um script e decodifica o objeto. A varredura conta
     * chaves fora de string JSON, então "}" dentro de texto não fecha o objeto antes da hora.
     *
     * @return array<mixed>|null
     */
    public static function objectAssignedTo(string $source, string $marker): ?array
    {
        $at = strpos($source, $marker);
        $equals = $at === false ? false : strpos($source, '=', $at + strlen($marker));
        if ($equals === false) {
            return null;
        }

        $start = $equals + 1 + strspn($source, " \t\r\n", $equals + 1);
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
