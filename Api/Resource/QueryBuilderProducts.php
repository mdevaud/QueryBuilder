<?php

declare(strict_types=1);

namespace QueryBuilder\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use QueryBuilder\Api\State\QueryBuilderProductsProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\Resource\Product;

/**
 * Products selected by the rules bound to a hook code, for a decoupled front:
 * the ids and discount offers per executed action, plus the product resources
 * themselves in selection order. Same aggregation as the theme hooks and the
 * query_builder_products() Twig function (HookResultPresenter).
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/front/query_builder/products/{hookCode}',
            uriVariables: [
                'hookCode' => new Link(fromProperty: 'hookCode', identifiers: ['string']),
            ],
            requirements: ['hookCode' => '[a-zA-Z0-9_.\-]+'],
            openapi: new Operation(
                summary: 'Products selected by the QueryBuilder rules bound to a hook code',
                parameters: [
                    new Parameter(name: 'hookCode', in: 'path', required: true, schema: ['type' => 'string']),
                    new Parameter(name: 'product_id', in: 'query', required: false, schema: ['type' => 'integer'], description: 'Product being displayed (PRODUCT context rules)'),
                    new Parameter(name: 'category_id', in: 'query', required: false, schema: ['type' => 'integer'], description: 'Category being displayed (CATEGORY context rules)'),
                    new Parameter(name: 'brand_id', in: 'query', required: false, schema: ['type' => 'integer'], description: 'Brand being displayed (BRAND context rules)'),
                    new Parameter(name: 'order_id', in: 'query', required: false, schema: ['type' => 'integer'], description: 'Order being displayed (ORDER context rules)'),
                ],
            ),
            provider: QueryBuilderProductsProvider::class,
        ),
    ],
    normalizationContext: ['groups' => [self::GROUP_FRONT_READ, Product::GROUP_FRONT_READ]],
)]
class QueryBuilderProducts
{
    public const GROUP_FRONT_READ = 'front:query_builder_products:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public string $hookCode;

    /** @var int[] unique product ids selected by the display actions, in selection order */
    #[Groups([self::GROUP_FRONT_READ])]
    public array $productIds = [];

    /** @var array<int, array{rate: float, label: ?string, cumulative: bool}> discount offers keyed by product id */
    #[Groups([self::GROUP_FRONT_READ])]
    public array $offers = [];

    /** @var array<int, array{rule: ?string, action: ?string, product_ids: int[], offers: array}> one entry per executed display action */
    #[Groups([self::GROUP_FRONT_READ])]
    public array $actions = [];

    /** @var Product[] the selected products, in selection order */
    #[Groups([self::GROUP_FRONT_READ])]
    public array $products = [];
}
