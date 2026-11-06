<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use QueryBuilder\Query\RuntimeContext;
use QueryBuilder\QueryBuilder;
use QueryBuilder\Service\SqlBuilder;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitleQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Product;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The compiled trees run against the shop database: negative operators keep the rows without a value, and the
 * purchases of the last three months count the completed orders only.
 *
 * Prerequisites: a shop test database (bin/test-prepare) where the module is
 * activated (module:activate QueryBuilder).
 */
final class SqlBuilderExecutionTest extends ActionIntegrationTestCase
{
    private SqlBuilder $sqlBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $module = ModuleQuery::create()->findOneByCode(QueryBuilder::getModuleCode());

        if ($module === null || (int) $module->getActivate() !== BaseModule::IS_ACTIVATED) {
            self::markTestSkipped('The QueryBuilder module is not activated in the test database.');
        }

        $this->sqlBuilder = $this->getService(SqlBuilder::class);
    }

    #[Test]
    public function aProductWithoutBrandIsKeptByADifferentBrandCondition(): void
    {
        $brand = $this->factory->brand();
        $branded = $this->product();
        $branded->setBrandId($brand->getId())->save();
        $brandless = $this->product();

        $productIds = $this->sqlBuilder->getProductIds(self::tree(
            ['field' => 'product_ref', 'operator' => 'in', 'value' => [$branded->getRef(), $brandless->getRef()]],
            ['field' => 'product_brand_id', 'operator' => '!=', 'value' => $brand->getId()],
        ), new RuntimeContext());

        self::assertSame([$brandless->getId()], array_map('intval', $productIds));
    }

    #[Test]
    public function aCustomerWithoutOrderMatchesADifferentNumberOfDaysSinceTheLastOrder(): void
    {
        $product = $this->product();

        $productIds = $this->sqlBuilder->getProductIds(self::tree(
            ['field' => 'product_ref', 'operator' => '=', 'value' => $product->getRef()],
            ['field' => 'customer_last_order_days', 'operator' => '!=', 'value' => 10],
        ), new RuntimeContext(customerId: $this->customer()->getId()));

        self::assertSame([$product->getId()], array_map('intval', $productIds));
    }

    #[Test]
    public function aCanceledOrUnpaidOrderIsNotAPurchaseOfTheLastThreeMonths(): void
    {
        $product = $this->product();
        $customer = $this->customer();

        foreach ([OrderStatus::CODE_CANCELED, OrderStatus::CODE_NOT_PAID] as $statusCode) {
            $order = $this->factory->order($customer, ['statusCode' => $statusCode]);
            $this->factory->orderProduct($order, ['productRef' => $product->getRef()]);
        }

        self::assertSame([$product->getId()], $this->notBoughtRecently($product, $customer));

        $paidOrder = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->factory->orderProduct($paidOrder, ['productRef' => $product->getRef()]);

        self::assertSame([], $this->notBoughtRecently($product, $customer));
    }

    /** @return int[] */
    private function notBoughtRecently(Product $product, Customer $customer): array
    {
        return array_map('intval', $this->sqlBuilder->getProductIds(self::tree(
            ['field' => 'product_ref', 'operator' => '=', 'value' => $product->getRef()],
            ['field' => 'customer_purchased_last_3_months', 'operator' => '=', 'value' => false],
        ), new RuntimeContext(customerId: $customer->getId())));
    }

    private function product(): Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
    }

    private function customer(): Customer
    {
        $title = CustomerTitleQuery::create()->findOne();
        self::assertNotNull($title);

        return $this->factory->customer($title);
    }

    /** @param array{field: string, operator: string, value: mixed} ...$rules */
    private static function tree(array ...$rules): array
    {
        return ['combinator' => 'and', 'rules' => $rules];
    }
}
