<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\DensityHeuristic;

it('acha o corpo num div sem classe conhecida', function () {
    $strategy = new DensityHeuristic();
    $body = '<article class="card"><p>Teaser curto de outra matéria aqui.</p></article><div class="x9">' . paragraphs(5) . '</div>';

    expect($strategy->name())->toBe('density')
        ->and($strategy->extract(page('', $body)))->toHaveCount(5);
});

it('escolhe o container do corpo e deixa a coluna de links de fora', function () {
    $links = str_repeat('<p><a href="/x">Outra matéria com título bem comprido para chamar atenção</a></p>', 8);
    $blocks = (new DensityHeuristic())->extract(page('', '<div id="wrap"><div class="col-a">' . paragraphs(4) . "</div><div class=\"col-b\">{$links}</div></div>"));

    expect($blocks)->toHaveCount(4)
        ->and(implode(' ', array_map(static fn($b) => $b->text, $blocks)))->not->toContain('Outra matéria');
});

it('soma no avô os parágrafos que vêm cada um num wrapper', function () {
    $wrapped = '';
    for ($i = 1; $i <= 6; $i++) {
        $wrapped .= "<div class=\"paragraph\"><p>Trecho {$i} da matéria com bastante texto para contar no corpo da página aqui.</p></div>";
    }

    expect((new DensityHeuristic())->extract(page('', "<div class=\"corpo\">{$wrapped}</div>")))->toHaveCount(6);
});

it('não devolve nada quando nenhum candidato passa no piso', function () {
    expect((new DensityHeuristic())->extract(page('', '<div><p>Curto.</p><p>Pouco texto aqui mesmo.</p></div>')))->toBe([]);
});

function linkedParagraphs(int $count, int $plain, int $linked): string
{
    $html = '';
    for ($i = 1; $i <= $count; $i++) {
        $html .= '<p>' . str_repeat('t', $plain - 1) . $i . '<a href="/x">' . str_repeat('l', $linked) . '</a></p>';
    }

    return $html;
}

it('aceita container com exatamente 25% do texto em link', function () {
    expect((new DensityHeuristic())->extract(page('', '<div>' . linkedParagraphs(4, 75, 25) . '</div>')))->toHaveCount(4);
});

it('recusa bloco de chamadas longas que são quase só link', function () {
    $related = linkedParagraphs(4, 30, 200);

    $blocks = (new DensityHeuristic())->extract(page('', "<div class=\"col\">{$related}</div><div class=\"txt\">" . paragraphs(4) . '</div>'));

    expect($blocks[0]->text ?? '')->toStartWith('Parágrafo 1');
});

it('link de ícone sem texto não conta como link', function () {
    $icons = str_repeat('<a href="/s"></a>', 20);

    expect((new DensityHeuristic())->extract(page('', "<div>{$icons}" . paragraphs(4) . '</div>')))->toHaveCount(4);
});

it('parágrafo curto no começo não interrompe a busca', function () {
    expect((new DensityHeuristic())->extract(page('', '<p>Curto.</p><div>' . paragraphs(4) . '</div>')))->toHaveCount(4);
});

it('soma todos os parágrafos do container', function () {
    $single = '<div class="b"><p>' . str_repeat('Um parágrafo só, longo. ', 13) . '</p></div>';

    expect((new DensityHeuristic())->extract(page('', $single . '<div class="a">' . paragraphs(6) . '</div>')))->toHaveCount(6);
});

it('conta parágrafo de exatamente 20 caracteres', function () {
    $twenty = implode('', array_map(static fn(int $i): string => '<p>' . str_pad("p{$i}", 20, 'x') . '</p>', range(1, 16)));

    expect((new DensityHeuristic())->extract(page('', "<div>{$twenty}</div>")))->toHaveCount(16);
});

it('parágrafo curto não dá ponto ao container', function () {
    $short = str_repeat('<p>Curtinho aqui.</p>', 40);
    $long = '<p>' . str_repeat('Texto longo de um bloco com chamadas curtas. ', 4) . '</p><p>' . str_repeat('Outro trecho longo do mesmo bloco aqui. ', 4) . '</p>';

    expect((new DensityHeuristic())->extract(page('', "<div class=\"x\">{$short}{$long}</div><div class=\"y\">" . paragraphs(4) . '</div>'))[0]->text)
        ->toStartWith('Parágrafo 1');
});

it('parágrafo vazio não quebra a pontuação', function () {
    expect((new DensityHeuristic())->extract(page('', '<div><p></p><p>   </p>' . paragraphs(4) . '</div>')))->toHaveCount(4);
});

it('ordena os candidatos pelo ponto, não pela posição', function () {
    $single = '<div class="b"><p>' . str_repeat('Um parágrafo só, longo. ', 13) . '</p></div>';

    expect((new DensityHeuristic())->extract(page('', '<div class="a">' . paragraphs(6) . "</div>{$single}")))->toHaveCount(6);
});

it('soma no avô cada parágrafo embrulhado, metade do tamanho', function () {
    $wrapped = '';
    for ($i = 1; $i <= 8; $i++) {
        $wrapped .= '<div class="w"><p>' . str_pad("Trecho {$i}", 100, ' do corpo') . '</p></div>';
    }
    $other = '<div class="z">' . implode('', array_map(static fn(int $i): string => '<p>' . str_pad("Outro {$i}", 80, ' texto') . '</p>', range(1, 4))) . '</div>';

    expect((new DensityHeuristic())->extract(page('', "<div class=\"corpo\">{$wrapped}</div>{$other}"))[0]->text ?? '')->toStartWith('Trecho 1');
});

it('link aninhado dentro de negrito também conta como link', function () {
    $related = implode('', array_map(static fn(int $i): string => '<p>' . str_repeat('t', 29) . $i . '<strong><a href="/x">' . str_repeat('l', 200) . '</a></strong></p>', range(1, 4)));

    expect((new DensityHeuristic())->extract(page('', "<div class=\"col\">{$related}</div><div class=\"txt\">" . paragraphs(4) . '</div>'))[0]->text ?? '')
        ->toStartWith('Parágrafo 1');
});
