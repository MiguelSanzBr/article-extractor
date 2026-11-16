<?php

declare(strict_types=1);

use ArticleExtractor\Page;

function page(string $head = '', string $body = '', string $url = 'https://a.com/n/1', string $htmlAttrs = ''): Page
{
    return Page::parse("<!doctype html><html {$htmlAttrs}><head>{$head}</head><body>{$body}</body></html>", $url);
}

/** @param array<mixed> $data */
function ld(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE) . '</script>';
}

function utc(): DateTimeZone
{
    return new DateTimeZone('UTC');
}

/** n parágrafos distintos de ~95 caracteres: 4 deles passam no piso de 300 */
function paragraphs(int $count, string $prefix = 'Parágrafo'): string
{
    $html = '';
    for ($i = 1; $i <= $count; $i++) {
        $html .= "<p>{$prefix} {$i} do corpo da matéria, com texto suficiente para contar como conteúdo real aqui.</p>";
    }

    return $html;
}

/** @param list<ArticleExtractor\Block> $blocks @return list<string> */
function blockTypes(array $blocks): array
{
    return array_map(static fn (ArticleExtractor\Block $b): string => $b->type->value, $blocks);
}
