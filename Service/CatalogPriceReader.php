<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Discount\LinePrices;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElements;

/**
 * Reads the catalog prices of a sale element exactly as the core does when it
 * prices a cart line or a product page: the row of the requested currency
 * (default currency converted when missing), the customer discount applied.
 * Every surface of the module starts from this reading so the discounted price
 * is the same on the product page, in the listings and on the cart line.
 */
final readonly class CatalogPriceReader
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /** Prices of a cart line: the cart currency, the discount of the cart owner. */
    public function forCart(ProductSaleElements $productSaleElements, Cart $cart): LinePrices
    {
        return $this->read(
            $productSaleElements,
            $cart->getCurrency() ?? Currency::getDefaultCurrency(),
            self::customerDiscount($cart->getCustomer())
        );
    }

    /** Prices as the visitor sees them: the session currency, the discount of the logged-in customer. */
    public function forSession(ProductSaleElements $productSaleElements): LinePrices
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $customer = $session instanceof Session ? $session->getCustomerUser() : null;

        return $this->read(
            $productSaleElements,
            $session instanceof Session ? $session->getCurrency() : Currency::getDefaultCurrency(),
            self::customerDiscount($customer instanceof Customer ? $customer : null)
        );
    }

    private function read(ProductSaleElements $productSaleElements, Currency $currency, float $customerDiscount): LinePrices
    {
        $prices = $productSaleElements->getPricesByCurrency($currency, $customerDiscount);

        return new LinePrices(
            (float) $prices->getPrice(),
            (float) $prices->getPromoPrice(),
            (int) $productSaleElements->getPromo()
        );
    }

    private static function customerDiscount(?Customer $customer): float
    {
        //Same reading as the core: the DECIMAL column comes back as a string
        return $customer !== null && (float) $customer->getDiscount() > 0 ? (float) $customer->getDiscount() : 0.0;
    }
}
