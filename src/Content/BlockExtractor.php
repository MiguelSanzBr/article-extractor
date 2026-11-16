<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Support\Text;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * Transforma um container em blocos de texto. Trabalha sempre num clone: o documento de
 * quem chamou nunca muda.
 */
final class BlockExtractor
{
    private const array CANDIDATE_TAGS = ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote', 'figcaption'];

    /** ancestrais que mudam o que um bloco é */
    private const array CONTEXT_TAGS = ['a', 'li', 'blockquote', 'figcaption'];

    private const int MIN_HEADING = 3;

    private const int MIN_TEXT = 20;

    private const float LINK_SHARE = 0.8;

    private const int LINK_BLOCK_MAX_LENGTH = 200;

    /**
     * @return list<Block>
     */
    public static function fromElement(Element $container): array
    {
        $blocks = [];
        $seen = [];
        foreach (self::candidates(NoiseRules::cleanCopy($container)) as [$element, $context]) {
            $block = self::block($element, $context);
            if ($block !== null && !isset($seen[$key = mb_strtolower($block->text)])) {
                $seen[$key] = $block;
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Candidatos em ordem de documento, cada um com o que há acima dele (link, item,
     * citação, legenda). Uma passada só: perguntar closest() por nó fica quadrático em
     * página com aninhamento fundo.
     *
     * @return list<array{Element, array<string, string>}>
     */
    private static function candidates(Element $root): array
    {
        $found = [];
        $stack = [[$root, []]];
        while ($stack !== []) {
            [$element, $above] = array_pop($stack);
            if ($element !== $root && in_array($element->localName, self::CANDIDATE_TAGS, true)) {
                $found[] = [$element, $above];
            }
            $below = in_array($element->localName, self::CONTEXT_TAGS, true) ? $above + [$element->localName => $element->localName] : $above;
            for ($child = $element->lastElementChild; $child !== null; $child = $child->previousElementSibling) {
                $stack[] = [$child, $below];
            }
        }

        return $found;
    }

    /**
     * @return list<Block>
     */
    public static function fromHtmlFragment(string $html): array
    {
        $body = HTMLDocument::createFromString("<!doctype html><html><body>{$html}</body></html>", LIBXML_NOERROR)->body;

        return $body === null ? [] : self::fromElement($body);
    }

    /**
     * Corpo que vem como texto (JSON-LD, estado de app): com tag de bloco é HTML, senão
     * cada linha é um parágrafo.
     *
     * @return list<Block>
     */
    public static function fromText(string $body): array
    {
        if (preg_match('/<(p|h[1-6]|div|ul|ol|li|blockquote|br)\b/i', $body) === 1) {
            return self::fromHtmlFragment($body);
        }

        $blocks = [];
        foreach (preg_split('/\R+/u', $body) ?: [] as $line) {
            $text = Text::clean(strip_tags($line));
            if ($text !== null && Text::length($text) >= self::MIN_TEXT && !NoiseRules::isBoilerplate($text)) {
                $blocks[] = new Block(BlockType::Paragraph, $text);
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, string> $above
     */
    private static function block(Element $element, array $above): ?Block
    {
        $tag = $element->localName;
        // item, citação e legenda com <p> dentro: os <p> viram os blocos, sem repetir o texto
        if ((in_array($tag, ['li', 'blockquote', 'figcaption'], true) && $element->querySelector('p') !== null) || isset($above['a'])) {
            return null;
        }

        $type = match ($tag) {
            'p' => match (true) {
                isset($above['li']) => BlockType::ListItem,
                isset($above['blockquote']) => BlockType::Quote,
                isset($above['figcaption']) => BlockType::Caption,
                default => BlockType::Paragraph,
            },
            'li' => BlockType::ListItem,
            'blockquote' => BlockType::Quote,
            'figcaption' => BlockType::Caption,
            default => BlockType::Heading,
        };
        $text = Text::clean(self::ownText($element));
        if ($text === null
            || Text::length($text) < ($type === BlockType::Heading ? self::MIN_HEADING : self::MIN_TEXT)
            || NoiseRules::isBoilerplate($text)
            || NoiseRules::isDateStamp($text)
            || self::isLinkBlock($element, $type, $text)) {
            return null;
        }

        return new Block($type, $text);
    }

    /**
     * Texto sem as listas aninhadas: os itens delas viram blocos próprios.
     */
    private static function ownText(Element $element): string
    {
        if ($element->querySelector('ul, ol') === null) {
            return $element->textContent ?? '';
        }
        $copy = NoiseRules::cleanCopy($element);
        foreach ($copy->querySelectorAll('ul, ol') as $list) {
            $list->remove();
        }

        return $copy->textContent ?? '';
    }

    /**
     * Título de seção que é todo link, ou linha curta que é quase só link, é chamada pra
     * outra matéria.
     */
    private static function isLinkBlock(Element $element, BlockType $type, string $text): bool
    {
        $length = Text::length($text);
        if ($type !== BlockType::Heading && $length >= self::LINK_BLOCK_MAX_LENGTH) {
            return false;
        }
        $linked = 0;
        foreach (self::links($element, $type === BlockType::Heading) as $link) {
            $linked += Text::length(Text::clean($link->textContent) ?? '');
        }

        return $linked >= self::LINK_SHARE * $length;
    }

    /**
     * Título olha só os links filhos diretos (o DOM nativo do 8.4 não tem :scope nem children).
     *
     * @return iterable<Element>
     */
    private static function links(Element $element, bool $directOnly): iterable
    {
        if (!$directOnly) {
            return $element->querySelectorAll('a');
        }
        $links = [];
        for ($child = $element->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            if ($child->localName === 'a') {
                $links[] = $child;
            }
        }

        return $links;
    }
}
