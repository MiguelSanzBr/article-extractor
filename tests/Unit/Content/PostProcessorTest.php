<?php

declare(strict_types=1);

use ArticleExtractor\Block;
use ArticleExtractor\BlockType;
use ArticleExtractor\Content\PostProcessor;
use ArticleExtractor\Metadata;

function p(string $text): Block
{
    return new Block(BlockType::Paragraph, $text);
}

function h(string $text): Block
{
    return new Block(BlockType::Heading, $text);
}

const BODY = 'Parágrafo longo do corpo da matéria, com mais de cem caracteres para contar como texto de verdade aqui.';

it('remove bloco que repete o título ou a linha fina', function () {
    $blocks = PostProcessor::process(
        [p('Prefeitura amplia horário das bibliotecas'), p(BODY), p('Unidades passam a abrir aos domingos.')],
        new Metadata(title: 'Prefeitura amplia horário das bibliotecas.', subtitle: 'Unidades passam a abrir aos domingos'),
    );

    expect($blocks)->toBe([$blocks[0]])
        ->and($blocks[0]->text)->toBe(BODY);
});

it('remove sequência de duas ou mais chamadas entre parágrafos', function () {
    $blocks = [p(BODY), h('Outra matéria'), p('Teaser curto.'), h('Mais uma'), p('Outro teaser.'), p(BODY . ' fim')];

    expect(PostProcessor::process($blocks, Metadata::empty()))->toBe([$blocks[0], $blocks[5]]);
});

it('mantém título de seção com parágrafo curto de introdução', function () {
    $blocks = [p(BODY), h('Contexto'), p('Uma introdução curta.'), p(BODY . ' fim')];

    expect(PostProcessor::process($blocks, Metadata::empty()))->toBe($blocks);
});

it('mantém a sequência quando não há parágrafo antes ou longo depois', function () {
    $atStart = [h('A'), p('Curto um.'), h('B'), p('Curto dois.'), p(BODY)];
    $noLongAfter = [p(BODY), h('A'), p('Curto um.'), h('B'), p('Curto dois.'), p('Curto final.')];

    expect(PostProcessor::process($atStart, Metadata::empty()))->toBe($atStart)
        ->and(PostProcessor::process($noLongAfter, Metadata::empty()))->toBe($noLongAfter);
});

it('só remove a partir de semelhança acima de 90%', function () {
    $meta = new Metadata(title: 'abcdefghij');

    expect(PostProcessor::process([p('abcdefghiX'), p(BODY)], $meta))->toHaveCount(2)
        ->and(PostProcessor::process([p('abcdefghij.'), p(BODY)], $meta))->toHaveCount(1);
});

it('sequência de chamadas no fim da lista fica como está', function () {
    $blocks = [p(BODY), h('A'), p('Curto um.'), h('B'), p('Curto dois.')];

    expect(PostProcessor::process($blocks, Metadata::empty()))->toBe($blocks);
});

it('chamada com texto de 200 caracteres ou mais não é teaser', function () {
    $blocks = [p(BODY), h('A'), p(str_repeat('x', 200)), h('B'), p('Curto dois.'), p(BODY . ' fim')];
    $short = [p(BODY), h('A'), p(str_repeat('x', 199)), h('B'), p('Curto dois.'), p(BODY . ' fim')];

    expect(PostProcessor::process($blocks, Metadata::empty()))->toBe($blocks)
        ->and(PostProcessor::process($short, Metadata::empty()))->toHaveCount(2);
});

it('o parágrafo depois da sequência precisa ter mais de 100 caracteres', function () {
    $exactly100 = [p(BODY), h('A'), p('Curto um.'), h('B'), p('Curto dois.'), p(str_repeat('y', 100))];
    $over100 = [p(BODY), h('A'), p('Curto um.'), h('B'), p('Curto dois.'), p(str_repeat('y', 101))];

    expect(PostProcessor::process($exactly100, Metadata::empty()))->toBe($exactly100)
        ->and(PostProcessor::process($over100, Metadata::empty()))->toHaveCount(2);
});
