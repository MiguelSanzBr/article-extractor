<?php

declare(strict_types=1);

namespace ArticleExtractor;

enum BlockType: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case ListItem = 'list_item';
    case Quote = 'quote';
    case Caption = 'caption';

    /**
     * Blocos que contam como texto da matéria (título de seção e legenda não contam).
     */
    public function isBody(): bool
    {
        return match ($this) {
            self::Paragraph, self::ListItem, self::Quote => true,
            self::Heading, self::Caption => false,
        };
    }
}
