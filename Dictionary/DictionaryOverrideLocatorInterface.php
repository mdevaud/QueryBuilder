<?php

declare(strict_types=1);

namespace QueryBuilder\Dictionary;

/**
 * Lists the dictionary files merged over the base one, in merge order (the last
 * file wins on a label). The shop implementation reads the active modules; the
 * tests hand over their own files.
 */
interface DictionaryOverrideLocatorInterface
{
    /** @return string[] absolute paths of the override files */
    public function locate(): array;
}
