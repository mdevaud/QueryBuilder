<?php

declare(strict_types=1);

namespace QueryBuilder\Discount;

/** The three price columns of a cart line: base price, promotion price, promotion flag. */
final readonly class LinePrices
{
    public function __construct(
        public float $price,
        public float $promoPrice,
        public int $promo,
    ) {
    }

    public function inPromo(): bool
    {
        return $this->promo === 1;
    }
}
