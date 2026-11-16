<?php

declare(strict_types=1);

namespace ArticleExtractor\Content;

use ArticleExtractor\Block;
use ArticleExtractor\Page;

interface ContentStrategy
{
    /** nome estável que vai no resultado, pra dizer de onde o corpo saiu */
    public function name(): string;

    /**
     * Corpo achado por esta estratégia, ou [] quando ela não acha nada que passe em
     * Quality::accepts. Nunca altera o documento da página.
     *
     * @return list<Block>
     */
    public function extract(Page $page): array;
}
