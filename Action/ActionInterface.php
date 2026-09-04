<?php

declare(strict_types=1);

namespace QueryBuilder\Action;

use QueryBuilder\Enum\Context;
use QueryBuilder\Model\QueryBuilderAction;
use QueryBuilder\Query\RuntimeContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A predefined action executable by a rule. Implementations are matched with
 * the query_builder_action rows through their code, and autoconfigured
 * through this tag.
 */
#[AutoconfigureTag(ActionInterface::TAG)]
interface ActionInterface
{
    public const TAG = 'querybuilder.action';

    public const TYPE_DISPLAY = 'display';
    public const TYPE_ACTION = 'action';
    public const TYPE_FILTER = 'filter';

    public static function getCode(): string;

    public static function getType(): string;

    public static function getLabel(): string;

    /** @return Context[] empty = available in every context */
    public static function getSupportedContexts(): array;

    public function execute(QueryBuilderAction $action, RuntimeContext $runtimeContext): ActionResult;
}
