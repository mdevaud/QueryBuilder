<?php

declare(strict_types=1);

namespace QueryBuilder\EventListener;

use Propel\Runtime\ActiveQuery\Criteria;
use QueryBuilder\Service\SuggestionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\ProductQuery;

/**
 * Ends the display cycle of the suggestions whose product has been ordered:
 * the suggestion (and its discount offer) dies with the purchase.
 */
class OrderListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly SuggestionService $suggestionService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_PAY => ['markSuggestionsPurchased', 10],
        ];
    }

    public function markSuggestionsPurchased(OrderEvent $event): void
    {
        try {
            $order = $event->getPlacedOrder() ?? $event->getOrder();

            if (!$order instanceof Order || !$order->getCustomerId()) {
                return;
            }

            $productRefs = [];
            foreach ($order->getOrderProducts() as $orderProduct) {
                $productRefs[] = $orderProduct->getProductRef();
            }

            if ($productRefs === []) {
                return;
            }

            $productIds = ProductQuery::create()
                ->filterByRef(array_unique($productRefs), Criteria::IN)
                ->select('id')
                ->find()
                ->toArray();

            $this->suggestionService->markPurchased(
                (int) $order->getCustomerId(),
                array_map('intval', $productIds)
            );
        } catch (\Exception $exception) {
            //Never break the checkout for suggestion bookkeeping
            Tlog::getInstance()->addError('QueryBuilder: unable to mark purchased suggestions: ' . $exception->getMessage());
        }
    }
}
