<?php

declare(strict_types=1);

namespace ArticleExtractor\MetadataSources;

use ArticleExtractor\Page;
use ArticleExtractor\Support\Dates;
use ArticleExtractor\Support\Text;
use ArticleExtractor\Support\Url;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Conversão de valor bruto (atributo ou JSON) em campo de metadado. Cada função aceita
 * qualquer coisa e devolve null/[] para o tipo inesperado, pra um campo estranho nunca
 * derrubar os outros.
 */
final class Fields
{
    public static function text(mixed $value): ?string
    {
        return is_string($value) ? Text::clean($value) : null;
    }

    /**
     * Nomes de pessoa. Valor que é URL ou caminho (comum em article:author) não é nome.
     *
     * @return list<string>
     */
    public static function people(mixed $value): array
    {
        $names = [];
        foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
            $name = self::text(is_array($item) ? ($item['name'] ?? null) : $item);
            if ($name !== null && preg_match('~^(https?://|www\.|/)~i', $name) !== 1) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Lista de termos a partir de texto separado por vírgula ou de lista; sem repetição, sem caixa.
     *
     * @return list<string>
     */
    public static function terms(mixed $value): array
    {
        $terms = [];
        foreach (is_array($value) ? $value : [$value] as $item) {
            foreach (is_string($item) ? explode(',', $item) : [] as $part) {
                $term = Text::clean($part);
                if ($term !== null && !isset($terms[mb_strtolower($term)])) {
                    $terms[mb_strtolower($term)] = $term;
                }
            }
        }

        return array_values($terms);
    }

    public static function date(mixed $value, DateTimeZone $assume): ?DateTimeImmutable
    {
        return is_string($value) ? Dates::parse($value, $assume) : null;
    }

    public static function url(mixed $value, Page $page): ?string
    {
        return is_string($value) ? Url::resolve($page->baseUrl, $value) : null;
    }

    /**
     * Imagem em texto, objeto com url/src, ou lista de qualquer um dos dois.
     */
    public static function image(mixed $value, Page $page, int $depth = 0): ?string
    {
        if (is_array($value) && $depth < 2) {
            return self::image(array_is_list($value) ? ($value[0] ?? null) : ($value['url'] ?? $value['src'] ?? null), $page, $depth + 1);
        }

        return self::url($value, $page);
    }

    /**
     * pt_BR, pt-br e PT-BR viram pt-BR; o que não parece código de idioma vira null.
     */
    public static function language(?string $value): ?string
    {
        if ($value === null || preg_match('/^([a-z]{2,3})(?:[-_]([a-z]{2}))?$/i', trim($value), $m) !== 1) {
            return null;
        }

        return strtolower($m[1]) . (isset($m[2]) ? '-' . strtoupper($m[2]) : '');
    }

    /**
     * Desce por chaves aninhadas de um array JSON; qualquer passo que não é array dá null.
     */
    public static function at(mixed $data, string ...$path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($data)) {
                return null;
            }
            $data = $data[$key] ?? null;
        }

        return $data;
    }

    public static function meta(Page $page, string $key): ?string
    {
        return self::metaAll($page, $key)[0] ?? null;
    }

    /**
     * Valores não vazios de <meta> por property ou name (sites misturam os dois).
     *
     * @return list<string>
     */
    public static function metaAll(Page $page, string $key): array
    {
        $values = [];
        foreach ($page->document->querySelectorAll("meta[property=\"{$key}\"], meta[name=\"{$key}\"]") as $meta) {
            $value = Text::clean($meta->getAttribute('content'));
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }
}
