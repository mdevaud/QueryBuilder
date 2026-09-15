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

    //Editors a field is offered in: the trigger conditions of a rule, the product selection of an action
    public const USAGE_RULE = 'rule';
    public const USAGE_ACTION = 'action';

    public const USAGES = [
        self::USAGE_RULE,
        self::USAGE_ACTION,
    ];

    /**
     * @param Context[] $contexts contexts the field is offered in; GLOBAL_SCOPE (the default) means every context
     * @param string[] $usages editors the field is offered in (USAGE_* values), both by default
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
        public array $contexts = [Context::GLOBAL_SCOPE],
        public ?array $operators = null,
        //SQL returning the possible values ("value" + optional "label" columns, :locale allowed) —
        //the back-office editor then shows a select instead of a free input
        public ?string $valuesQuery = null,
        public array $usages = self::USAGES,
    ) {
    }

    /**
     * Hierarchical group of the field, read from its code before the first
     * underscore ("product_created_at" => "product", "delivery_country" => "delivery").
     */
    public function getGroup(): string
    {
        return explode('_', $this->code, 2)[0];
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

    /** A GLOBAL field is available in every context, like a GLOBAL rule listens to every hook. */
    public function isAvailableInContext(Context $context): bool
    {
        return \in_array(Context::GLOBAL_SCOPE, $this->contexts, true) || \in_array($context, $this->contexts, true);
    }

    public function isAvailableForUsage(string $usage): bool
    {
        return \in_array($usage, $this->usages, true);
    }
}
