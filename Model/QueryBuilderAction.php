<?php

declare(strict_types=1);

namespace QueryBuilder\Model;

use QueryBuilder\Model\Base\QueryBuilderAction as BaseQueryBuilderAction;

class QueryBuilderAction extends BaseQueryBuilderAction
{
    use JsonColumnTrait;

    public function getConditionTreeArray(): ?array
    {
        $tree = $this->decodeJsonColumn($this->getConditionTree(), sprintf('query_builder_action#%d.condition_tree', (int) $this->getId()));

        return \is_array($tree) ? $tree : null;
    }

    public function getParametersArray(): array
    {
        $parameters = $this->decodeJsonColumn($this->getParameters(), sprintf('query_builder_action#%d.parameters', (int) $this->getId()));

        return \is_array($parameters) ? $parameters : [];
    }
}
