<?php

declare(strict_types=1);

namespace QueryBuilder\Model;

use QueryBuilder\Model\Base\QueryBuilderRule as BaseQueryBuilderRule;

class QueryBuilderRule extends BaseQueryBuilderRule
{
    use JsonColumnTrait;

    /** @return string[] */
    public function getHookCodes(): array
    {
        $hooks = $this->decodeJsonColumn($this->getHooks(), sprintf('query_builder_rule#%d.hooks', (int) $this->getId()));

        return \is_array($hooks) ? $hooks : [];
    }

    /** @param string[] $hookCodes */
    public function setHookCodes(array $hookCodes): static
    {
        return $this->setHooks(json_encode(array_values($hookCodes), \JSON_THROW_ON_ERROR));
    }

    public function getConditionTreeArray(): ?array
    {
        $tree = $this->decodeJsonColumn($this->getConditionTree(), sprintf('query_builder_rule#%d.condition_tree', (int) $this->getId()));

        return \is_array($tree) ? $tree : null;
    }
}
