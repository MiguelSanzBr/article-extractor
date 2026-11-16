<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

final readonly class RobotsRules
{
    /**
     * @param list<array{bool, string}> $rules [é Allow?, padrão] do grupo que vale pro robô
     * @param list<string> $sitemaps
     */
    public function __construct(
        private array $rules,
        public ?float $crawlDelay,
        public array $sitemaps,
    ) {}

    /**
     * A regra de padrão mais longo que casa vence; no empate, Allow (RFC 9309).
     */
    public function isAllowed(string $url): bool
    {
        $path = (parse_url($url, PHP_URL_PATH) ?: '/') . (($query = parse_url($url, PHP_URL_QUERY)) ? '?' . $query : '');
        $verdict = true;
        $longest = -1;
        foreach ($this->rules as [$allow, $pattern]) {
            $length = strlen($pattern);
            if ($pattern !== '' && self::matches($pattern, $path) && ($length > $longest || ($length === $longest && $allow))) {
                [$verdict, $longest] = [$allow, $length];
            }
        }

        return $verdict;
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $regex = str_replace('\*', '.*', preg_quote($anchored ? substr($pattern, 0, -1) : $pattern, '~'));

        return preg_match('~^' . $regex . ($anchored ? '$' : '') . '~', $path) === 1;
    }
}
