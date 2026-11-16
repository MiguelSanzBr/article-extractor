<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Support\Text;
use Dom\Element;
use Dom\HTMLDocument;
use LogicException;

/**
 * Transforma um container em blocos de texto. Trabalha sempre num clone: o documento de
 * quem chamou nunca muda.
 */
final class BlockExtractor
{
    private const string CANDIDATES = 'p, h2, h3, h4, h5, h6, li, blockquote, figcaption';

    private const int MIN_HEADING = 3;

    private const int MIN_TEXT = 20;

    private const float LINK_SHARE = 0.8;

    private const int LINK_BLOCK_MAX_LENGTH = 200;

    /**
     * @return list<Block>
     */
    public static function fromElement(Element $container): array
    {
        $clone = self::copy($container);
        foreach ($clone->querySelectorAll('*') as $element) {
            if (NoiseRules::isNoise($element)) {
                $element->remove();
            }
        }

        $blocks = [];
        $seen = [];
        foreach ($clone->querySelectorAll(self::CANDIDATES) as $element) {
            $block = self::block($element);
            if ($block !== null && !isset($seen[$key = mb_strtolower($block->text)])) {
                $seen[$key] = true;
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @return list<Block>
     */
    public static function fromHtmlFragment(string $html): array
    {
        $body = HTMLDocument::createFromString("<!doctype html><html><body>{$html}</body></html>", LIBXML_NOERROR)->body;

        return $body === null ? [] : self::fromElement($body);
    }

    private static function block(Element $element): ?Block
    {
        $tag = $element->localName;
        // item, citação e legenda com <p> dentro: os <p> viram os blocos, sem repetir o texto
        if ((in_array($tag, ['li', 'blockquote', 'figcaption'], true) && $element->querySelector('p') !== null) || $element->closest('a') !== null) {
            return null;
        }

        $type = match ($tag) {
            'p' => self::paragraphType($element),
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

    private static function paragraphType(Element $paragraph): BlockType
    {
        return match (true) {
            $paragraph->closest('li') !== null => BlockType::ListItem,
            $paragraph->closest('blockquote') !== null => BlockType::Quote,
            $paragraph->closest('figcaption') !== null => BlockType::Caption,
            default => BlockType::Paragraph,
        };
    }

    /**
     * Texto do item sem as sublistas, q viram itens próprios.
     */
    private static function ownText(Element $element): string
    {
        if ($element->localName !== 'li' || $element->querySelector('ul, ol') === null) {
            return $element->textContent ?? '';
        }
        $copy = self::copy($element);
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

    private static function copy(Element $element): Element
    {
        $copy = $element->cloneNode(true);

        return $copy instanceof Element ? $copy : throw new LogicException('clone de elemento deveria ser elemento');
    }
}
