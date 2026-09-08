<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\AppliedCartDiscount;
use Thelia\Model\Cart;

/**
 * Resolves the ApplyCartDiscount rules for a cart and values them: the single
 * source of the rule discount amount for the cart listener, the API addon and
 * the theme hook, so the three always agree.
 */
final readonly class CartDiscountCalculator
{
    public function __construct(
        private CartDiscountResolutionService $cartDiscountResolutionService,
        private RuntimeContextFactory $runtimeContextFactory,
        private DeliveryCountryResolver $deliveryCountryResolver,
    ) {
    }

    public function resolve(Cart $cart): ?AppliedCartDiscount
    {
        $cartDiscount = $this->cartDiscountResolutionService->getCartDiscount(
            $this->runtimeContextFactory->forCart($cart)
        );

        if ($cartDiscount === null) {
            return null;
        }

        $amount = null;

        if ($cartDiscount->rate !== null) {
            $productsTotal = $cart->getTaxedAmount($this->deliveryCountryResolver->resolve($cart), false);
            $amount = round($productsTotal * $cartDiscount->rate / 100, 2);
        }

        return new AppliedCartDiscount(
            rate: $cartDiscount->rate,
            label: $cartDiscount->label,
            freeShipping: $cartDiscount->freeShipping,
            amount: $amount,
            currencyCode: $cart->getCurrency()?->getCode(),
        );
    }
}
