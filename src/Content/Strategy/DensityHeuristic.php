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
use Dom\Text as DomText;

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
        [$elements, $text, $links] = $this->lengths(NoiseRules::cleanCopy($page->body()));

        foreach ($this->candidates($elements, $text) as $id) {
            // todo candidato tem ao menos um parágrafo de 20+ caracteres, então o texto é > 0
            if ($links[$id] / $text[$id] > self::MAX_LINK_DENSITY) {
                continue;
            }
            $blocks = BlockExtractor::fromElement($elements[$id]);
            if (Quality::accepts($blocks)) {
                return $blocks;
            }
        }

        return [];
    }

    /**
     * Tamanho do texto e do texto em link de cada elemento, somados de baixo pra cima numa
     * passada só (medir cada candidato descendo pela subárvore fica quadrático em
     * aninhamento fundo).
     *
     * @return array{array<int, Element>, array<int, int>, array<int, int>}
     */
    private function lengths(Element $root): array
    {
        $order = [];
        $text = [];
        $links = [];
        $stack = [$root];
        while ($stack !== []) {
            $element = array_pop($stack);
            $order[spl_object_id($element)] = $element;
            [$text[spl_object_id($element)], $links[spl_object_id($element)]] = [0, 0];
            for ($child = $element->lastElementChild; $child !== null; $child = $child->previousElementSibling) {
                $stack[] = $child;
            }
        }

        // de trás pra frente: cada filho já está somado quando o pai é visitado
        foreach (array_reverse($order, true) as $id => $element) {
            foreach ($element->childNodes as $node) {
                if ($node instanceof DomText) {
                    $text[$id] += Text::length((string) Text::clean($node->data));
                }
            }
            if ($element->localName === 'a') {
                $links[$id] = $text[$id];
            }
            // o clone está solto: só a raiz não tem pai
            $parent = $element->parentElement;
            if ($parent !== null) {
                $text[spl_object_id($parent)] += $text[$id];
                $links[spl_object_id($parent)] += $links[$id];
            }
        }

        return [$order, $text, $links];
    }

    /**
     * Cada parágrafo dá ponto ao pai e metade ao avô.
     *
     * @param array<int, Element> $elements
     * @param array<int, int> $text
     *
     * @return list<int> ids, do maior ponto pro menor
     */
    private function candidates(array $elements, array $text): array
    {
        $scores = [];
        foreach ($elements as $id => $element) {
            $parent = $element->parentElement;
            if ($element->localName !== 'p' || $text[$id] < self::MIN_PARAGRAPH || $parent === null) {
                continue;
            }
            $scores[spl_object_id($parent)] = ($scores[spl_object_id($parent)] ?? 0) + $text[$id];
            $grandparent = $parent->parentElement;
            if ($grandparent !== null) {
                $scores[spl_object_id($grandparent)] = ($scores[spl_object_id($grandparent)] ?? 0) + $text[$id] / 2;
            }
        }
        arsort($scores);

        return array_keys($scores);
    }
}
