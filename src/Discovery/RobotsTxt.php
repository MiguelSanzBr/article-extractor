<?php

declare(strict_types=1);

namespace ArticleExtractor\Discovery;

final class RobotsTxt
{
    /**
     * Regras pro robô cujo User-Agent é $userAgent: valem os grupos com o nome do produto
     * (sem caixa, sem versão), senão os grupos *.
     */
    public static function parse(string $body, string $userAgent): RobotsRules
    {
        $product = strtolower(explode('/', trim($userAgent))[0]);
        $agents = [];
        $rules = [];
        $delays = [];
        $sitemaps = [];
        $lastWasAgent = false;

        $body = str_starts_with($body, "\u{FEFF}") ? substr($body, 3) : $body;
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
                $agents[$group][] = strtolower(explode('/', $value)[0]);
            } elseif ($group !== null && ($key === 'allow' || $key === 'disallow')) {
                $rules[$group][] = [$key === 'allow', $value];
            } elseif ($group !== null && $key === 'crawl-delay' && is_numeric($value)) {
                $delays[$group] ??= (float) $value;
            }
            $lastWasAgent = $key === 'user-agent';
        }

        $chosen = self::groupsFor($product, $agents);

        return new RobotsRules(
            array_merge(...array_map(static fn(int|string $group): array => $rules[$group] ?? [], $chosen)),
            self::firstDelay($chosen, $delays),
            $sitemaps,
        );
    }

    /**
     * Grupos com exatamente o nome do produto (sem caixa e sem versão); sem nenhum, os
     * grupos *. Vários grupos com o mesmo nome valem juntos (RFC 9309, 2.2.1).
     *
     * @param array<array-key, list<string>> $agents
     *
     * @return list<array-key>
     */
    private static function groupsFor(string $product, array $agents): array
    {
        $exact = [];
        $wildcard = [];
        foreach ($agents as $group => $names) {
            if (in_array($product, $names, true)) {
                $exact[] = $group;
            } elseif (in_array('*', $names, true)) {
                $wildcard[] = $group;
            }
        }

        return $exact !== [] ? $exact : $wildcard;
    }

    /**
     * @param list<array-key> $groups
     * @param array<array-key, float> $delays
     */
    private static function firstDelay(array $groups, array $delays): ?float
    {
        foreach ($groups as $group) {
            if (isset($delays[$group])) {
                return $delays[$group];
            }
        }

        return null;
    }
}
