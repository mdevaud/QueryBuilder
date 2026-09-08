<?php

declare(strict_types=1);

namespace QueryBuilder\Hook;

use QueryBuilder\Service\DataDictionary;
use QueryBuilder\Service\HookResultPresenter;
use QueryBuilder\Service\FrontTemplateRenderer;
use QueryBuilder\Service\RuntimeContextFactory;
use Thelia\Core\Hook\Theme\ThemeHookInterface;

/**
 * Answers the theme_hook() extension points of the front theme (Flexy: product.top,
 * product.details.bottom, product.bottom, cart.top, cart.bottom, home.top...) with
 * the product lists selected by the rules bound to the hook code in the dictionary.
 *
 * The theme passes the resource it displays (product, category, brand) as an array
 * read through resources(): its id feeds the runtime context of the rules.
 */
final readonly class FrontThemeHook implements ThemeHookInterface
{
    public function __construct(
        private DataDictionary $dataDictionary,
        private HookResultPresenter $hookResultPresenter,
        private RuntimeContextFactory $runtimeContextFactory,
        private FrontTemplateRenderer $frontTemplateRenderer,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return $this->dataDictionary->getContextForHook($hookName) !== null;
    }

    public function render(string $hookName, array $parameters): string
    {
        $runtimeContext = $this->runtimeContextFactory->fromSession(
            productId: self::identifier($parameters['product'] ?? null),
            categoryId: self::identifier($parameters['category'] ?? null),
            brandId: self::identifier($parameters['brand'] ?? null),
        );

        //Same aggregation as the JSON endpoint and the Twig function: the presenter
        //dedupes and caps the shared hook slot across rules. Offers are skipped, this
        //path only renders product lists
        $result = $this->hookResultPresenter->present($hookName, $runtimeContext, withOffers: false);

        $html = '';

        foreach ($result['actions'] as $action) {
            if ($action['product_ids'] === []) {
                continue;
            }

            $html .= $this->frontTemplateRenderer->render('product-list.html.twig', [
                'hook' => $hookName,
                'rule_name' => (string) $action['rule'],
                'action_name' => (string) $action['action'],
                'product_ids' => array_values(array_map('intval', $action['product_ids'])),
            ]);
        }

        return $html;
    }

    /** The theme hands over a resource array, an object or a bare id: only the id matters here. */
    private static function identifier(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (\is_string($value) && ctype_digit($value)) {
            return self::identifier((int) $value);
        }

        if (\is_array($value)) {
            return self::identifier($value['id'] ?? null);
        }

        if (\is_object($value)) {
            if (method_exists($value, 'getId')) {
                return self::identifier($value->getId());
            }

            if (property_exists($value, 'id')) {
                return self::identifier($value->id);
            }
        }

        return null;
    }
}
