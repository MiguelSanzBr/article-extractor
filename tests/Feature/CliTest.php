<?php

declare(strict_types=1);

use ArticleExtractor\Cli;

/** @return array{int, string, string} */
function runCli(array $argv, string $stdin): array
{
    $out = fopen('php://memory', 'w+');
    $err = fopen('php://memory', 'w+');
    $code = Cli::run($argv, $stdin, $out, $err);
    rewind($out);
    rewind($err);

    return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
}

it('imprime o artigo em JSON', function () {
    [$code, $out, $err] = runCli(['article-extract', 'https://exemplo.test/x'], htmlFixture('wordpress'));
    $json = json_decode($out, true);

    expect($code)->toBe(0)
        ->and($err)->toBe('')
        ->and($json['strategy'])->toBe('priority-selector')
        ->and($json['title'])->toBe('Prefeitura amplia horário das bibliotecas')
        ->and($out)->toContain('"url": "https://exemplo.test/x"');
});

it('respeita --encoding', function () {
    $latin1 = mb_convert_encoding('<html><head><meta property="og:title" content="Ação"></head><body></body></html>', 'ISO-8859-1', 'UTF-8');
    [$code, $out] = runCli(['article-extract', 'https://exemplo.test/x', '--encoding=windows-1252'], $latin1);

    expect($code)->toBe(0)
        ->and(json_decode($out, true)['title'])->toBe('Ação');
});

it('sai com 64 e mostra o uso sem URL ou com URL inválida', function (array $argv) {
    [$code, $out, $err] = runCli($argv, '<p>x</p>');

    expect($code)->toBe(64)
        ->and($out)->toBe('')
        ->and($err)->toContain('Uso: article-extract <url>');
})->with([[['article-extract']], [['article-extract', 'ftp://a.test/x']]]);

it('sai com 65 quando o stdin vem vazio', function () {
    [$code, , $err] = runCli(['article-extract', 'https://exemplo.test/x'], "  \n");

    expect($code)->toBe(65)
        ->and($err)->toContain('stdin');
});

it('roda pelo executável', function () {
    $process = proc_open([PHP_BINARY, __DIR__ . '/../../bin/article-extract', 'https://exemplo.test/x'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fwrite($pipes[0], htmlFixture('jsonld-only'));
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);

    expect(proc_close($process))->toBe(0)
        ->and(json_decode((string) $out, true)['strategy'])->toBe('json-ld');
});
