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

it('acha parecido o título com pontuação e caixa diferentes', function () {
    expect(Text::similarity('Título da Matéria', 'título da matéria.'))->toBeGreaterThan(0.9);
});

it('dá 1 para textos iguais com acento', function () {
    expect(Text::similarity('ação', 'ação'))->toBe(1.0);
});

it('dá quase zero para textos sem nada em comum', function () {
    expect(Text::similarity('abc', 'xyz'))->toBeLessThan(0.1);
});

it('dá zero quando um dos lados é vazio', function () {
    expect(Text::similarity('', 'abc'))->toBe(0.0)
        ->and(Text::similarity('', ''))->toBe(0.0);
});

it('não infla a semelhança de texto acentuado medindo bytes', function () {
    // 'ãããã' e 'aaaa' não têm caractere em comum
    expect(Text::similarity('ãããã', 'aaaa'))->toBe(0.0);
});

it('mede semelhança mesmo com mais de 256 caracteres distintos', function () {
    $chars = implode('', array_map(mb_chr(...), range(0x4E00, 0x4E00 + 299)));

    expect(Text::similarity($chars, $chars))->toBe(1.0)
        ->and(Text::similarity($chars, mb_substr($chars, 0, 150)))->toBe(0.5);
});

it('decodifica entidades de aspas do HTML5', function () {
    expect(Text::clean('d&#39;água &apos;x&apos; &quot;y&quot;'))->toBe('d\'água \'x\' "y"');
});

it('calcula a distância de edição exata', function () {
    expect(Text::similarity('kitten', 'sitting'))->toBe(1 - 3 / 7)
        ->and(Text::similarity('abc', 'abd'))->toBe(1 - 1 / 3)
        ->and(Text::similarity('abc', 'ab'))->toBe(1 - 1 / 3)
        ->and(Text::similarity('ab', 'ba'))->toBe(0.0);
});

it('dá zero quando só o segundo texto é vazio', function () {
    expect(Text::similarity('abc', ''))->toBe(0.0);
});
