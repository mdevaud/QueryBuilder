<?php

declare(strict_types=1);

namespace QueryBuilder\Action;

/**
 * Result of an executed action. A "display" action either returns raw html
 * (injected as-is at the hook location) or product ids (rendered by the
 * caller: hook template on the front-office, JSON payload on the API).
 */
final readonly class ActionResult
{
    /** @param int[] $productIds */
    public function __construct(
        public ?string $html = null,
        public array $productIds = [],
    ) {
    }
}
