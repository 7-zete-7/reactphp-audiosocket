<?php

declare(strict_types=1);

return new PhpCsFixer\Config()
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__.'/var/cache/php-cs-fixer')
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => [
            'strategy' => 'enforce',
        ],
    ])
    ->setFinder(
        new PhpCsFixer\Finder()
            ->in(__DIR__.'/examples')
            ->in(__DIR__.'/src')
            ->in(__DIR__.'/tests')
            ->append([
                __FILE__,
            ])
    )
;
