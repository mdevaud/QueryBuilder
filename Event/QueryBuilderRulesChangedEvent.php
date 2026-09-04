<?php

declare(strict_types=1);

namespace QueryBuilder\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after any back-office mutation of a rule or an action (create,
 * save, toggle, delete): the outcome of the rules may have changed for any
 * customer. Extension point for the project modules — typically to invalidate
 * caches that embed rule results (recommendations, discounts).
 */
final class QueryBuilderRulesChangedEvent extends Event
{
}
