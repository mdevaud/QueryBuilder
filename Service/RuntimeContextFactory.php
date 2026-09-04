<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use QueryBuilder\Query\RuntimeContext;
use QueryBuilder\Query\RuntimeParameterProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;

/**
 * Builds the RuntimeContext of the current front visit (customer, cart,
 * locale) then lets the registered providers add their project-specific
 * placeholders.
 */
final readonly class RuntimeContextFactory
{
    /** @param iterable<RuntimeParameterProviderInterface> $parameterProviders */
    public function __construct(
        private RequestStack $requestStack,
        private EventDispatcherInterface $eventDispatcher,
        #[TaggedIterator(RuntimeParameterProviderInterface::TAG)]
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

        $cartProductIds = [];
        if ($cart !== null) {
            foreach ($cart->getCartItems() as $cartItem) {
                $cartProductIds[] = (int) $cartItem->getProductId();
            }
        }

        return $this->withProviderParameters(new RuntimeContext(
            customerId: $customer?->getId(),
            productId: $productId,
            cartId: $cart?->getId(),
            cartProductIds: array_values(array_unique($cartProductIds)),
            orderId: $orderId,
            categoryId: $categoryId,
            brandId: $brandId,
            locale: $session instanceof Session ? ($session->getLang()?->getLocale() ?? 'fr_FR') : 'fr_FR',
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
            parameters: array_merge($parameters, $baseContext->parameters),
        );
    }
}
