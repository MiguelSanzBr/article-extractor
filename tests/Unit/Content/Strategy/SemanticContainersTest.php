<?php

declare(strict_types=1);

use ArticleExtractor\Content\Strategy\SemanticContainers;

it('acha o corpo em article, main ou role=main', function (string $tag) {
    $html = $tag === 'role' ? '<div role="main">' . paragraphs(4) . '</div>' : "<{$tag}>" . paragraphs(4) . "</{$tag}>";
    $strategy = new SemanticContainers();

    expect($strategy->name())->toBe('semantic-container')
        ->and($strategy->extract(page('', $html)))->toHaveCount(4);
})->with(['article', 'main', 'role']);

it('teaser de um parágrafo em article não vence', function () {
    $body = '<article class="card"><p>' . str_repeat('Chamada curta de outra matéria. ', 4) . '</p></article><div>' . paragraphs(5) . '</div>';

    expect((new SemanticContainers())->extract(page('', $body)))->toBe([]);
});

it('lista de chamadas curtas perde para o corpo de verdade', function () {
    $teasers = '';
    for ($i = 1; $i <= 10; $i++) {
        $teasers .= "<p>Chamada curta {$i} para outra matéria.</p>";
    }
    $blocks = (new SemanticContainers())->extract(page('', "<article>{$teasers}</article><article>" . paragraphs(4) . '</article>'));

    expect($blocks)->toHaveCount(4)
        ->and($blocks[0]->text)->toStartWith('Parágrafo 1');
});

it('container cheio de títulos perde para o corpo de verdade', function () {
    $headings = '';
    for ($i = 1; $i <= 5; $i++) {
        $headings .= "<h2>Título de seção número {$i}</h2>";
    }
    $blocks = (new SemanticContainers())->extract(page('', "<article>{$headings}" . paragraphs(5, 'Lista') . '</article><article>' . paragraphs(4) . '</article>'));

    expect($blocks[0]->text)->toStartWith('Parágrafo 1');
});

function sized(int $count, int $length, string $prefix = 'p'): string
{
    $html = '';
    for ($i = 1; $i <= $count; $i++) {
        $start = "{$prefix}{$i} ";
        $html .= '<p>' . $start . str_repeat('a', $length - mb_strlen($start)) . '</p>';
    }

    return $html;
}

function winner(string $body): string
{
    return (new SemanticContainers())->extract(page('', $body))[0]->text ?? '';
}

it('o melhor container vence mesmo vindo antes', function () {
    $teasers = '';
    for ($i = 1; $i <= 10; $i++) {
        $teasers .= "<p>Chamada curta {$i} para outra matéria.</p>";
    }

    expect(winner('<article>' . paragraphs(4) . "</article><article>{$teasers}</article>"))->toStartWith('Parágrafo 1');
});

it('container recusado antes não atrapalha o seguinte', function () {
    expect(winner('<article><p>Teaser curto de outra matéria.</p></article><main>' . paragraphs(4) . '</main>'))->toStartWith('Parágrafo 1');
});

it('desconta lista de chamadas só com média abaixo de 80 caracteres', function () {
    expect(winner('<article>' . sized(5, 80, 'a') . '</article><article>' . sized(4, 90, 'b') . '</article>'))->toStartWith('a1')
        ->and(winner('<article>' . sized(5, 79, 'a') . '</article><article>' . sized(4, 90, 'b') . '</article>'))->toStartWith('b1');
});

it('não conta título de seção na média dos parágrafos', function () {
    expect(winner('<article><h2>Seção um</h2><h2>Seção dois</h2>' . sized(4, 90, 'a') . '</article><article>' . sized(4, 85, 'b') . '</article>'))->toStartWith('Seção um');
});

it('desconta página de índice a partir de 4 títulos e títulos >= parágrafos', function () {
    $headings = static fn(int $n): string => implode('', array_map(static fn(int $i): string => "<h2>Título {$i}</h2>", range(1, $n)));

    expect(winner('<article>' . $headings(4) . sized(4, 100, 'a') . '</article><article>' . sized(4, 90, 'b') . '</article>'))->toStartWith('b1')
        ->and(winner('<article>' . $headings(3) . sized(3, 110, 'a') . '</article><article>' . sized(4, 80, 'b') . '</article>'))->toStartWith('Título 1')
        ->and(winner('<article>' . $headings(5) . sized(6, 85, 'a') . '</article><article>' . sized(4, 100, 'b') . '</article>'))->toStartWith('Título 1');
});

it('container único com desconto ainda é devolvido', function () {
    $headings = implode('', array_map(static fn(int $i): string => "<h2>Título {$i}</h2>", range(1, 4)));

    expect(winner("<article>{$headings}" . sized(4, 100, 'a') . '</article>'))->toStartWith('Título 1');
});

it('no empate fica o primeiro container', function () {
    expect(winner('<article>' . sized(4, 90, 'a') . '</article><article>' . sized(4, 90, 'b') . '</article>'))->toStartWith('a1');
});

it('desconta lista de chamadas já com 4 parágrafos', function () {
    expect(winner('<article>' . sized(4, 79, 'a') . '</article><article>' . sized(3, 101, 'b') . '</article>'))->toStartWith('b1');
});

it('entre dois containers com desconto, o de mais texto vence', function () {
    $headings = implode('', array_map(static fn(int $i): string => "<h2>Título {$i}</h2>", range(1, 4)));

    expect(winner("<article>{$headings}" . sized(4, 100, 'a') . "</article><article>{$headings}" . sized(4, 80, 'b') . '</article>'))->toStartWith('Título 1')
        ->and(winner('<article>' . sized(4, 79, 'a') . '</article><article>' . sized(5, 60, 'b') . '</article>'))->toStartWith('a1');
});
