<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\Service\DeliveryModuleOptionPostageClearer;
use Thelia\Api\Resource\DeliveryModuleOption;

final class DeliveryModuleOptionPostageClearerTest extends TestCase
{
    #[Test]
    public function everyOptionIsAnnouncedFreeTaxesIncluded(): void
    {
        $home = (new DeliveryModuleOption())->setPostage(6.9)->setPostageTax(1.15)->setPostageUntaxed(5.75);
        $pickup = (new DeliveryModuleOption())->setPostage(3.5)->setPostageTax(0.58)->setPostageUntaxed(2.92);

        (new DeliveryModuleOptionPostageClearer())->clear([$home, $pickup]);

        foreach ([$home, $pickup] as $option) {
            self::assertSame(0.0, $option->getPostage());
            self::assertSame(0.0, $option->getPostageTax());
            self::assertSame(0.0, $option->getPostageUntaxed());
        }
    }

    #[Test]
    public function anInvalidOptionWithoutPriceBecomesAZeroPriceToo(): void
    {
        $option = (new DeliveryModuleOption())->setValid(false);

        (new DeliveryModuleOptionPostageClearer())->clear([$option]);

        self::assertSame(0.0, $option->getPostage());
        self::assertFalse($option->isValid());
    }

    #[Test]
    public function foreignEntriesAreSkipped(): void
    {
        $option = (new DeliveryModuleOption())->setPostage(6.9);

        (new DeliveryModuleOptionPostageClearer())->clear(['not an option', $option]);

        self::assertSame(0.0, $option->getPostage());
    }
}
