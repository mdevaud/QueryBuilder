<?php

declare(strict_types=1);

namespace QueryBuilder\Query;

final readonly class CompiledQuery
{
    /** @param array<string, mixed> $parameters parameter name (without colon) => scalar value */
    public function __construct(
        public string $sql,
        public array $parameters,
    ) {
    }
}
