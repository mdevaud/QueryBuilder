<?php

declare(strict_types=1);

namespace QueryBuilder\EventListener;

use QueryBuilder\Service\CartItemDiscountApplier;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;

/**
 * Re-applies the product rule discounts on the cart lines whenever the cart or
 * the customer changes, at priority 2: after the core wrote the catalog prices
 * (128) and the coupons (10), before the cart discount listener (1) so that a
 * cart percentage is computed on the discounted lines.
 */
final readonly class CartItemDiscountListener implements EventSubscriberInterface
{
    private const PRIORITY = 2;

    public function __construct(
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private CartItemDiscountApplier $cartItemDiscountApplier,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CART_ADDITEM => ['applyProductDiscounts', self::PRIORITY],
            TheliaEvents::CART_UPDATEITEM => ['applyProductDiscounts', self::PRIORITY],
            TheliaEvents::CART_DELETEITEM => ['applyProductDiscounts', self::PRIORITY],
            TheliaEvents::CUSTOMER_LOGIN => ['applyProductDiscounts', self::PRIORITY],
            TheliaEvents::CHANGE_DEFAULT_CURRENCY => ['applyProductDiscounts', self::PRIORITY],
        ];
    }

    public function applyProductDiscounts(Event $event): void
    {
        try {
            $cart = $event instanceof CartEvent ? $event->getCart() : $this->getSessionCart();

            if ($cart instanceof Cart) {
                $this->cartItemDiscountApplier->apply($cart);
            }
        } catch (\Throwable $throwable) {
            //Ne jamais bloquer le panier pour une remise
            Tlog::getInstance()->addError('QueryBuilder: product discounts not applied on the cart lines: ' . $throwable->getMessage());
        }
    }

    private function getSessionCart(): ?Cart
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof Session || !$session->isStarted()) {
            return null;
        }

        return $session->getSessionCart($this->eventDispatcher);
    }
}
