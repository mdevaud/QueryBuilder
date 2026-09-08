<?php

declare(strict_types=1);

namespace QueryBuilder\Hook;

use QueryBuilder\QueryBuilder;
use QueryBuilder\Service\CartDiscountCalculator;
use QueryBuilder\Service\FrontTemplateRenderer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;

/**
 * Shows the cart discount granted by a rule (label, amount, free shipping) on
 * the checkout pages of the theme. The core summary carries the amount in its
 * discount total; this fragment names the rule behind it.
 *
 * Flexy calls checkout.top on every checkout page, the cart page included, and
 * cart.bottom on the cart page: the fragment renders once per request.
 */
final class CartDiscountThemeHook implements ThemeHookInterface
{
    private const HOOKS = ['checkout.top', 'cart.bottom'];

    private bool $rendered = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartDiscountCalculator $cartDiscountCalculator,
        private readonly FrontTemplateRenderer $frontTemplateRenderer,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return \in_array($hookName, self::HOOKS, true);
    }

    public function render(string $hookName, array $parameters): string
    {
        if ($this->rendered) {
            return '';
        }

        $session = $this->requestStack->getCurrentRequest()?->getSession();

        if (!$session instanceof Session || !$session->isStarted()) {
            return '';
        }

        $applied = $this->cartDiscountCalculator->resolve($session->getSessionCart($this->eventDispatcher));

        if ($applied === null || (($applied->amount === null || $applied->amount <= 0) && !$applied->freeShipping)) {
            return '';
        }

        $this->rendered = true;

        return $this->frontTemplateRenderer->render('cart-discount.html.twig', [
            'hook' => $hookName,
            'label' => $applied->label,
            'rate' => $applied->rate,
            'amount' => $applied->amount,
            'free_shipping' => $applied->freeShipping,
            'currency_code' => $applied->currencyCode,
            'locale' => $session->getLang()?->getLocale() ?? 'en_US',
            'texts' => [
                'discount' => $this->trans('Discount'),
                'rate_off' => $this->trans('%rate%% off the products total', ['%rate%' => (string) $applied->rate]),
                'free_shipping' => $this->trans('Free shipping'),
            ],
        ]);
    }

    private function trans(string $id, array $parameters = []): string
    {
        return Translator::getInstance()->trans($id, $parameters, QueryBuilder::DOMAIN_NAME);
    }
}
