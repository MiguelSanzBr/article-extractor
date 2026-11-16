<?php

declare(strict_types=1);

namespace ArticleExtractor\Support;

use InvalidArgumentException;

final class Url
{
    private const array TRACKING = ['fbclid', 'gclid', 'mc_cid', 'mc_eid'];

    /**
     * Resolve uma referência contra a base (RFC 3986, seção 5.2). Só devolve http(s):
     * referência vazia ou com outro esquema (data:, file:, javascript:…) vira null.
     */
    public static function resolve(string $base, string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }
        // referência com esquema: só http(s) sai daqui (nada de file:, data:, javascript:…)
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $reference, $scheme) === 1) {
            return in_array(strtolower($scheme[1]), ['http', 'https'], true) ? $reference : null;
        }

        $parts = parse_url($base);
        if (!isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $scheme = $parts['scheme'];
        if (str_starts_with($reference, '//')) {
            return $scheme . ':' . $reference;
        }

        $authority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $basePath = $parts['path'] ?? '';
        preg_match('/^([^?#]*)(\?[^#]*)?(#.*)?$/s', $reference, $match);
        $path = $match[1] ?? '';
        $query = $match[2] ?? '';
        $fragment = $match[3] ?? '';

        if ($path === '') {
            $path = $basePath;
            if ($query === '' && isset($parts['query'])) {
                $query = '?' . $parts['query'];
            }
        } elseif (!str_starts_with($path, '/')) {
            $directory = $basePath === '' ? '/' : (string) preg_replace('~[^/]*$~', '', $basePath);
            $path = $directory . $path;
        }

        return $scheme . '://' . $authority . self::removeDotSegments($path) . $query . $fragment;
    }

    /**
     * Forma canônica pra deduplicar: scheme e host minúsculos, sem porta padrão, sem fragmento,
     * sem parâmetro de rastreio, query ordenada e sem barra final (menos na raiz).
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!isset($parts['host']) || !in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("URL precisa ser http(s) com host: {$url}");
        }

        $port = $parts['port'] ?? null;
        $isDefaultPort = $port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $path = rtrim($parts['path'] ?? '', '/');

        $pairs = array_filter(
            explode('&', $parts['query'] ?? ''),
            static function (string $pair): bool {
                $key = strtolower(explode('=', $pair)[0]);

                return $key !== '' && !str_starts_with($key, 'utm_') && !in_array($key, self::TRACKING, true);
            },
        );
        sort($pairs);

        return $scheme . '://' . strtolower($parts['host'])
            . ($isDefaultPort ? '' : ':' . $port)
            . ($path === '' ? '/' : $path)
            . ($pairs === [] ? '' : '?' . implode('&', $pairs));
    }

    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : null;
    }

    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $output = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                if (count($output) > 1) {
                    array_pop($output);
                }
            } elseif ($segment !== '.') {
                $output[] = $segment;
            }
        }

        $last = end($segments);

        return implode('/', $output) . ($last === '.' || $last === '..' ? '/' : '');
    }
}
