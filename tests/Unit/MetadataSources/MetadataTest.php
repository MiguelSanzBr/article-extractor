<?php

declare(strict_types=1);

use ArticleExtractor\Metadata;

it('preenche só o que está vazio, mantendo o que já tem valor', function () {
    $first = new Metadata(title: 'A', authors: ['Ana']);
    $next = new Metadata(title: 'B', subtitle: 'Sub', authors: ['Bia'], tags: ['x'], publishedAt: new DateTimeImmutable('2026-01-01'));

    $merged = $first->fillFrom($next);

    expect($merged->title)->toBe('A')
        ->and($merged->subtitle)->toBe('Sub')
        ->and($merged->authors)->toBe(['Ana'])
        ->and($merged->tags)->toBe(['x'])
        ->and($merged->publishedAt?->format('Y-m-d'))->toBe('2026-01-01');
});

it('começa vazio', function () {
    $empty = Metadata::empty();

    expect($empty->title)->toBeNull()
        ->and($empty->authors)->toBe([])
        ->and($empty->tags)->toBe([])
        ->and($empty->canonical)->toBeNull();
});

it('não troca nenhum campo que já tem valor', function () {
    $first = new Metadata('T', 'S', ['A'], new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'), 'i', 'site', 'sec', ['t'], 'pt', 'c');
    $next = new Metadata('T2', 'S2', ['B'], new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2025-01-02'), 'i2', 'site2', 'sec2', ['t2'], 'en', 'c2');

    expect($first->fillFrom($next))->toEqual($first)
        ->and(Metadata::empty()->fillFrom($next))->toEqual($next);
});
