<?php

declare(strict_types=1);

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\Quality;

it('aceita corpo a partir de 300 caracteres', function () {
    expect(Quality::accepts([new Block(BlockType::Paragraph, str_repeat('a', 300))]))->toBeTrue()
        ->and(Quality::accepts([new Block(BlockType::Paragraph, str_repeat('a', 299))]))->toBeFalse()
        ->and(Quality::accepts([new Block(BlockType::ListItem, str_repeat('á', 150)), new Block(BlockType::Quote, str_repeat('b', 150))]))->toBeTrue();
});

it('não conta título de seção nem legenda como corpo', function () {
    expect(Quality::accepts([new Block(BlockType::Heading, str_repeat('a', 400)), new Block(BlockType::Caption, str_repeat('b', 400))]))->toBeFalse()
        ->and(Quality::accepts([]))->toBeFalse();
});
