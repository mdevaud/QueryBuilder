<?php

declare(strict_types=1);

namespace QueryBuilder\Tests\Unit\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use QueryBuilder\Api\FrontRead;

final class FrontReadTest extends TestCase
{
    #[Test]
    public function aReadWithFrontGroupsOnlyIsAFrontRead(): void
    {
        self::assertTrue(FrontRead::isFrontRead(['groups' => ['front:product:read', 'front:product:read:single']]));
    }

    #[Test]
    public function anAdminGroupMakesTheWholeReadAnAdminOne(): void
    {
        self::assertFalse(FrontRead::isFrontRead(['groups' => ['front:product:read', 'admin:product:read']]));
        self::assertFalse(FrontRead::isFrontRead(['groups' => ['admin:product:read']]));
    }

    #[Test]
    public function aReadWithoutFrontGroupIsNotAFrontRead(): void
    {
        self::assertFalse(FrontRead::isFrontRead([]));
        self::assertFalse(FrontRead::isFrontRead(['groups' => []]));
        self::assertFalse(FrontRead::isFrontRead(['groups' => ['Default', 42]]));
    }

    #[Test]
    public function aSingleGroupGivenAsAStringIsAccepted(): void
    {
        self::assertTrue(FrontRead::isFrontRead(['groups' => 'front:cart:read']));
        self::assertFalse(FrontRead::isFrontRead(['groups' => 'admin:cart:read']));
    }
}
