<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\Service\CartDiscountLedger;

final class CartDiscountLedgerTest extends TestCase
{
    #[Test]
    public function theRuleAmountIsAddedOnTopOfTheCouponDiscount(): void
    {
        $ledger = new CartDiscountLedger();

        self::assertSame(15.0, $ledger->nextTotal(1, 5.0, 10.0));
    }

    #[Test]
    public function aNestedPassOnAnUntouchedColumnDoesNotAccumulate(): void
    {
        $ledger = new CartDiscountLedger();
        $written = $ledger->nextTotal(1, 5.0, 10.0);

        //The cart events nest: the column still holds what the first pass wrote
        self::assertSame(15.0, $ledger->nextTotal(1, $written, 10.0));
        self::assertSame(15.0, $ledger->nextTotal(1, 15.0, 10.0));
    }

    #[Test]
    public function aRecomputedRuleAmountReplacesThePreviousOne(): void
    {
        $ledger = new CartDiscountLedger();
        $written = $ledger->nextTotal(1, 5.0, 10.0);

        //Quantity changed: the rule amount is now 12, the coupon part (5) is kept once
        self::assertSame(17.0, $ledger->nextTotal(1, $written, 12.0));
    }

    #[Test]
    public function anUpstreamResetOfTheColumnStartsFromItsNewValue(): void
    {
        $ledger = new CartDiscountLedger();
        $ledger->nextTotal(1, 5.0, 10.0);

        //The core rewrote the column (a coupon was added): our part is gone, add it again
        self::assertSame(18.0, $ledger->nextTotal(1, 8.0, 10.0));
    }

    #[Test]
    public function cartsAreTrackedSeparatelyAndTotalsAreRoundedToCents(): void
    {
        $ledger = new CartDiscountLedger();

        self::assertSame(10.0, $ledger->nextTotal(1, 0.0, 10.0));
        self::assertSame(3.33, $ledger->nextTotal(2, 0.0, 3.333));
        self::assertSame(3.33, $ledger->nextTotal(2, 3.33, 3.333));
    }
}
