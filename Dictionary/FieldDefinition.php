<?php

declare(strict_types=1);

namespace QueryBuilder\Dictionary;

use QueryBuilder\Enum\Context;

final readonly class FieldDefinition
{
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_BOOLEAN = 'boolean';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_DATE,
        self::TYPE_DATETIME,
        self::TYPE_BOOLEAN,
    ];

    /**
     * @param Context[] $contexts empty = available in every context
     * @param string[]|null $operators restriction of the operators allowed for the field type
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $type = self::TYPE_TEXT,
        //"table.column" — the table must be product or resolvable through the joins graph
        public ?string $column = null,
        //Self-contained SQL expression (subquery allowed), may reference runtime placeholders (:customer_id...)
        public ?string $expression = null,
        public array $contexts = [],
        public ?array $operators = null,
        //SQL returning the possible values ("value" + optional "label" columns, :locale allowed) —
        //the back-office editor then shows a select instead of a free input
        public ?string $valuesQuery = null,
    ) {
    }

    /**
     * An expression may consume the value entered in the editor through the
     * :value token (expanded to one placeholder per item) instead of being
     * compared to it — the in/notIn operator then only selects the polarity.
     */
    public function usesValuePlaceholder(): bool
    {
        return $this->expression !== null && preg_match('/:value\b/', $this->expression) === 1;
    }

    public function getTable(): ?string
    {
        if ($this->column === null || !str_contains($this->column, '.')) {
            return null;
        }

        return explode('.', $this->column, 2)[0];
    }

    public function isAvailableInContext(Context $context): bool
    {
        return $this->contexts === [] || \in_array($context, $this->contexts, true);
    }
}
