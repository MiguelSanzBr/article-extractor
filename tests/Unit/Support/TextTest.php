<?php

declare(strict_types=1);

use ArticleExtractor\Support\Text;

it('decodifica entidades, troca espaço Unicode e colapsa', function () {
    expect(Text::clean("  Olá&nbsp;&amp;\u{00A0}mundo \n"))->toBe('Olá & mundo');
});

it('devolve null para texto só de espaço, inclusive não quebrável', function () {
    expect(Text::clean(" \u{00A0}\u{2009} "))->toBeNull()
        ->and(Text::clean(null))->toBeNull()
        ->and(Text::clean(''))->toBeNull();
});

it('mede tamanho em caracteres, não em bytes', function () {
    expect(Text::length('ação'))->toBe(4);
});

it('decodifica entidades de aspas do HTML5', function () {
    expect(Text::clean('d&#39;água &apos;x&apos; &quot;y&quot;'))->toBe('d\'água \'x\' "y"');
});
