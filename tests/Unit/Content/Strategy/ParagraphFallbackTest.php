<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\ParagraphFallback;

it('junta os parágrafos da página fora de ruído', function () {
    $strategy = new ParagraphFallback();
    $blocks = $strategy->extract(page('', '<div>' . paragraphs(2) . '</div><aside>' . paragraphs(3, 'Lateral') . '</aside><h2>Seção qualquer</h2><span><div>' . paragraphs(2, 'Mais') . '</div></span>'));

    expect($strategy->name())->toBe('paragraph-fallback')
        ->and(blockTypes($blocks))->toBe(['paragraph', 'paragraph', 'paragraph', 'paragraph'])
        ->and(array_is_list($blocks))->toBeTrue()
        ->and($blocks[2]->text)->toStartWith('Mais 1');
});

it('não devolve nada abaixo do piso', function () {
    expect((new ParagraphFallback())->extract(page('', '<p>Só isso de texto na página toda.</p>')))->toBe([]);
});
