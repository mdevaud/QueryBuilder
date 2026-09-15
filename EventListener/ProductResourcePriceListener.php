<?php

declare(strict_types=1);

namespace QueryBuilder\EventListener;

use QueryBuilder\Api\FrontRead;
use QueryBuilder\Discount\LinePrices;
use QueryBuilder\Service\ProductPriceDiscountResolver;
use QueryBuilder\Service\RuntimeContextFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\ProductPrice as ProductPriceResource;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Log\Tlog;
use Thelia\Model\ProductSaleElements as ProductSaleElementsModel;

/**
 * Shows the ApplyDiscount rule discounts on the front product resources: the
 * listings, the cross-selling strips and a decoupled front read the sale
 * elements through the API, so a discounted product answers a promotion at the
 * discounted price there too. Runs after the core kept the single price row of
 * the browsed currency. Admin reads are left untouched.
 */
final readonly class ProductResourcePriceListener implements EventSubscriberInterface
{
    private const PRIORITY = -10;

    public function __construct(
        private RuntimeContextFactory $runtimeContextFactory,
        private ProductPriceDiscountResolver $productPriceDiscountResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ModelToResourceEvent::AFTER_TRANSFORM => ['applyRuleDiscount', self::PRIORITY]];
    }

    public function applyRuleDiscount(ModelToResourceEvent $event): void
    {
        $resource = $event->getResource();

        if (!$resource instanceof ProductSaleElementsResource || !FrontRead::isFrontRead($event->getContext())) {
            return;
        }

        $model = $resource->getPropelModel();
        $prices = $resource->getProductPrices();

        if (!$model instanceof ProductSaleElementsModel || $model->getProductId() === null || $prices === []) {
            return;
        }

        try {
            $productId = (int) $model->getProductId();
            $runtimeContext = $this->runtimeContextFactory->fromSession();
            $discounted = false;

            foreach ($prices as $price) {
                if (!$price instanceof ProductPriceResource) {
                    continue;
                }

                $discountedPrice = $this->productPriceDiscountResolver->resolve(
                    $runtimeContext,
                    $productId,
                    new LinePrices($price->getPrice(), $price->getPromoPrice(), $resource->getPromo() ? 1 : 0)
                );

                if ($discountedPrice === null) {
                    continue;
                }

                $price->setPromoPrice($discountedPrice->prices->promoPrice);
                $discounted = true;
            }

            if ($discounted) {
                $resource->setPromo(true);
            }
        } catch (\Throwable $throwable) {
            //Never break a product read for a discount
            Tlog::getInstance()->addError('QueryBuilder: product discount not shown on the product resource prices: ' . $throwable->getMessage());
        }
    }
}
