<?php

declare(strict_types=1);

namespace QueryBuilder\Discount;

/**
 * Discount carried by an ApplyDiscount action: stateless, worth as long as
 * the rule is active, for every customer matching the rule eligibility.
 */
final readonly class Discount
{
    public function __construct(
        public float $rate,
        public ?string $label,
        public bool $cumulative,
    ) {
    }

    /** Builds the discount described by an ApplyDiscount action, null when invalid. */
    public static function fromActionParameters(array $parameters): ?self
    {
        if (!isset($parameters['discount_rate']) || (float) $parameters['discount_rate'] <= 0) {
            return null;
        }

        $label = $parameters['discount_label'] ?? null;

        return new self(
            rate: (float) $parameters['discount_rate'],
            label: \is_string($label) && $label !== '' ? $label : null,
            cumulative: (bool) ($parameters['discount_cumulative'] ?? false),
        );
    }
}
