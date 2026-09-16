<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\CartLineDiscountPolicy;
use QueryBuilder\Discount\Discount;
use QueryBuilder\Discount\LinePrices;
use Thelia\Model\CartItem;

/**
 * The rule discount a cart line actually charges: the rule that wins for the
 * product of the line, provided the promotion columns of the line hold the
 * prices it yields (CartItemDiscountApplier wrote them). A line still at the
 * catalog prices, or discounted by a better catalog promotion, carries none.
 */
final readonly class CartLineDiscountResolver
{
    public function __construct(
        private RuntimeContextFactory $runtimeContextFactory,
        private CatalogPriceReader $catalogPriceReader,
        private ProductPriceDiscountResolver $productPriceDiscountResolver,
        private CartLineDiscountPolicy $cartLineDiscountPolicy,
    ) {
    }

    public function resolve(CartItem $cartItem): ?Discount
    {
        $cart = $cartItem->getCart();
        $productSaleElements = $cartItem->getProductSaleElements();

        if ($cart === null || $productSaleElements === null) {
            return null;
        }

        $discountedPrice = $this->productPriceDiscountResolver->resolve(
            $this->runtimeContextFactory->forCart($cart),
            (int) $cartItem->getProductId(),
            $this->catalogPriceReader->forCart($productSaleElements, $cart)
        );

        if ($discountedPrice === null) {
            return null;
        }

        $line = new LinePrices((float) $cartItem->getPrice(), (float) $cartItem->getPromoPrice(), (int) $cartItem->getPromo());

        return $this->cartLineDiscountPolicy->sameLine($line, $discountedPrice->prices) ? $discountedPrice->discount : null;
    }
}
