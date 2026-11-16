<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

use ArticleExtractor\Support\Text;

/**
 * Feed e sitemap misturam matéria com página de tag, autor, paginação e arquivo. Isso
 * separa o que vale baixar. Vídeo e URL com utm continuam valendo.
 */
final class ArticleUrlFilter
{
    private const int MIN_PATH = 20;

    private const array LISTING_SEGMENTS = [
        'tag', 'tags', 'autor', 'autores', 'author', 'authors', 'categoria', 'categorias', 'category', 'page', 'pagina',
        'search', 'busca', 'login', 'newsletter', 'feed', 'rss', 'assinatura', 'subscribe', 'wp-json', 'wp-admin',
    ];

    private const array FILE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp3', 'mp4', 'zip', 'xml', 'json', 'css', 'js'];

    public static function accepts(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        $path = rtrim($parts['path'] ?? '', '/');
        $segments = array_map(strtolower(...), explode('/', trim($path, '/')));

        return Text::length($path) >= self::MIN_PATH
            && array_intersect($segments, self::LISTING_SEGMENTS) === []
            && !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::FILE_EXTENSIONS, true);
    }
}
