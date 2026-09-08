<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\Enum\Context;
use QueryBuilder\Tests\Support\DictionaryFactory;

final class DataDictionaryTest extends TestCase
{
    protected function tearDown(): void
    {
        DictionaryFactory::cleanUp();
    }

    #[Test]
    public function theBaseDictionaryDeclaresTheFlexyHooksPerContext(): void
    {
        $dictionary = DictionaryFactory::base();

        self::assertSame(['product.top', 'product.details.bottom', 'product.bottom'], array_keys($dictionary->getHooks(Context::PRODUCT)));
        self::assertSame(['cart.top', 'cart.bottom'], array_keys($dictionary->getHooks(Context::CART)));
        self::assertSame(Context::CUSTOMER, $dictionary->getContextForHook('account.bottom'));
        self::assertNull($dictionary->getContextForHook('unknown.hook'));
    }

    #[Test]
    public function aGlobalRuleSeesEveryDeclaredHook(): void
    {
        $globalHooks = DictionaryFactory::base()->getHooks(Context::GLOBAL_SCOPE);

        foreach (['home.top', 'category.top', 'brand.bottom', 'product.top', 'cart.bottom', 'account.top'] as $hookCode) {
            self::assertArrayHasKey($hookCode, $globalHooks);
        }
    }

    #[Test]
    public function anOverrideMergesHooksByCodeAndTheLastLabelWins(): void
    {
        $dictionary = DictionaryFactory::withOverrides(
            <<<YAML
            contexts:
                PRODUCT:
                    - code: product.top
                      label: "Project label"
                CART:
                    - cart.recommendations
            YAML,
        );

        $productHooks = $dictionary->getHooks(Context::PRODUCT);
        self::assertSame('Project label', $productHooks['product.top']);
        self::assertSame(['product.top', 'product.details.bottom', 'product.bottom'], array_keys($productHooks), 'the base hooks stay, in order');
        self::assertSame('cart.recommendations', $dictionary->getHooks(Context::CART)['cart.recommendations'], 'a bare code is its own label');
        self::assertSame(Context::CART, $dictionary->getContextForHook('cart.recommendations'));
    }

    #[Test]
    public function anOverrideCanDisableABaseFieldAndAddItsOwn(): void
    {
        $dictionary = DictionaryFactory::withOverrides(
            <<<YAML
            fields:
                erp_family:
                    label: "ERP family"
                    field: product.ref
                    contexts: [PRODUCT]
            disabled: [product_visible]
            YAML,
        );

        self::assertNull($dictionary->getField('product_visible'));
        self::assertNotNull($dictionary->getField('erp_family'));
        self::assertArrayHasKey('erp_family', $dictionary->getFields(Context::PRODUCT));
        self::assertArrayNotHasKey('erp_family', $dictionary->getFields(Context::CART));
    }

    #[Test]
    #[DataProvider('invalidDefinitions')]
    public function anInvalidDefinitionIsRefused(string $yaml, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        DictionaryFactory::withOverrides($yaml)->getFields();
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'field and expression together' => [
            "fields:\n    broken:\n        field: product.ref\n        expression: \"1\"\n",
            'requires exactly one of "field" or "expression"',
        ];
        yield 'unknown type' => [
            "fields:\n    broken:\n        field: product.ref\n        type: json\n",
            'unknown type "json"',
        ];
        yield 'values on a boolean' => [
            "fields:\n    broken:\n        field: product.visible\n        type: boolean\n        values_query: \"SELECT 1 AS value\"\n",
            '"values_query" does not apply',
        ];
        yield ':value expression without polarity operators' => [
            "fields:\n    broken:\n        expression: \"product.id IN (:value)\"\n",
            'its "operators" must be declared among [in, notIn]',
        ];
        yield 'join without a source column' => [
            "joins:\n    broken_table:\n        to: id\n",
            'requires a "from" in "table.column" form',
        ];
        yield 'malformed hook entry' => [
            "contexts:\n    PRODUCT:\n        - 42\n",
            'invalid hook entry in context "PRODUCT"',
        ];
    }
}
