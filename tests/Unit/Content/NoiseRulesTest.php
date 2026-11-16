<?php

declare(strict_types=1);

use ArticleExtractor\Content\NoiseRules;

function firstElement(string $html): Dom\Element
{
    $element = page('', $html)->document->body?->firstElementChild;
    assert($element instanceof Dom\Element);

    return $element;
}

it('casa classe e id por nome inteiro ou prefixo, não por pedaço', function (string $html, bool $noise) {
    expect(NoiseRules::isNoise(firstElement($html)))->toBe($noise);
})->with([
    'layout com sidebar' => ['<div class="layout has-sidebar">x</div>', false],
    'sem sidebar' => ['<div class="no-sidebar">x</div>', false],
    'widget da sidebar' => ['<div class="sidebar__widget">x</div>', true],
    'downloads' => ['<div class="downloads-list">x</div>', false],
    'id de anúncio' => ['<div id="ad-slot-3">x</div>', true],
    'commentary' => ['<div class="commentary">x</div>', false],
    'comments' => ['<section class="comments-area">x</section>', true],
    'unrelated' => ['<div class="unrelated">x</div>', false],
    'related em caixa alta' => ['<div class="Related-Posts">x</div>', true],
    'leia também' => ['<div class="box leia-tambem">x</div>', true],
    'admin bar' => ['<div class="admin-bar">x</div>', false],
    'form' => ['<form><p>x</p></form>', false],
    'corpo normal' => ['<div class="entry-content">x</div>', false],
]);

it('trata como ruído as tags de navegação, script e afins', function (string $tag) {
    expect(NoiseRules::isNoise(firstElement("<{$tag}>x</{$tag}>")))->toBeTrue();
})->with(['script', 'style', 'noscript', 'template', 'iframe', 'svg', 'nav', 'aside', 'footer', 'button', 'dialog']);

it('trata como ruído role de navegação e elemento escondido', function (string $html) {
    expect(NoiseRules::isNoise(firstElement($html)))->toBeTrue();
})->with([
    '<div role="navigation">x</div>',
    '<div role="Complementary">x</div>',
    '<div hidden>x</div>',
    '<div aria-hidden="true">x</div>',
]);

it('derruba chamada curta e mantém texto longo que fala de publicidade', function () {
    $long = str_repeat('O mercado de publicidade cresceu muito neste ano segundo a associação. ', 9);

    expect(NoiseRules::isBoilerplate('Leia também: Outra matéria'))->toBeTrue()
        ->and(NoiseRules::isBoilerplate('Continua depois da publicidade'))->toBeTrue()
        ->and(NoiseRules::isBoilerplate('SUBSCRIBE to our daily newsletter'))->toBeTrue()
        ->and(NoiseRules::isBoilerplate('O governo anunciou hoje um pacote de medidas.'))->toBeFalse()
        ->and(NoiseRules::isBoilerplate($long))->toBeFalse();
});

it('aplica a lista de chamadas só abaixo de 200 caracteres', function () {
    $justBelow = 'Leia mais ' . str_repeat('a', 189);

    expect(NoiseRules::isBoilerplate($justBelow))->toBeTrue()
        ->and(NoiseRules::isBoilerplate($justBelow . 'a'))->toBeFalse();
});

it('reconhece carimbo de data e mantém frase que só começa com data', function (string $text, bool $stamp) {
    expect(NoiseRules::isDateStamp($text))->toBe($stamp);
})->with([
    ['Publicado em 05/03/2026 10:30', true],
    ['Atualizado em 2026-03-05', true],
    ['05/03/2026 às 10h30', true],
    ['Publicado em 05/03/2026 às 10h30 - Atualizado em 05/03/2026 às 11h00', true],
    ['05/03/2026 foi o dia em que o país mudou', false],
    ['Por Ana Lima, da redação', false],
    ['05/03/2026 ' . str_repeat('x', 80), false],
]);

it('olha classe e id separados', function () {
    expect(NoiseRules::isNoise(firstElement('<div class="x" id="sidebar">x</div>')))->toBeTrue()
        ->and(NoiseRules::isNoise(firstElement('<div class="side" id="bar">x</div>')))->toBeFalse();
});

it('carimbo de data tem até 80 caracteres e até 6 palavras', function () {
    $stamp = 'Publicado em 05/03/2026 ';

    expect(NoiseRules::isDateStamp($stamp . str_repeat('-', 80 - mb_strlen($stamp))))->toBeTrue()
        ->and(NoiseRules::isDateStamp($stamp . str_repeat('-', 81 - mb_strlen($stamp))))->toBeFalse()
        ->and(NoiseRules::isDateStamp('Publicado em 05/03/2026 às 10h30 por Ana Lima'))->toBeTrue()
        ->and(NoiseRules::isDateStamp('Publicado em 05/03/2026 às 10h30 por Ana de Lima'))->toBeFalse();
});
