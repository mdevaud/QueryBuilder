<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\Discount;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Currency;

/**
 * Applies the ApplyDiscount rule discounts on the cart lines: a discounted
 * product gets its line flagged as a promotion with the discounted price, which
 * the core then carries through the cart totals and onto the order lines.
 *
 * The catalog prices of every line are re-read exactly as the core does when it
 * refreshes a cart (sale element prices in the cart currency, customer discount
 * included), so the reference never drifts:
 *  - no rule discount: a line still carrying a promotion the catalog does not
 *    explain was written by this service, it goes back to the catalog prices;
 *  - non-stackable rule (default): the discounted price replaces a catalog
 *    promotion only when it is better, the customer always gets the best price;
 *  - stackable rule: the rate applies on top of the catalog promotion price.
 */
final readonly class CartItemDiscountApplier
{
    private const PRICE_TOLERANCE = 0.005;

    public function __construct(
        private DiscountResolutionService $discountResolutionService,
        private RuntimeContextFactory $runtimeContextFactory,
    ) {
    }

    public function apply(Cart $cart): void
    {
        $cartItems = $cart->getCartItems();

        if (\count($cartItems) === 0) {
            return;
        }

        $currency = $cart->getCurrency() ?? Currency::getDefaultCurrency();
        $customer = $cart->getCustomer();
        //Same reading as the core: the DECIMAL column comes back as a string
        $customerDiscount = $customer !== null && (float) $customer->getDiscount() > 0 ? (float) $customer->getDiscount() : 0.0;

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

            $catalogPrices = $productSaleElements->getPricesByCurrency($currency, $customerDiscount);
            $catalogPrice = (float) $catalogPrices->getPrice();
            $catalogPromoPrice = (float) $catalogPrices->getPromoPrice();
            $catalogPromo = (int) $productSaleElements->getPromo();

            $discount = $discounts[(int) $cartItem->getProductId()] ?? null;
            $target = $discount instanceof Discount
                ? $this->discountedLine($discount, $catalogPrice, $catalogPromoPrice, $catalogPromo)
                : null;

            if ($target === null) {
                if (!$this->carriesRuleDiscount($cartItem, $catalogPromoPrice, $catalogPromo)) {
                    continue;
                }

                //The rule no longer applies: the line goes back to the catalog
                $target = [$catalogPrice, $catalogPromoPrice, $catalogPromo];
            }

            $this->writeLine($cartItem, ...$target);
        }
    }

    /**
     * @return array{0: float, 1: float, 2: int}|null [price, promo price, promo flag], null when the catalog promotion wins
     */
    private function discountedLine(Discount $discount, float $catalogPrice, float $catalogPromoPrice, int $catalogPromo): ?array
    {
        $catalogInPromo = $catalogPromo === 1;
        $base = $discount->cumulative && $catalogInPromo ? $catalogPromoPrice : $catalogPrice;
        $discountedPrice = round($base * (1 - $discount->rate / 100), 2);

        if (!$discount->cumulative && $catalogInPromo && $catalogPromoPrice <= $discountedPrice + self::PRICE_TOLERANCE) {
            return null;
        }

        return [$catalogPrice, $discountedPrice, 1];
    }

    /** A promotion on the line that the catalog does not explain was written here. */
    private function carriesRuleDiscount(CartItem $cartItem, float $catalogPromoPrice, int $catalogPromo): bool
    {
        if ((int) $cartItem->getPromo() !== 1) {
            return false;
        }

        return $catalogPromo !== 1 || abs((float) $cartItem->getPromoPrice() - $catalogPromoPrice) >= self::PRICE_TOLERANCE;
    }

    private function writeLine(CartItem $cartItem, float $price, float $promoPrice, int $promo): void
    {
        if (abs((float) $cartItem->getPrice() - $price) < self::PRICE_TOLERANCE
            && abs((float) $cartItem->getPromoPrice() - $promoPrice) < self::PRICE_TOLERANCE
            && (int) $cartItem->getPromo() === $promo
        ) {
            return;
        }

        //Propel decimal columns are typed string, tinyint columns int, under Thelia 3
        $cartItem
            ->setPrice((string) $price)
            ->setPromoPrice((string) $promoPrice)
            ->setPromo($promo)
            ->save();
    }
}
