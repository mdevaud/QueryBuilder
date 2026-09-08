<?php

declare(strict_types=1);

namespace QueryBuilder\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use QueryBuilder\Api\Resource\QueryBuilderProducts;
use QueryBuilder\Service\DataDictionary;
use QueryBuilder\Service\HookResultPresenter;
use QueryBuilder\Service\RuntimeContextFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Api\Bridge\Propel\Service\ApiResourcePropelTransformerService;
use Thelia\Api\Resource\Product;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;

/**
 * Runs the display rules bound to the hook code for the current visit (session
 * customer and cart, entity ids passed in the query string) and shapes the
 * result: same payload as the Twig function, plus the product resources.
 */
final readonly class QueryBuilderProductsProvider implements ProviderInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private DataDictionary $dataDictionary,
        private HookResultPresenter $hookResultPresenter,
        private RuntimeContextFactory $runtimeContextFactory,
        private ApiResourcePropelTransformerService $apiResourcePropelTransformerService,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $hookCode = (string) ($uriVariables['hookCode'] ?? '');

        //A hook code the dictionary does not declare can bind no rule
        if ($this->dataDictionary->getContextForHook($hookCode) === null) {
            throw new NotFoundHttpException(sprintf('Unknown QueryBuilder hook code "%s".', $hookCode));
        }

        $query = $this->requestStack->getCurrentRequest()?->query;

        $runtimeContext = $this->runtimeContextFactory->fromSession(
            productId: self::optionalId($query?->get('product_id')),
            orderId: self::optionalId($query?->get('order_id')),
            categoryId: self::optionalId($query?->get('category_id')),
            brandId: self::optionalId($query?->get('brand_id')),
        );

        $result = $this->hookResultPresenter->present($hookCode, $runtimeContext);

        $resource = new QueryBuilderProducts();
        $resource->hookCode = $hookCode;
        $resource->productIds = $result['product_ids'];
        $resource->offers = $result['offers'];
        $resource->actions = $result['actions'];
        $resource->products = $this->loadProducts($result['product_ids'], $context);

        return $resource;
    }

    /**
     * @param int[] $productIds
     *
     * @return Product[] in the given order; a product gone since the selection is dropped
     */
    private function loadProducts(array $productIds, array $context): array
    {
        if ($productIds === []) {
            return [];
        }

        $modelsById = [];
        foreach (ProductQuery::create()->filterById($productIds)->find() as $productModel) {
            $modelsById[(int) $productModel->getId()] = $productModel;
        }

        $langs = LangQuery::create()->filterByActive(1)->find();
        $productContext = array_merge($context, ['groups' => [Product::GROUP_FRONT_READ]]);
        $products = [];

        foreach ($productIds as $productId) {
            if (!isset($modelsById[$productId])) {
                continue;
            }

            $products[] = $this->apiResourcePropelTransformerService->modelToResource(
                resourceClass: Product::class,
                propelModel: $modelsById[$productId],
                context: $productContext,
                langs: $langs,
            );
        }

        return $products;
    }

    private static function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }
}
