<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

/**
 * Anti-accumulation bookkeeping of the cart rule discount, per cart and per request.
 *
 * The listener adds its amount on top of the current cart.discount, which the
 * core resets to an absolute value (the coupons) before it runs. But cart events
 * may nest, so the current value may still contain the amount added by a previous
 * pass: when the column still holds exactly the total written last, that pass is
 * subtracted before re-adding; any other value means an upstream absolute write
 * already dropped it.
 */
final class CartDiscountLedger
{
    private const TOLERANCE = 0.001;

    /** @var array<int, float> rule discount amount applied by this request, by cart id */
    private array $appliedAmountByCartId = [];

    /** @var array<int, float> total discount written by this request, by cart id */
    private array $writtenTotalByCartId = [];

    /** The total to write in cart.discount, given the current column value and the rule amount. */
    public function nextTotal(int $cartId, float $currentDiscount, float $ruleAmount): float
    {
        $baseDiscount = $currentDiscount;

        if (isset($this->writtenTotalByCartId[$cartId])
            && abs($currentDiscount - $this->writtenTotalByCartId[$cartId]) < self::TOLERANCE
        ) {
            $baseDiscount = max(0.0, $currentDiscount - $this->appliedAmountByCartId[$cartId]);
        }

        $totalDiscount = round($baseDiscount + $ruleAmount, 2);

        $this->appliedAmountByCartId[$cartId] = $ruleAmount;
        $this->writtenTotalByCartId[$cartId] = $totalDiscount;

        return $totalDiscount;
    }
}
