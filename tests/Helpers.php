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
