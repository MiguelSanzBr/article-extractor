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
        $group = -1;
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map(trim(...), explode(':', $line, 2));
            $key = strtolower($key);

            if ($key === 'sitemap' && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                $sitemaps[] = $value;
            } elseif ($key === 'user-agent') {
                // user-agent seguido de user-agent é o mesmo grupo; depois de uma regra, abre outro
                $group += $lastWasAgent ? 0 : 1;
                $agents[$group][] = strtolower($value);
            } elseif ($group >= 0 && ($key === 'allow' || $key === 'disallow')) {
                $rules[$group][] = [$key === 'allow', $value];
            } elseif ($group >= 0 && $key === 'crawl-delay' && is_numeric($value)) {
                $delays[$group] = (float) $value;
            }
            $lastWasAgent = $key === 'user-agent';
        }

        $chosen = null;
        $bestLength = -1;
        foreach ($agents as $index => $names) {
            foreach ($names as $agent) {
                $length = $agent === '*' ? 0 : (str_starts_with($product, $agent) ? strlen($agent) : -1);
                if ($length > $bestLength) {
                    [$chosen, $bestLength] = [$index, $length];
                }
            }
        }

        return new RobotsRules($chosen === null ? [] : $rules[$chosen] ?? [], $chosen === null ? null : $delays[$chosen] ?? null, $sitemaps);
    }
}
