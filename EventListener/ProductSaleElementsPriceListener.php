<?php

declare(strict_types=1);

namespace QueryBuilder\EventListener;

use QueryBuilder\Discount\LinePrices;
use QueryBuilder\Service\ProductPriceDiscountResolver;
use QueryBuilder\Service\RuntimeContextFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\ProductSaleElement\PseByProductEvent;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ProductSaleElementsTableMap;

/**
 * Shows the ApplyDiscount rule discounts on the product page: the core prices
 * each sale element of the product (currency, customer discount) in the price
 * virtual columns then dispatches this event, so the theme reads a promotion
 * carrying the discounted price. The change lives in memory only.
 */
final readonly class ProductSaleElementsPriceListener implements EventSubscriberInterface
{
    private const PRICE_COLUMN = 'price_PRICE';
    private const PROMO_PRICE_COLUMN = 'price_PROMO_PRICE';

    public function __construct(
        private RuntimeContextFactory $runtimeContextFactory,
        private ProductPriceDiscountResolver $productPriceDiscountResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PseByProductEvent::class => 'applyRuleDiscount'];
    }

    public function applyRuleDiscount(PseByProductEvent $event): void
    {
        $productSaleElements = $event->getProductSaleElements();

        //Another dispatcher of the event may not have priced the element
        if (!$productSaleElements->hasVirtualColumn(self::PRICE_COLUMN) || !$productSaleElements->hasVirtualColumn(self::PROMO_PRICE_COLUMN)) {
            return;
        }

        try {
            $productId = (int) $productSaleElements->getProductId();
            $catalog = new LinePrices(
                $productSaleElements->getPrice(),
                $productSaleElements->getPromoPrice(),
                (int) $productSaleElements->getPromo()
            );

            $discountedPrice = $this->productPriceDiscountResolver->resolve(
                $this->runtimeContextFactory->fromSession(productId: $productId),
                $productId,
                $catalog
            );

            if ($discountedPrice === null) {
                return;
            }

            $productSaleElements->setVirtualColumn(self::PROMO_PRICE_COLUMN, $discountedPrice->prices->promoPrice);
            $productSaleElements->setPromo(1);
            //Display only: nothing may persist the flag, and the pooled instance must not hand
            //it to the next query of the request (the cart pricing reads the catalog flag)
            $productSaleElements->resetModified();
            ProductSaleElementsTableMap::removeInstanceFromPool($productSaleElements);
        } catch (\Throwable $throwable) {
            //Never break the product page for a discount
            Tlog::getInstance()->addError('QueryBuilder: product discount not shown on the product page prices: ' . $throwable->getMessage());
        }
    }
}
