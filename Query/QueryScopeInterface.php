<?php

declare(strict_types=1);

namespace QueryBuilder\Query;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point: any module can restrict every product query built by the
 * QueryBuilder (ex: filter the products on the catalog of the logged-in
 * customer). Implementations are autoconfigured through this tag.
 */
#[AutoconfigureTag(QueryScopeInterface::TAG)]
interface QueryScopeInterface
{
    public const TAG = 'querybuilder.query_scope';

    public function apply(QueryParts $queryParts, RuntimeContext $runtimeContext): void;
}
