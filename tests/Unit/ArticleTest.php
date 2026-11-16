<?php

declare(strict_types=1);

use ArticleExtractor\Article;
use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Metadata;

function article(array $blocks, ?Metadata $metadata = null, ?string $strategy = 'density'): Article
{
    return new Article('https://a.com/n/1', $metadata ?? Metadata::empty(), $blocks, $strategy);
}

it('monta o texto com linha em branco entre blocos e hífen nos itens', function () {
    $text = article([
        new Block(BlockType::Paragraph, 'Primeiro.'),
        new Block(BlockType::Heading, 'Seção'),
        new Block(BlockType::ListItem, 'Item um'),
        new Block(BlockType::ListItem, 'Item dois'),
    ])->text();

    expect($text)->toBe("Primeiro.\n\nSeção\n\n- Item um\n\n- Item dois");
});

it('reconstrói HTML seguro, agrupando itens de lista', function () {
    $html = article([
        new Block(BlockType::ListItem, 'Item um'),
        new Block(BlockType::Paragraph, 'Texto com <script>alert(1)</script> & "aspas"'),
        new Block(BlockType::Heading, 'Seção'),
        new Block(BlockType::Quote, 'Citação'),
        new Block(BlockType::Caption, 'Legenda'),
        new Block(BlockType::ListItem, 'Item dois'),
    ])->html();

    expect($html)->toBe('<ul><li>Item um</li></ul>'
        . '<p>Texto com &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;aspas&quot;</p>'
        . '<h2>Seção</h2><blockquote><p>Citação</p></blockquote><figcaption>Legenda</figcaption>'
        . '<ul><li>Item dois</li></ul>');
});

it('conta palavras com acento, hífen e número', function () {
    expect(article([new Block(BlockType::Paragraph, "Olá, mundo! Guarda-chuva d'água em 2026.")])->wordCount())->toBe(5)
        ->and(article([])->wordCount())->toBe(0);
});

it('diz se achou corpo', function () {
    expect(article([new Block(BlockType::Paragraph, 'x')])->hasBody())->toBeTrue()
        ->and(article([], strategy: null)->hasBody())->toBeFalse();
});

it('exporta as chaves na ordem fixa, datas em UTC com Z', function () {
    $metadata = new Metadata(
        title: 'T',
        authors: ['Ana'],
        publishedAt: new DateTimeImmutable('2026-03-05T13:00:00+00:00'),
        tags: ['x'],
    );
    $data = article([new Block(BlockType::Paragraph, 'Um dois')], $metadata)->toArray();

    expect(array_keys($data))->toBe(['url', 'title', 'subtitle', 'authors', 'published_at', 'modified_at', 'image', 'site_name', 'section', 'tags', 'language', 'canonical', 'strategy', 'word_count', 'text', 'html', 'blocks'])
        ->and($data['published_at'])->toBe('2026-03-05T13:00:00Z')
        ->and($data['modified_at'])->toBeNull()
        ->and($data['word_count'])->toBe(2)
        ->and($data['blocks'])->toBe([['type' => 'paragraph', 'text' => 'Um dois']]);
});
