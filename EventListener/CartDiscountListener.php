<?php

declare(strict_types=1);

namespace QueryBuilder\EventListener;

use QueryBuilder\Service\CartDiscountCalculator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Core\Event\Cart\CartCheckoutEvent;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\Event\AddressEvent;

/**
 * Applies the ApplyCartDiscount rule discounts on the core Thelia discount
 * channel (cart.discount / order.discount) — deliberately WITHOUT any coupon
 * machinery (no CouponManager, no coupon facade, no coupon session): coupons
 * remain a separate channel and both amounts simply add up in the column.
 *
 * Anti-accumulation: the listener adds its amount on top of the current
 * cart.discount (owned by whoever wrote it before us — the core resets it to
 * an absolute value at priority 10 on these same events, we run at 1; the
 * coupon consume/clear handlers do the same absolute write at priority 128
 * on COUPON_CONSUME / COUPON_CLEAR_ALL, hence our subscription there too). But
 * cart events may NEST (a listener re-dispatching CART_ADDITEM while handling
 * one), so the current value may still CONTAIN our own previous addition. The
 * listener therefore tracks, per cart, the exact total it wrote: when the
 * current value is still that total, nothing reset the column since our last
 * pass and our part is subtracted before re-adding; any other value means an
 * upstream reset (absolute write) already dropped it.
 *
 * Free shipping follows the coupon path of the core: CART_SET_POSTAGE at
 * priority 133, right before the core coupon check (132) and the core postage
 * setter (128), clearing the cart postage and stopping the propagation exactly
 * like Coupon::forceFreePostage. The legacy ORDER_SET_POSTAGE point is kept for
 * a Smarty front still going through the order session.
 */
final class CartDiscountListener implements EventSubscriberInterface
{
    /** @var array<int, float> rule discount amount applied by this request, by cart id */
    private array $appliedAmountByCartId = [];

    /** @var array<int, float> total discount written by this request, by cart id */
    private array $writtenTotalByCartId = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartDiscountCalculator $cartDiscountCalculator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CART_ADDITEM => ['updateCartDiscount', 1],
            TheliaEvents::CART_UPDATEITEM => ['updateCartDiscount', 1],
            TheliaEvents::CART_DELETEITEM => ['updateCartDiscount', 1],
            TheliaEvents::CUSTOMER_LOGIN => ['updateCartDiscount', 1],
            TheliaEvents::COUPON_CONSUME => ['updateCartDiscount', 1],
            TheliaEvents::COUPON_CLEAR_ALL => ['updateCartDiscount', 1],
            AddressEvent::POST_UPDATE => ['updateCartDiscount', 1],
            TheliaEvents::CART_SET_POSTAGE => ['removeCartPostageWhenFreeShipping', 133],
            TheliaEvents::ORDER_SET_POSTAGE => ['removeOrderPostageWhenFreeShipping', 133],
        ];
    }

    public function updateCartDiscount(Event $event): void
    {
        try {
            $session = $this->getStartedSession();

            if ($session === null) {
                return;
            }

            //Sur les événements panier le cart est porté par l'événement ; sinon
            //(login, adresse) le core vient de résoudre le panier de session à
            //prio 10, le relire ici est sans risque de boucle de restauration
            //Thelia 3: the session needs the dispatcher to restore or create the cart
            $cart = $event instanceof CartEvent
                ? $event->getCart()
                : $session->getSessionCart($this->eventDispatcher);

            if (!$cart instanceof Cart) {
                return;
            }

            $ruleDiscountAmount = $this->cartDiscountCalculator->resolve($cart)?->amount;

            if ($ruleDiscountAmount === null || $ruleDiscountAmount <= 0) {
                return;
            }

            $cartId = (int) $cart->getId();
            $currentDiscount = (float) $cart->getDiscount();

            //Notre passage précédent est-il encore dans la colonne ? (cf. docblock)
            $baseDiscount = $currentDiscount;
            if (isset($this->writtenTotalByCartId[$cartId])
                && abs($currentDiscount - $this->writtenTotalByCartId[$cartId]) < 0.001
            ) {
                $baseDiscount = max(0.0, $currentDiscount - $this->appliedAmountByCartId[$cartId]);
            }

            $totalDiscount = $baseDiscount + $ruleDiscountAmount;

            //Propel decimal columns are typed string under Thelia 3
            $cart->setDiscount((string) $totalDiscount)->save();
            $session->getOrder()?->setDiscount((string) $totalDiscount);

            $this->appliedAmountByCartId[$cartId] = $ruleDiscountAmount;
            $this->writtenTotalByCartId[$cartId] = $totalDiscount;
        } catch (\Throwable $throwable) {
            //Ne jamais bloquer le panier pour une remise
            Tlog::getInstance()->addError('QueryBuilder: cart discount not applied: ' . $throwable->getMessage());
        }
    }

    /** Thelia 3 checkout: the postage is computed on the cart (Flexy, API). */
    public function removeCartPostageWhenFreeShipping(CartCheckoutEvent $event): void
    {
        try {
            $cart = $event->getCart();

            if (!$this->cartDiscountCalculator->resolve($cart)?->freeShipping) {
                return;
            }

            //Same write as Coupon::forceFreePostage: a cleared postage is a free delivery
            $cart
                ->setPostage(null)
                ->setPostageTax(null)
                ->setPostageTaxRuleTitle(null)
                ->save();
            $event->stopPropagation();
        } catch (\Throwable $throwable) {
            //Ne jamais bloquer la commande pour des frais de port offerts
            Tlog::getInstance()->addError('QueryBuilder: free shipping rule not applied on the cart: ' . $throwable->getMessage());
        }
    }

    /** Legacy checkout: the postage is set on the order carried by the session. */
    public function removeOrderPostageWhenFreeShipping(OrderEvent $event): void
    {
        try {
            $session = $this->getStartedSession();
            $cart = $session?->getSessionCart($this->eventDispatcher);

            if (!$cart instanceof Cart || !$this->cartDiscountCalculator->resolve($cart)?->freeShipping) {
                return;
            }

            $order = $event->getOrder();
            $order->setPostage('0');
            $event->setOrder($order);
            $event->stopPropagation();
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError('QueryBuilder: free shipping rule not applied on the order: ' . $throwable->getMessage());
        }
    }

    private function getStartedSession(): ?Session
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        return $session instanceof Session && $session->isStarted() ? $session : null;
    }
}
