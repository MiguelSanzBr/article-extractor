# article-extractor

[![ci](https://github.com/MiguelSanzBr/article-extractor/actions/workflows/ci.yml/badge.svg)](https://github.com/MiguelSanzBr/article-extractor/actions/workflows/ci.yml)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4)
![cobertura 100%](https://img.shields.io/badge/cobertura-100%25-brightgreen)
![mutação 97%](https://img.shields.io/badge/muta%C3%A7%C3%A3o-97%25-brightgreen)
[![licença MIT](https://img.shields.io/badge/licen%C3%A7a-MIT-blue)](LICENSE)

Biblioteca PHP 8.4 q recebe o HTML de uma página de notícia e devolve a matéria: título, linha fina, autores, datas, imagem, tags e o corpo em blocos, sem seletor por site. Também acha e lê os feeds de um portal (RSS, Atom, sitemap, sitemap de notícias) e o `robots.txt`. Zero dependência além de `ext-dom` e `ext-mbstring`.

[English version](README.en.md)

**O problema.** Portal de notícia não tem API, e cada um monta o HTML de um jeito: WordPress, Arc XP, Next.js, tema próprio, página cheia de chamada e anúncio. Manter um seletor por site não escala quando são centenas de sites, e quebra a cada redesign. Isso nasceu de um problema real desses; esta é a versão aberta, reescrita do zero, com regras genéricas q funcionam em site q a lib nunca viu.

**O q ela garante** (cada item tem teste):

- **Diz de onde o corpo saiu.** O resultado traz `strategy` com a camada q achou o texto, pra extração ser explicável e depurável.
- **Teaser não vira matéria.** Corpo só conta com 300+ caracteres de texto: chamada de um parágrafo num `<article>`, card de capa ou página de paywall voltam sem corpo (`hasBody()` falso), com título, sem exceção.
- **Uma fonte quebrada não apaga as outras.** Um `headline` em lista ou uma data numérica no JSON-LD zera só aquele campo; o resto continua vindo das outras fontes.
- **Nenhuma etapa estraga a outra.** O JSON-LD e o estado de app (Next.js, Arc XP) são lidos antes de qualquer limpeza, e toda limpeza é feita em cópia: o documento da página nunca muda.
- **HTML de saída seguro.** `html()` é remontado a partir dos blocos com todo texto escapado, venha o corpo do DOM, do JSON-LD ou do estado de app.
- **Datas em UTC.** Data sem fuso é lida no fuso q você informar; "ontem" e 31/02 viram `null`, nunca uma data errada.
- **Namespace de feed não importa.** Atom com `xmlns:media`, sitemap do Yoast e sitemap de notícias do Google funcionam; itens saem do mais novo pro mais velho, com URL absoluta.
- **Rápida em página grande.** As seis camadas passam por uma página de 3 MB em menos de 2 s (teste no CI).

## Instalação

```bash
composer require miguelsanzbr/article-extractor
```

## Uso

```php
use ArticleExtractor\Extractor;

$article = Extractor::create(new DateTimeZone('America/Sao_Paulo'))->extract($html, $url);

$article->metadata->title;   // "Prefeitura amplia horário das bibliotecas"
$article->strategy;          // "priority-selector"
$article->text();            // corpo em texto, parágrafos separados por linha em branco
$article->toArray();         // tudo, pronto pra json_encode
```

O fuso é o q a lib assume pra data q a página publica sem fuso; o padrão é UTC. Página sem `<meta charset>` aceita a codificação como terceiro argumento: `extract($html, $url, 'windows-1252')`.

## Pela linha de comando

```bash
curl -s https://exemplo.com/noticia | vendor/bin/article-extract https://exemplo.com/noticia
```

```json
{
    "title": "Prefeitura amplia horário das bibliotecas",
    "subtitle": "Unidades passam a abrir aos domingos a partir de abril.",
    "authors": ["Marina Costa"],
    "published_at": "2026-03-05T12:30:00Z",
    "site_name": "Diário do Bairro",
    "language": "pt-BR",
    "strategy": "priority-selector",
    "word_count": 107,
    "blocks": [
        {"type": "paragraph", "text": "As bibliotecas municipais vão abrir também aos domingos a partir de abril, …"}
    ]
}
```

(saída da fixture `tests/Fixtures/html/wordpress.html`, cortada)

## Como ela decide

**Corpo:** seis camadas, na ordem. Vence a primeira q achar corpo acima do piso de 300 caracteres, depois de tirar o bloco q repete o título e o widget de "relacionadas" no meio do texto.

| # | `strategy` | Onde procura |
|---|---|---|
| 1 | `priority-selector` | classes e atributos q CMSs comuns usam pro corpo (`[itemprop=articleBody]`, `.entry-content`, `.article-body`…); fica com o candidato de mais texto |
| 2 | `semantic-container` | `article`, `main`, `[role=main]`, com desconto pra lista de chamadas e pra página de índice |
| 3 | `json-ld` | `articleBody` do JSON-LD |
| 4 | `embedded-app` | estado de app em JS: `content_elements` do Arc XP e o artigo no `__NEXT_DATA__` |
| 5 | `density` | cada parágrafo dá ponto ao pai e metade ao avô; container com muito link é descartado |
| 6 | `paragraph-fallback` | todo `<p>` da página fora de ruído |

Ruído (menu, rodapé, compartilhar, relacionadas, comentários, cookie, paywall…) é casado por classe ou id inteiros ou por prefixo: `sidebar__widget` é ruído, `has-sidebar` não é. Chamada como "leia também" só derruba bloco curto, então matéria sobre o mercado de publicidade não perde o corpo. Além de parágrafo e título de seção, entram listas, citações e legendas.

**Metadados:** cinco fontes, campo a campo, a primeira com valor vence: meta tags (Open Graph e `article:*`), JSON-LD, Twitter Card, estado de app (Arc XP, Next.js) e, por último, o HTML visível (`h1`, `<title>`, `time` dentro do artigo, breadcrumb).

## Discovery

```php
use ArticleExtractor\Discovery\{ArticleUrlFilter, FeedLinkFinder, FeedLocations, FeedParser, RobotsTxt};

$feeds = FeedLinkFinder::find($homeHtml, $homeUrl);            // feeds anunciados na home, sem o de comentários
$feeds = $feeds ?: FeedLocations::candidates($homeUrl);        // senão, caminhos comuns de feed e sitemap
$doc = FeedParser::parse($xml, $feedUrl);                      // itens do mais novo pro mais velho; num índice, $doc->sitemaps
$rules = RobotsTxt::parse($robotsTxt, 'meu-robo/1.0');         // RFC 9309: isAllowed(), crawlDelay, sitemaps
ArticleUrlFilter::accepts($url);                               // descarta tag, autor, paginação, busca e arquivo
```

A lib não faz requisição HTTP: quem baixa a página e o feed é você (ou a API q usa a lib). Assim ela fica testável sem rede e sem dependência.

## O q ela não faz

- Não roda JavaScript. Página q monta o texto no navegador funciona quando o estado vem embutido (Next.js, Arc XP); sem isso, não.
- Não baixa nada (ver acima).
- Não tem seletor por site, de propósito.

## Testes

```bash
composer install
composer test        # Pest
composer types       # PHPStan nível max, sem baseline
composer lint:check  # Pint
docker compose run --rm php composer coverage   # cobertura mínima de 100%
docker compose run --rm php composer mutate     # pest --mutate, mínimo de 97%
```

Cobertura e mutação rodam no container porque precisam do pcov. Os mesmos quatro portões rodam no CI. As páginas e feeds de teste em `tests/Fixtures` são sintéticos, escritos à mão: nenhuma matéria real está no repositório.

Por que cada decisão: [docs/decisions.md](docs/decisions.md).

## Licença

MIT
