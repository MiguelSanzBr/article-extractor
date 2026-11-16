<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

final class RobotsTxt
{
    /**
     * Regras pro robô cujo User-Agent é $userAgent: vale o grupo com o nome de produto mais
     * específico que casa (sem caixa), senão o grupo *.
     */
    public static function parse(string $body, string $userAgent): RobotsRules
    {
        $product = strtolower(explode('/', trim($userAgent))[0]);
        $agents = [];
        $rules = [];
        $delays = [];
        $sitemaps = [];
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = (string) preg_replace('/#.*/', '', $line);
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map(trim(...), explode(':', $line, 2));
            $key = strtolower($key);
            // user-agent seguido de user-agent é o mesmo grupo; depois de uma regra, abre outro
            $group = $key === 'user-agent' && !$lastWasAgent ? count($agents) : array_key_last($agents);

            if ($key === 'sitemap' && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                $sitemaps[] = $value;
            } elseif ($key === 'user-agent') {
                $agents[$group][] = strtolower($value);
            } elseif ($group !== null && ($key === 'allow' || $key === 'disallow')) {
                $rules[$group][] = [$key === 'allow', $value];
            } elseif ($group !== null && $key === 'crawl-delay' && is_numeric($value)) {
                $delays[$group] ??= (float) $value;
            }
            $lastWasAgent = $key === 'user-agent';
        }

        $chosen = self::groupsFor($product, $agents);

        return new RobotsRules(
            array_merge(...array_map(static fn(int $group): array => $rules[$group] ?? [], $chosen)),
            array_values(array_filter(array_map(static fn(int $group): ?float => $delays[$group] ?? null, $chosen)))[0] ?? null,
            $sitemaps,
        );
    }

    /**
     * Grupos do nome de produto mais específico que casa; sem nenhum, os grupos *. Vários
     * grupos com o mesmo nome valem juntos.
     *
     * @param array<array-key, list<string>> $agents
     *
     * @return list<array-key>
     */
    private static function groupsFor(string $product, array $agents): array
    {
        $chosen = [];
        $bestLength = null;
        foreach ($agents as $group => $names) {
            foreach ($names as $agent) {
                if ($agent !== '*' && !str_starts_with($product, $agent)) {
                    continue;
                }
                $length = $agent === '*' ? 0 : strlen($agent);
                if ($bestLength === null || $length > $bestLength) {
                    [$chosen, $bestLength] = [[$group], $length];
                } elseif ($length === $bestLength) {
                    $chosen[] = $group;
                }
            }
        }

        return $chosen;
    }
}
