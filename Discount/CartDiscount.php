<?php

declare(strict_types=1);

namespace QueryBuilder\Discount;

/**
 * Cart-level discount carried by an ApplyCartDiscount action: stateless,
 * worth as long as the rule is active, for every customer matching the rule
 * eligibility. Applied on the core Thelia discount channel (cart.discount),
 * never through the coupon machinery.
 */
final readonly class CartDiscount
{
    public function __construct(
        public ?float $rate,
        public bool $freeShipping,
        public ?string $label = null,
    ) {
    }

    /** Builds the discount described by an ApplyCartDiscount action, null when it has no effect. */
    public static function fromActionParameters(array $parameters, ?string $label = null): ?self
    {
        $rate = isset($parameters['cart_discount_rate']) ? (float) $parameters['cart_discount_rate'] : null;

        if ($rate !== null && ($rate <= 0 || $rate >= 100)) {
            $rate = null;
        }

        $freeShipping = (bool) ($parameters['cart_discount_free_shipping'] ?? false);

        if ($rate === null && !$freeShipping) {
            return null;
        }

        return new self(rate: $rate, freeShipping: $freeShipping, label: $label);
    }
}
