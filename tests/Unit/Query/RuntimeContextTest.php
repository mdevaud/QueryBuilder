<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\Query\RuntimeContext;

final class RuntimeContextTest extends TestCase
{
    #[Test]
    public function everyPlaceholderIsBindableAndTheCurrentProductDefaultsToZero(): void
    {
        $parameters = (new RuntimeContext(customerId: 7, cartProductIds: [1, 2], cartTotal: 42.5, deliveryCountryId: 64))->getBindableParameters();

        self::assertSame(7, $parameters['customer_id']);
        self::assertSame(0, $parameters['product_id']);
        self::assertSame([1, 2], $parameters['cart_product_ids']);
        self::assertSame(42.5, $parameters['cart_total']);
        self::assertSame(64, $parameters['delivery_country_id']);
        self::assertSame('fr_FR', $parameters['locale']);
    }

    #[Test]
    public function projectParametersAreMergedAfterTheBuiltInOnes(): void
    {
        $parameters = (new RuntimeContext(productId: 3, parameters: ['customer_typology_id' => 12, 'product_id' => 99]))->getBindableParameters();

        self::assertSame(12, $parameters['customer_typology_id']);
        self::assertSame(99, $parameters['product_id'], 'a project provider may override a built-in placeholder');
    }
}
