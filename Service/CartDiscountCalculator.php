<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\AppliedCartDiscount;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;

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
        private RequestStack $requestStack,
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
            $productsTotal = $cart->getTaxedAmount($this->resolveDeliveryCountry($cart), false);
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

    /**
     * Taxes follow the delivery country: the cart's own delivery address first,
     * then the address picked in the legacy order session, then the shop default.
     */
    private function resolveDeliveryCountry(Cart $cart): Country
    {
        $country = $cart->getCartAddressRelatedByAddressDeliveryId()?->getCountry();

        if ($country !== null) {
            return $country;
        }

        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $deliveryAddressId = $session instanceof Session && $session->isStarted()
            ? $session->getOrder()?->getChoosenDeliveryAddress()
            : null;

        if ($deliveryAddressId) {
            $country = AddressQuery::create()->findPk($deliveryAddressId)?->getCountry();

            if ($country !== null) {
                return $country;
            }
        }

        return Country::getDefaultCountry();
    }
}
