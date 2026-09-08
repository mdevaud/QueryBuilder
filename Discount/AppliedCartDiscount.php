<?php

declare(strict_types=1);

namespace QueryBuilder\Discount;

/**
 * The cart-level rule discount as it applies to a given cart: the rate and label
 * of the winning ApplyCartDiscount action, the amount it represents on the
 * products total (taxes included, shipping excluded) and the free shipping flag.
 */
final readonly class AppliedCartDiscount
{
    public function __construct(
        public ?float $rate,
        public ?string $label,
        public bool $freeShipping,
        public ?float $amount,
        public ?string $currencyCode,
    ) {
    }
}
