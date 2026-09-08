<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Support;

use QueryBuilder\Dictionary\DictionaryOverrideLocatorInterface;

final readonly class StaticDictionaryOverrideLocator implements DictionaryOverrideLocatorInterface
{
    /** @param string[] $files */
    public function __construct(
        private array $files = [],
    ) {
    }

    public function locate(): array
    {
        return $this->files;
    }
}
