<?php

declare(strict_types=1);

use ArticleExtractor\Support\Dates;

beforeEach(function () {
    $this->utc = new DateTimeZone('UTC');
    $this->sp = new DateTimeZone('America/Sao_Paulo');
});

it('converte ISO-8601 com fuso para UTC', function () {
    expect(Dates::parse('2026-03-05T10:00:00-03:00', $this->utc)?->format('c'))->toBe('2026-03-05T13:00:00+00:00');
});

it('aceita ISO com milissegundos e Z', function () {
    expect(Dates::parse('2026-03-05T10:00:00.123Z', $this->sp)?->format('c'))->toBe('2026-03-05T10:00:00+00:00');
});

it('interpreta data sem fuso no fuso assumido', function () {
    expect(Dates::parse('2026-03-05T10:00:00', $this->sp)?->format('c'))->toBe('2026-03-05T13:00:00+00:00')
        ->and(Dates::parse('2026-03-05', $this->utc)?->format('c'))->toBe('2026-03-05T00:00:00+00:00');
});

it('lê data brasileira com hora', function () {
    expect(Dates::parse('05/03/2026 10h30', $this->sp)?->format('c'))->toBe('2026-03-05T13:30:00+00:00')
        ->and(Dates::parse('05/03/2026 10:30', $this->sp)?->format('c'))->toBe('2026-03-05T13:30:00+00:00')
        ->and(Dates::parse('05/03/2026', $this->utc)?->format('c'))->toBe('2026-03-05T00:00:00+00:00')
        ->and(Dates::parse('05.03.2026', $this->utc)?->format('c'))->toBe('2026-03-05T00:00:00+00:00');
});

it('lê RFC 2822 de feed', function () {
    expect(Dates::parse('Thu, 05 Mar 2026 10:00:00 GMT', $this->utc)?->format('c'))->toBe('2026-03-05T10:00:00+00:00')
        ->and(Dates::parse('Thu, 05 Mar 2026 10:00:00 -0300', $this->utc)?->format('c'))->toBe('2026-03-05T13:00:00+00:00');
});

it('recusa data impossível, texto solto e ano fora do intervalo', function (?string $raw) {
    expect(Dates::parse($raw, $this->utc))->toBeNull();
})->with(['31/02/2026', 'ontem', '', null, '05/03/1890', '2026-13-01', '  ', 'Thu, 05 Xyz 2026 10:00:00', '05/03/2026 25h00', '2026-02-31']);

it('apara espaços em volta', function () {
    expect(Dates::parse("  2026-03-05\n", $this->utc)?->format('Y-m-d'))->toBe('2026-03-05');
});
