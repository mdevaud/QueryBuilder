<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit\Discount;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\Discount\CartLineDiscountPolicy;
use QueryBuilder\Discount\Discount;
use QueryBuilder\Discount\LinePrices;

final class CartLineDiscountPolicyTest extends TestCase
{
    private CartLineDiscountPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new CartLineDiscountPolicy();
    }

    #[Test]
    public function aRuleDiscountsTheCatalogPriceOfALineWithoutPromotion(): void
    {
        $line = $this->policy->discountedLine(new Discount(10.0, 'Loyalty', false), new LinePrices(100.0, 0.0, 0));

        self::assertEquals(new LinePrices(100.0, 90.0, 1), $line);
    }

    #[Test]
    public function aNonStackableRuleLeavesABetterCatalogPromotionAlone(): void
    {
        self::assertNull($this->policy->discountedLine(new Discount(10.0, null, false), new LinePrices(100.0, 85.0, 1)));
        self::assertNull($this->policy->discountedLine(new Discount(10.0, null, false), new LinePrices(100.0, 90.0, 1)), 'an equal promotion is not replaced');
    }

    #[Test]
    public function aNonStackableRuleReplacesAWorseCatalogPromotion(): void
    {
        $line = $this->policy->discountedLine(new Discount(20.0, null, false), new LinePrices(100.0, 90.0, 1));

        self::assertEquals(new LinePrices(100.0, 80.0, 1), $line);
    }

    #[Test]
    public function aStackableRuleAppliesOnTopOfTheCatalogPromotion(): void
    {
        $line = $this->policy->discountedLine(new Discount(10.0, null, true), new LinePrices(100.0, 80.0, 1));

        self::assertEquals(new LinePrices(100.0, 72.0, 1), $line);
    }

    #[Test]
    public function aPromotionTheCatalogDoesNotExplainWasWrittenByARule(): void
    {
        $catalogWithoutPromo = new LinePrices(100.0, 0.0, 0);
        $catalogWithPromo = new LinePrices(100.0, 80.0, 1);

        self::assertTrue($this->policy->carriesRuleDiscount(new LinePrices(100.0, 90.0, 1), $catalogWithoutPromo));
        self::assertTrue($this->policy->carriesRuleDiscount(new LinePrices(100.0, 72.0, 1), $catalogWithPromo));
        self::assertFalse($this->policy->carriesRuleDiscount(new LinePrices(100.0, 80.0, 1), $catalogWithPromo), 'the catalog promotion itself');
        self::assertFalse($this->policy->carriesRuleDiscount(new LinePrices(100.0, 0.0, 0), $catalogWithoutPromo), 'no promotion at all');
    }

    #[Test]
    public function linesCompareWithinACentTolerance(): void
    {
        self::assertTrue($this->policy->sameLine(new LinePrices(100.0, 90.0, 1), new LinePrices(100.004, 89.996, 1)));
        self::assertFalse($this->policy->sameLine(new LinePrices(100.0, 90.0, 1), new LinePrices(100.0, 90.0, 0)));
    }
}
