<?php

declare(strict_types=1);

namespace QueryBuilder\Twig;

use QueryBuilder\Service\HookResultPresenter;
use QueryBuilder\Service\RuntimeContextFactory;
use Thelia\Log\Tlog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig counterpart of the JSON endpoint:
 *
 *   {% set recommendations = query_builder_products('cart.recommendations', { product_id: product.id }) %}
 *
 * returns the same structure as the JSON payload: hook, product_ids (unique ids
 * selected by the display actions), offers (discount offers keyed by product id),
 * actions (one entry per executed display action). Optional parameters forwarded
 * to the runtime context: product_id, order_id, category_id, brand_id. On error the
 * lists are empty and the error logged: a suggestion block never breaks the page.
 */
final class QueryBuilderExtension extends AbstractExtension
{
    public function __construct(
        private readonly HookResultPresenter $hookResultPresenter,
        private readonly RuntimeContextFactory $runtimeContextFactory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('query_builder_products', $this->products(...)),
        ];
    }

    /**
     * @param array{product_id?: int|string|null, order_id?: int|string|null, category_id?: int|string|null, brand_id?: int|string|null} $parameters
     *
     * @return array{hook: string, product_ids: int[], offers: array, actions: array}
     */
    public function products(string $hookCode, array $parameters = []): array
    {
        try {
            if ($hookCode === '') {
                throw new \InvalidArgumentException('query_builder_products: the hook code is required.');
            }

            $runtimeContext = $this->runtimeContextFactory->fromSession(
                productId: self::optionalId($parameters['product_id'] ?? null),
                orderId: self::optionalId($parameters['order_id'] ?? null),
                categoryId: self::optionalId($parameters['category_id'] ?? null),
                brandId: self::optionalId($parameters['brand_id'] ?? null),
            );

            return $this->hookResultPresenter->present($hookCode, $runtimeContext);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error('QueryBuilder: query_builder_products failed', [
                'hook' => $hookCode,
                'error' => $throwable->getMessage(),
            ]);

            return ['hook' => $hookCode, 'product_ids' => [], 'offers' => [], 'actions' => []];
        }
    }

    private static function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
