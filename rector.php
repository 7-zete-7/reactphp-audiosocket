<?php

declare(strict_types=1);

return Rector\Config\RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withParallel()
    ->withCache(__DIR__.'/var/cache/rector')
;
