<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\CartLineDiscountPolicy;
use QueryBuilder\Discount\Discount;
use QueryBuilder\Discount\LinePrices;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;

/**
 * Applies the ApplyDiscount rule discounts on the cart lines: a discounted
 * product gets its line flagged as a promotion with the discounted price, which
 * the core then carries through the cart totals and onto the order lines.
 *
 * The catalog prices of every line are re-read exactly as the core does when it
 * refreshes a cart (CatalogPriceReader: sale element prices in the cart currency,
 * customer discount included), so the reference never drifts; CartLineDiscountPolicy decides how a
 * rule combines with a catalog promotion, and a line still carrying a promotion
 * the catalog does not explain goes back to the catalog prices when no rule
 * applies any more.
 */
final readonly class CartItemDiscountApplier
{
    public function __construct(
        private DiscountResolutionService $discountResolutionService,
        private RuntimeContextFactory $runtimeContextFactory,
        private CartLineDiscountPolicy $cartLineDiscountPolicy,
        private CatalogPriceReader $catalogPriceReader,
    ) {
    }

    public function apply(Cart $cart): void
    {
        $cartItems = $cart->getCartItems();

        if (\count($cartItems) === 0) {
            return;
        }

        $productIds = [];
        foreach ($cartItems as $cartItem) {
            $productIds[] = (int) $cartItem->getProductId();
        }

        $discounts = $this->discountResolutionService->getDiscounts(
            $this->runtimeContextFactory->forCart($cart),
            array_values(array_unique($productIds))
        );

        foreach ($cartItems as $cartItem) {
            $productSaleElements = $cartItem->getProductSaleElements();

            if ($productSaleElements === null) {
                continue;
            }

            $catalog = $this->catalogPriceReader->forCart($productSaleElements, $cart);
            $line = new LinePrices((float) $cartItem->getPrice(), (float) $cartItem->getPromoPrice(), (int) $cartItem->getPromo());

            $discount = $discounts[(int) $cartItem->getProductId()] ?? null;
            $target = $discount instanceof Discount
                ? $this->cartLineDiscountPolicy->discountedLine($discount, $catalog)
                : null;

            //No rule (or the catalog promotion wins): a line written by a rule goes back to the catalog
            if ($target === null && $this->cartLineDiscountPolicy->carriesRuleDiscount($line, $catalog)) {
                $target = $catalog;
            }

            if ($target === null || $this->cartLineDiscountPolicy->sameLine($line, $target)) {
                continue;
            }

            $this->writeLine($cartItem, $target);
        }
    }

    private function writeLine(CartItem $cartItem, LinePrices $target): void
    {
        //Propel decimal columns are typed string, tinyint columns int, under Thelia 3
        $cartItem
            ->setPrice((string) $target->price)
            ->setPromoPrice((string) $target->promoPrice)
            ->setPromo($target->promo)
            ->save();
    }
}
