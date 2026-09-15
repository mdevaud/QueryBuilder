<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\CartLineDiscountPolicy;
use QueryBuilder\Discount\DiscountedPrice;
use QueryBuilder\Discount\LinePrices;
use QueryBuilder\Query\RuntimeContext;

/**
 * The rule discount to show on a product priced at the given catalog prices:
 * the ApplyDiscount rules are resolved for the visit, then the cart line policy
 * decides how the discount combines with a catalog promotion. Null when no rule
 * discounts the product, or when the catalog promotion is at least as good.
 */
final readonly class ProductPriceDiscountResolver
{
    public function __construct(
        private DiscountResolutionService $discountResolutionService,
        private CartLineDiscountPolicy $cartLineDiscountPolicy,
    ) {
    }

    public function resolve(RuntimeContext $runtimeContext, int $productId, LinePrices $catalog): ?DiscountedPrice
    {
        $discount = $this->discountResolutionService->getDiscount($runtimeContext, $productId);

        if ($discount === null) {
            return null;
        }

        $prices = $this->cartLineDiscountPolicy->discountedLine($discount, $catalog);

        return $prices === null ? null : new DiscountedPrice($discount, $prices);
    }
}
