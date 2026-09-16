<?php

declare(strict_types=1);

namespace QueryBuilder\Discount;

/**
 * How an ApplyDiscount rule combines with the catalog prices of a cart line:
 *  - non-stackable rule (default): the discounted price replaces a catalog
 *    promotion only when it is better, the customer always gets the best price;
 *  - stackable rule: the rate applies on top of the catalog promotion price.
 */
final readonly class CartLineDiscountPolicy
{
    public const PRICE_TOLERANCE = 0.005;

    /** The line to write for a discounted product, null when the catalog promotion is at least as good. */
    public function discountedLine(Discount $discount, LinePrices $catalog): ?LinePrices
    {
        $base = $discount->cumulative && $catalog->inPromo() ? $catalog->promoPrice : $catalog->price;
        $discountedPrice = round($base * (1 - $discount->rate / 100), 2);

        if (!$discount->cumulative && $catalog->inPromo() && $catalog->promoPrice <= $discountedPrice + self::PRICE_TOLERANCE) {
            return null;
        }

        return new LinePrices($catalog->price, $discountedPrice, 1);
    }

    /** A promotion on the line that the catalog does not explain was written by a rule. */
    public function carriesRuleDiscount(LinePrices $line, LinePrices $catalog): bool
    {
        if (!$line->inPromo()) {
            return false;
        }

        return !$catalog->inPromo() || abs($line->promoPrice - $catalog->promoPrice) >= self::PRICE_TOLERANCE;
    }

    public function sameLine(LinePrices $left, LinePrices $right): bool
    {
        return abs($left->price - $right->price) < self::PRICE_TOLERANCE
            && abs($left->promoPrice - $right->promoPrice) < self::PRICE_TOLERANCE
            && $left->promo === $right->promo;
    }
}
