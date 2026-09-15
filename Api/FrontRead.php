<?php

declare(strict_types=1);

namespace QueryBuilder\Api;

/**
 * Tells a front API read from an admin one by the serialization groups of the
 * context, the way the core price listener does: group names carry their side
 * ("front:product:read" against "admin:product:read"), and a read no admin
 * group takes part in is a front read, a module resource embedding core
 * resources included.
 */
final readonly class FrontRead
{
    /** @param array<string, mixed> $context */
    public static function isFrontRead(array $context): bool
    {
        $groups = $context['groups'] ?? [];

        if (!\is_array($groups)) {
            $groups = [$groups];
        }

        $isFrontRead = false;

        foreach ($groups as $group) {
            if (!\is_string($group)) {
                continue;
            }

            if (str_starts_with($group, 'admin:')) {
                return false;
            }

            $isFrontRead = $isFrontRead || str_starts_with($group, 'front:');
        }

        return $isFrontRead;
    }
}
