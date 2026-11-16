# Changelog

## 0.1.0 - 2026-11-16

Primeira versão.

- `Extractor`: metadados em cascata (meta tags, JSON-LD, Twitter Card, Arc XP, Next.js, HTML) e corpo em seis camadas com piso de 300 caracteres, resultado com a camada vencedora, texto, HTML seguro e blocos.
- Discovery: feeds anunciados na home, caminhos comuns, `FeedParser` (RSS 1.0/2.0, Atom, sitemap, índice, sitemap de notícias), `RobotsTxt` (RFC 9309) e filtro de URL de matéria.
- CLI `article-extract`.
- Tudo linear no tamanho da página, inclusive com milhares de parágrafos soltos ou `<div>` sem fechar.
- JSON-LD com quebra de linha crua, CDATA, comentário ou `@graph` objeto é consertado; feed com `&` solto, entidade HTML ou byte inválido também.
- Só URL http(s) sai da lib.
