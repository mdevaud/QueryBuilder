<?php

declare(strict_types=1);

namespace QueryBuilder\Action;

use QueryBuilder\Model\QueryBuilderAction;
use QueryBuilder\Model\QueryBuilderRule;

final readonly class ExecutedAction
{
    public function __construct(
        public QueryBuilderRule $rule,
        public QueryBuilderAction $action,
        public string $type,
        public ActionResult $result,
    ) {
    }
}
