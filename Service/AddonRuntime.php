<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Services reachable from the API resource addons.
 *
 * The core builds an addon with `new $addonClass()` (ApiResourcePropelTransformerService),
 * outside the container, so an addon cannot receive its dependencies. This service
 * is instantiated by the container on the first request of the kernel (it listens
 * to it) and publishes itself for the addons of the module.
 */
final class AddonRuntime implements EventSubscriberInterface
{
    private static ?self $current = null;

    public function __construct(
        public readonly CartDiscountCalculator $cartDiscountCalculator,
        public readonly DiscountResolutionService $discountResolutionService,
        public readonly RuntimeContextFactory $runtimeContextFactory,
    ) {
        self::$current = $this;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function getSubscribedEvents(): array
    {
        //Early enough to exist before any API read of the request
        return [KernelEvents::REQUEST => ['onKernelRequest', 4096]];
    }

    public function onKernelRequest(): void
    {
        //Being instantiated is the whole point: the constructor published the instance
    }
}
