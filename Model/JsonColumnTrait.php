<?php

declare(strict_types=1);

namespace QueryBuilder\Model;

use Thelia\Log\Tlog;

trait JsonColumnTrait
{
    /**
     * Decodes a JSON column. A corrupt value is logged then neutralized (null)
     * instead of silently ignored or breaking the front rendering.
     */
    private function decodeJsonColumn(?string $rawValue, string $columnDescription): mixed
    {
        if ($rawValue === null || trim($rawValue) === '') {
            return null;
        }

        try {
            return json_decode($rawValue, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Tlog::getInstance()->error('QueryBuilder: invalid JSON column', [
                'column' => $columnDescription,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
