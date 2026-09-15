<?php

declare(strict_types=1);

namespace QueryBuilder\Discount;

/** A rule discount as it applies to a catalog price: the discount and the prices it yields. */
final readonly class DiscountedPrice
{
    public function __construct(
        public Discount $discount,
        public LinePrices $prices,
    ) {
    }
}
