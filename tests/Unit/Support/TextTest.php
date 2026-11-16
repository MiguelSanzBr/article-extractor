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
