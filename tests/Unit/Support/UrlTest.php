<?php

declare(strict_types=1);

use ArticleExtractor\Support\Url;

it('resolve caminho relativo com ..', function () {
    expect(Url::resolve('https://a.com/x/y/page.html', '../img/f.jpg'))->toBe('https://a.com/x/img/f.jpg');
});

it('resolve URL relativa ao protocolo', function () {
    expect(Url::resolve('https://a.com/x/', '//cdn.a.com/f.jpg'))->toBe('https://cdn.a.com/f.jpg')
        ->and(Url::resolve('http://a.com/x/', '//cdn.a.com/f.jpg'))->toBe('http://cdn.a.com/f.jpg');
});

it('resolve caminho a partir da raiz, query e fragmento', function () {
    expect(Url::resolve('https://a.com/x/y?q=1', '/z/./w'))->toBe('https://a.com/z/w')
        ->and(Url::resolve('https://a.com/x/y?q=1', '?p=2'))->toBe('https://a.com/x/y?p=2')
        ->and(Url::resolve('https://a.com/x/y?q=1', '#topo'))->toBe('https://a.com/x/y?q=1#topo')
        ->and(Url::resolve('https://a.com/x/y', 'z'))->toBe('https://a.com/x/z')
        ->and(Url::resolve('https://a.com', 'z'))->toBe('https://a.com/z');
});

it('mantém URL absoluta e a porta da base', function () {
    expect(Url::resolve('https://a.com/x/', 'HTTP://b.com/f.jpg'))->toBe('HTTP://b.com/f.jpg')
        ->and(Url::resolve('http://a.com:8080/x/', 'f.jpg'))->toBe('http://a.com:8080/x/f.jpg');
});

it('descarta referência vazia, data:, javascript: e mailto:', function (string $ref) {
    expect(Url::resolve('https://a.com/x/', $ref))->toBeNull();
})->with(['', '   ', 'data:image/gif;base64,R0', 'JavaScript:void(0)', 'mailto:a@b.com']);

it('não resolve contra base que não é http(s)', function () {
    expect(Url::resolve('nada', 'f.jpg'))->toBeNull();
});

it('normaliza para comparar URLs iguais', function () {
    expect(Url::normalize('HTTPS://Www.A.com:443/Noticia/?utm_source=x&b=2&a=1#topo'))
        ->toBe('https://www.a.com/Noticia?a=1&b=2');
});

it('remove parâmetros de rastreio e porta padrão do http', function () {
    expect(Url::normalize('http://a.com:80/n?fbclid=1&gclid=2&mc_cid=3&mc_eid=4&UTM_medium=x'))
        ->toBe('http://a.com/n');
});

it('mantém a raiz com barra e porta não padrão', function () {
    expect(Url::normalize('https://a.com'))->toBe('https://a.com/')
        ->and(Url::normalize('https://a.com:8443/x/'))->toBe('https://a.com:8443/x');
});

it('recusa URL que não é http(s) com host', function (string $url) {
    expect(fn() => Url::normalize($url))->toThrow(InvalidArgumentException::class);
})->with(['ftp://a.com/x', 'https:///x', 'nada']);

it('extrai o host em minúsculas', function () {
    expect(Url::host('https://WWW.A.com/x'))->toBe('www.a.com')
        ->and(Url::host('nada'))->toBeNull();
});

it('aceita base com scheme em maiúsculas e URL com espaço em volta', function () {
    expect(Url::resolve('HTTPS://a.com/x/', 'y'))->toBe('HTTPS://a.com/x/y')
        ->and(Url::normalize('  https://a.com/x  '))->toBe('https://a.com/x');
});

it('não sobe acima da raiz e trata . e .. no fim', function () {
    expect(Url::resolve('https://a.com/a/b', '../../../x'))->toBe('https://a.com/x')
        ->and(Url::resolve('https://a.com/x/', '../z'))->toBe('https://a.com/z')
        ->and(Url::resolve('https://a.com/a/b/', '..'))->toBe('https://a.com/a/')
        ->and(Url::resolve('https://a.com/a/b/', '.'))->toBe('https://a.com/a/b/');
});

it('só tira a porta quando é a padrão do scheme', function () {
    expect(Url::normalize('http://a.com:8080/x'))->toBe('http://a.com:8080/x')
        ->and(Url::normalize('http://a.com:443/x'))->toBe('http://a.com:443/x')
        ->and(Url::normalize('https://a.com:80/x'))->toBe('https://a.com:80/x');
});

it('mantém parâmetro de query sem valor e com = no valor', function () {
    expect(Url::normalize('https://a.com/x?b=1=2&a'))->toBe('https://a.com/x?a&b=1=2');
});
