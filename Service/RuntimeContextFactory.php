<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Query\RuntimeContext;
use QueryBuilder\Query\RuntimeParameterProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Lang;

/**
 * Builds the RuntimeContext of the current front visit (customer, cart,
 * locale, cart total, delivery country) then lets the registered providers add
 * their project-specific placeholders.
 */
final readonly class RuntimeContextFactory
{
    /** @param iterable<RuntimeParameterProviderInterface> $parameterProviders */
    public function __construct(
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        private DeliveryCountryResolver $deliveryCountryResolver,
        #[AutowireIterator(RuntimeParameterProviderInterface::TAG)]
        private iterable $parameterProviders = [],
    ) {
    }

    public function fromSession(
        ?int $productId = null,
        ?int $orderId = null,
        ?int $categoryId = null,
        ?int $brandId = null,
    ): RuntimeContext {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $customer = $session instanceof Session ? $session->getCustomerUser() : null;
        $cart = $session instanceof Session ? $session->getSessionCart($this->eventDispatcher) : null;
        $country = $this->deliveryCountryResolver->resolve($cart);

        return $this->withProviderParameters(new RuntimeContext(
            customerId: $customer?->getId(),
            productId: $productId,
            cartId: $cart?->getId(),
            cartProductIds: $cart !== null ? $this->cartProductIds($cart) : [],
            orderId: $orderId,
            categoryId: $categoryId,
            brandId: $brandId,
            locale: $session instanceof Session ? ($session->getLang()?->getLocale() ?? 'fr_FR') : 'fr_FR',
            cartTotal: $cart?->getTaxedAmount($country, false),
            deliveryCountryId: (int) $country->getId(),
        ));
    }

    /**
     * Context of a given cart (cart events, API cart reads): the customer is the
     * cart owner, not the session user, so a listener stays right when the two differ.
     */
    public function forCart(Cart $cart): RuntimeContext
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $locale = $session instanceof Session ? $session->getLang()?->getLocale() : null;
        $country = $this->deliveryCountryResolver->resolve($cart);

        return $this->withProviderParameters(new RuntimeContext(
            customerId: $cart->getCustomerId() !== null ? (int) $cart->getCustomerId() : null,
            cartId: $cart->getId() !== null ? (int) $cart->getId() : null,
            cartProductIds: $this->cartProductIds($cart),
            locale: $locale ?? Lang::getDefaultLanguage()->getLocale(),
            cartTotal: $cart->getTaxedAmount($country, false),
            deliveryCountryId: (int) $country->getId(),
        ));
    }

    /** Applies the registered project providers to an already built context. */
    public function withProviderParameters(RuntimeContext $baseContext): RuntimeContext
    {
        $parameters = [];
        foreach ($this->parameterProviders as $parameterProvider) {
            $parameters = array_merge($parameters, $parameterProvider->provide($baseContext));
        }

        if ($parameters === []) {
            return $baseContext;
        }

        return new RuntimeContext(
            customerId: $baseContext->customerId,
            productId: $baseContext->productId,
            cartId: $baseContext->cartId,
            cartProductIds: $baseContext->cartProductIds,
            orderId: $baseContext->orderId,
            categoryId: $baseContext->categoryId,
            brandId: $baseContext->brandId,
            locale: $baseContext->locale,
            cartTotal: $baseContext->cartTotal,
            deliveryCountryId: $baseContext->deliveryCountryId,
            parameters: array_merge($parameters, $baseContext->parameters),
        );
    }

    /** @return int[] */
    private function cartProductIds(Cart $cart): array
    {
        $cartProductIds = [];
        foreach ($cart->getCartItems() as $cartItem) {
            $cartProductIds[] = (int) $cartItem->getProductId();
        }

        return array_values(array_unique($cartProductIds));
    }
}
