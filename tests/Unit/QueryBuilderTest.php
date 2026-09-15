<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\QueryBuilder;

final class QueryBuilderTest extends TestCase
{
    #[Test]
    public function aThelia2CoreIsRefusedAtPreActivationWithAnExplicitMessage(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thelia 3.0.0 or later');
        $this->expectExceptionMessage('2.5.4');

        QueryBuilder::assertSupportedCoreVersion('2.5.4');
    }

    #[Test]
    public function aThelia3CoreIsAccepted(): void
    {
        QueryBuilder::assertSupportedCoreVersion('3.0.0');
        QueryBuilder::assertSupportedCoreVersion('3.1.0-beta1');

        $this->addToAssertionCount(2);
    }

    #[Test]
    public function anUnknownCoreVersionIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);

        QueryBuilder::assertSupportedCoreVersion(null);
    }
}
