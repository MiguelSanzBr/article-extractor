<?php

declare(strict_types=1);

namespace ArticleExtractor;

use InvalidArgumentException;

/**
 * curl -s <url> | article-extract <url> [--encoding=<charset>]
 */
final class Cli
{
    private const string USAGE = "Uso: article-extract <url> [--encoding=<charset>] < pagina.html\n";

    private const int EXIT_USAGE = 64;

    private const int EXIT_NO_INPUT = 65;

    /**
     * @param list<string> $argv
     * @param resource $stdout
     * @param resource $stderr
     */
    public static function run(array $argv, string $stdin, $stdout, $stderr): int
    {
        $url = null;
        $encoding = null;
        foreach (array_slice($argv, 1) as $argument) {
            if (str_starts_with($argument, '--encoding=')) {
                $encoding = substr($argument, strlen('--encoding='));
            } else {
                $url ??= $argument;
            }
        }

        if ($url === null) {
            fwrite($stderr, self::USAGE);

            return self::EXIT_USAGE;
        }
        if (trim($stdin) === '') {
            fwrite($stderr, "Nada no stdin: mande o HTML da página.\n" . self::USAGE);

            return self::EXIT_NO_INPUT;
        }

        try {
            $article = Extractor::create()->extract($stdin, $url, $encoding);
        } catch (InvalidArgumentException $error) {
            fwrite($stderr, $error->getMessage() . "\n" . self::USAGE);

            return self::EXIT_USAGE;
        }

        fwrite($stdout, json_encode($article->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

        return 0;
    }
}
