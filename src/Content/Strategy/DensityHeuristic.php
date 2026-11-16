<?php

declare(strict_types=1);

namespace ArticleExtractor\Content\Strategy;

use ArticleExtractor\Content\BlockExtractor;
use ArticleExtractor\Content\ContentStrategy;
use ArticleExtractor\Content\NoiseRules;
use ArticleExtractor\Content\Quality;
use ArticleExtractor\Page;
use ArticleExtractor\Support\Text;
use Dom\Element;

/**
 * Pra página sem marcação nenhuma: cada parágrafo dá ponto pro pai e metade pro avô, e o
 * container com mais ponto é o corpo. Pontuar o texto próprio (e não o acumulado dos
 * descendentes) evita q o wrapper mais de fora sempre vença. Linear no nº de parágrafos.
 */
final class DensityHeuristic implements ContentStrategy
{
    private const int MIN_PARAGRAPH = 20;

    private const float MAX_LINK_DENSITY = 0.25;

    public function name(): string
    {
        return 'density';
    }

    public function extract(Page $page): array
    {
        $body = $page->document->body;
        foreach ($body === null ? [] : $this->candidates(NoiseRules::cleanCopy($body)) as $candidate) {
            if ($this->linkDensity($candidate) > self::MAX_LINK_DENSITY) {
                continue;
            }
            $blocks = BlockExtractor::fromElement($candidate);
            if (Quality::accepts($blocks)) {
                return $blocks;
            }
        }

        return [];
    }

    /**
     * @return list<Element> do maior ponto pro menor
     */
    private function candidates(Element $root): array
    {
        $elements = [];
        $scores = [];
        foreach ($root->querySelectorAll('p') as $paragraph) {
            $length = Text::length((string) Text::clean($paragraph->textContent));
            $parent = $paragraph->parentElement;
            if ($length < self::MIN_PARAGRAPH || $parent === null) {
                continue;
            }
            foreach ([[$parent, $length], [$parent->parentElement, $length / 2]] as [$element, $points]) {
                if ($element !== null) {
                    $id = spl_object_id($element);
                    $elements[$id] = $element;
                    $scores[$id] = ($scores[$id] ?? 0) + $points;
                }
            }
        }
        arsort($scores);

        return array_map(static fn(int $id): Element => $elements[$id], array_keys($scores));
    }

    private function linkDensity(Element $element): float
    {
        $text = Text::length((string) Text::clean($element->textContent));
        $linked = 0;
        foreach ($element->querySelectorAll('a') as $link) {
            $linked += Text::length((string) Text::clean($link->textContent));
        }

        // todo candidato tem ao menos um parágrafo de 20+ caracteres, então $text > 0
        return $linked / $text;
    }
}
