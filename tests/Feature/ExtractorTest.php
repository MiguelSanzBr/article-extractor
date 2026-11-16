<?php

declare(strict_types=1);

use ArticleExtractor\Block;
use ArticleExtractor\Extractor;

function htmlFixture(string $name): string
{
    return (string) file_get_contents(__DIR__ . "/../Fixtures/html/{$name}.html");
}

/** @return list<Block> */
function bodyBlocks(ArticleExtractor\Article $article): array
{
    return array_values(array_filter($article->blocks, static fn (Block $b): bool => $b->type->isBody()));
}

it('extrai a matéria de cada tipo de página', function (string $fixture, string $strategy, string $title, int $bodyCount, string $first, string $last) {
    $article = Extractor::create()->extract(htmlFixture($fixture), "https://exemplo.test/{$fixture}");
    $body = bodyBlocks($article);

    expect($article->strategy)->toBe($strategy)
        ->and($article->metadata->title)->toBe($title)
        ->and($body)->toHaveCount($bodyCount)
        ->and($body[0]->text)->toStartWith($first)
        ->and($body[$bodyCount - 1]->text)->toStartWith($last);
})->with([
    'WordPress' => ['wordpress', 'priority-selector', 'Prefeitura amplia horário das bibliotecas', 5, 'As bibliotecas municipais', 'Os horários de cada unidade'],
    'Next.js' => ['nextjs', 'embedded-app', 'Startup local recebe investimento de fundo estrangeiro', 4, 'Uma startup de logística', 'Segundo os sócios'],
    'Arc XP' => ['arc-fusion', 'embedded-app', 'Câmara aprova novo plano de mobilidade urbana', 6, 'A Câmara Municipal aprovou', 'Vereadores da oposição'],
    'só JSON-LD' => ['jsonld-only', 'json-ld', 'Chuva forte provoca alagamentos na zona norte', 4, 'A chuva forte', 'A previsão é de tempo firme'],
    'portal ruidoso' => ['noisy-portal', 'density', 'Feira de ciências reúne mil estudantes', 6, 'A feira de ciências', 'A próxima edição'],
    'listas e citações' => ['lists-and-quotes', 'semantic-container', 'Cinco mudanças no calendário escolar', 6, 'A secretaria de educação', 'Associações de pais'],
]);

it('leva metadados e corpo juntos no resultado', function () {
    $article = Extractor::create()->extract(htmlFixture('wordpress'), 'https://exemplo.test/x');

    expect($article->metadata->subtitle)->toBe('Unidades passam a abrir aos domingos a partir de abril.')
        ->and($article->metadata->authors)->toBe(['Marina Costa'])
        ->and($article->metadata->siteName)->toBe('Diário do Bairro')
        ->and($article->metadata->language)->toBe('pt-BR')
        ->and($article->metadata->publishedAt?->format('c'))->toBe('2026-03-05T12:30:00+00:00')
        ->and($article->text())->not->toContain('Comentário de leitor')
        ->and($article->text())->not->toContain('Compartilhar')
        ->and($article->text())->toContain('Como vai funcionar');
});

it('reconstrói listas e citações no HTML', function () {
    $html = Extractor::create()->extract(htmlFixture('lists-and-quotes'), 'https://exemplo.test/x')->html();

    expect($html)->toContain('<ul><li>As aulas começam')
        ->and($html)->toContain('<blockquote><p>Queremos que o ano');
});

it('página de paywall com teaser não tem corpo, mas tem título', function () {
    $article = Extractor::create()->extract(htmlFixture('paywall-teaser'), 'https://exemplo.test/x');

    expect($article->strategy)->toBeNull()
        ->and($article->hasBody())->toBeFalse()
        ->and($article->metadata->title)->toBe('Os bastidores da negociação do novo contrato do metrô');
});

it('lê página em latin1 pelo meta charset ou pela codificação informada', function (bool $withMeta, ?string $encoding) {
    $meta = $withMeta ? '<meta charset="iso-8859-1">' : '';
    $utf8 = "<!doctype html><html><head>{$meta}<meta property=\"og:title\" content=\"Ação na Justiça\"></head><body><article>"
        . str_repeat('<p>A ação foi protocolada na última sexta-feira e ainda não tem data para julgamento no tribunal.</p>', 4)
        . '</article></body></html>';
    $latin1 = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');

    $article = Extractor::create()->extract($latin1, 'https://exemplo.test/x', $encoding);

    expect($article->metadata->title)->toBe('Ação na Justiça')
        ->and($article->text())->toContain('A ação foi protocolada');
})->with([
    'com meta charset' => [true, null],
    'sem meta, codificação informada' => [false, 'windows-1252'],
]);

it('trata data sem fuso no fuso informado', function () {
    $html = '<html><head><meta property="article:published_time" content="2026-03-05T10:00:00"></head><body></body></html>';

    expect(Extractor::create(new DateTimeZone('America/Sao_Paulo'))->extract($html, 'https://a.test/x')->metadata->publishedAt?->format('c'))
        ->toBe('2026-03-05T13:00:00+00:00');
});

it('aceita HTML vazio', function () {
    $article = Extractor::create()->extract('', 'https://exemplo.test/x');

    expect($article->hasBody())->toBeFalse()
        ->and($article->metadata->title)->toBeNull();
});

it('recusa URL que não é http(s)', function () {
    Extractor::create()->extract('<p>x</p>', 'ftp://a.test/x');
})->throws(InvalidArgumentException::class);
