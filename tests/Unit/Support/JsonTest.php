<?php

declare(strict_types=1);

use ArticleExtractor\Support\Json;

it('decodifica objeto e lista', function () {
    expect(Json::decode('{"a":1}'))->toBe(['a' => 1])
        ->and(Json::decode('[1,2]'))->toBe([1, 2]);
});

it('devolve null para JSON inválido ou escalar, sem lançar', function (string $raw) {
    expect(Json::decode($raw))->toBeNull();
})->with(['{a:1}', '"texto"', '42', '', 'null']);

it('acha o objeto atribuído ao marcador, ignorando chaves dentro de string', function () {
    expect(Json::objectAssignedTo('Fusion.globalContent = {"a":"}{","b":{"c":1}}; Fusion.x=1', 'Fusion.globalContent'))
        ->toBe(['a' => '}{', 'b' => ['c' => 1]]);
});

it('respeita aspas escapadas dentro de string', function () {
    expect(Json::objectAssignedTo('X={"a":"diz \"}\" ok"};', 'X'))->toBe(['a' => 'diz "}" ok']);
});

it('devolve null quando não há objeto depois do marcador', function (string $source) {
    expect(Json::objectAssignedTo($source, 'Fusion.globalContent'))->toBeNull();
})->with([
    'Fusion.globalContent = null;',
    'sem marcador',
    'Fusion.globalContent',
    'Fusion.globalContent = {"a":1',
    'Fusion.globalContent = {a:1};',
]);

it('exige o = depois do marcador e o objeto logo após os espaços', function () {
    expect(Json::objectAssignedTo('ab{"x":1}', 'Fusion'))->toBeNull()
        ->and(Json::objectAssignedTo('X{"a":1}', 'X'))->toBeNull()
        ->and(Json::objectAssignedTo('X= {"a":1}', 'X'))->toBe(['a' => 1]);
});
