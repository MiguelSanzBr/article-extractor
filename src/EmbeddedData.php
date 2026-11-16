<?php

declare(strict_types=1);

namespace ArticleExtractor;

/**
 * Dados estruturados que a própria página traz, decodificados uma vez antes de qualquer
 * limpeza do DOM (limpar antes apagaria os <script> que guardam isso).
 */
final readonly class EmbeddedData
{
    /**
     * @param list<array<mixed>> $jsonLd todos os itens JSON-LD, com @graph e listas achatados
     * @param array<mixed>|null $article primeiro item JSON-LD cujo @type é de artigo
     * @param array<mixed>|null $nextPageProps props.pageProps do __NEXT_DATA__
     * @param array<mixed>|null $fusion objeto do Fusion.globalContent (Arc XP)
     */
    public function __construct(
        public array $jsonLd,
        public ?array $article,
        public ?array $nextPageProps,
        public ?array $fusion,
    ) {}
}
