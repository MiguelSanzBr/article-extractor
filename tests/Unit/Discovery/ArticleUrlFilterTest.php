<?php

declare(strict_types=1);

use ArticleExtractor\Discovery\ArticleUrlFilter;

it('separa URL de matéria de página de listagem e de arquivo', function (string $url, bool $accepted) {
    expect(ArticleUrlFilter::accepts($url))->toBe($accepted);
})->with([
    ['https://a.com/economia/2026/03/05/juros-sobem-de-novo', true],
    ['https://a.com/videos/2026/03/05/entrevista-com-ministro', true],
    ['https://a.com/noticia/juros-sobem-de-novo/?utm_source=rss', true],
    ['https://a.com/tag/juros', false],
    ['https://a.com/TAG/juros-e-inflacao-no-brasil', false],
    ['https://a.com/autor/ana-lima-da-redacao-central', false],
    ['https://a.com/economia/page/2/mais-noticias-aqui', false],
    ['https://a.com/economia', false],
    ['https://a.com/economia/', false],
    ['https://a.com/documentos/relatorio-anual-2026.PDF', false],
    ['https://a.com/fotos/2026/03/05/enchente-centro.jpg', false],
    ['mailto:redacao@a.com', false],
    ['ftp://a.com/economia/2026/03/05/juros-sobem', false],
]);
