<?php

declare(strict_types=1);

namespace QueryBuilder\Dictionary;

final readonly class JoinDefinition
{
    public const TYPE_INNER = 'INNER';
    public const TYPE_LEFT = 'LEFT';
    public const TYPE_RIGHT = 'RIGHT';

    public function __construct(
        public string $table,
        public string $fromTable,
        public string $fromColumn,
        public string $toColumn = 'id',
        public string $type = self::TYPE_INNER,
        //Additional ON clause, may reference runtime placeholders (ex: "product_i18n.locale = :locale")
        public ?string $extraOn = null,
        //True when the join can yield several rows per product (1-N): comparisons
        //on fields reached through it must compile to EXISTS/NOT EXISTS subqueries
        public bool $multivalued = false,
    ) {
    }
}
