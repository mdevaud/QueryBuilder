<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use QueryBuilder\Action\ActionRegistry;
use QueryBuilder\Action\ExecutedAction;
use QueryBuilder\Enum\Context;
use QueryBuilder\Model\QueryBuilderActionQuery;
use QueryBuilder\Model\QueryBuilderRule;
use QueryBuilder\Model\QueryBuilderRuleQuery;
use QueryBuilder\Query\RuntimeContext;
use Thelia\Log\Tlog;

/**
 * Entry point of the module: for a given hook, loads the active rules bound
 * to it, checks their condition tree against the current context, then
 * executes their active actions in order.
 */
final readonly class RuleEngine
{
    public function __construct(
        private SqlBuilder $sqlBuilder,
        private ActionRegistry $actionRegistry,
    ) {
    }

    /** @return ExecutedAction[] */
    public function executeHook(string $hookCode, RuntimeContext $runtimeContext): array
    {
        $matchedRules = array_values(array_filter(
            $this->getActiveRulesForHook($hookCode),
            fn (QueryBuilderRule $rule): bool => $this->ruleMatches($rule, $runtimeContext)
        ));

        if ($matchedRules === []) {
            return [];
        }

        //Toutes les actions actives des règles matchées en une seule requête
        $actionsByRuleId = [];
        $actions = QueryBuilderActionQuery::create()
            ->filterByRuleId(
                array_map(static fn (QueryBuilderRule $rule): int => (int) $rule->getId(), $matchedRules),
                Criteria::IN
            )
            ->filterByActivate(1)
            ->orderByPosition()
            ->orderById()
            ->find();

        foreach ($actions as $action) {
            $actionsByRuleId[(int) $action->getRuleId()][] = $action;
        }

        $executedActions = [];

        foreach ($matchedRules as $rule) {
            foreach ($actionsByRuleId[(int) $rule->getId()] ?? [] as $action) {
                $handler = $this->actionRegistry->get($action->getCode());

                if ($handler === null) {
                    Tlog::getInstance()->addWarning(sprintf(
                        'QueryBuilder: no ActionInterface implementation found for code "%s" (action #%d).',
                        $action->getCode(),
                        $action->getId()
                    ));

                    continue;
                }

                try {
                    $executedActions[] = new ExecutedAction(
                        $rule,
                        $action,
                        $handler::getType(),
                        $handler->execute($action, $runtimeContext)
                    );
                } catch (\InvalidArgumentException $exception) {
                    //A rule may reference a runtime placeholder missing from the current
                    //context (ex: anonymous visitor): skip the action instead of breaking the page
                    Tlog::getInstance()->addWarning(sprintf(
                        'QueryBuilder: action #%d of rule "%s" skipped: %s',
                        $action->getId(),
                        $rule->getName(),
                        $exception->getMessage()
                    ));
                }
            }
        }

        return $executedActions;
    }

    /** @return QueryBuilderRule[] */
    private function getActiveRulesForHook(string $hookCode): array
    {
        $rules = QueryBuilderRuleQuery::create()
            ->filterByActivate(1)
            ->orderByPosition()
            ->orderById()
            ->find();

        return array_values(array_filter(
            iterator_to_array($rules),
            static fn (QueryBuilderRule $rule): bool => \in_array($hookCode, $rule->getHookCodes(), true)
        ));
    }

    /**
     * A rule matches when its condition tree, restricted to the entity of the
     * current context (product being viewed, products of the cart...), selects
     * at least one product. A rule without condition tree always matches.
     * Public: also used by DiscountResolutionService for rule eligibility.
     */
    public function ruleMatches(QueryBuilderRule $rule, RuntimeContext $runtimeContext): bool
    {
        $conditionTree = $rule->getConditionTreeArray();

        if ($conditionTree === null) {
            return true;
        }

        try {
            return $this->sqlBuilder->exists(
                $conditionTree,
                $runtimeContext,
                $this->getContextRestrictions($rule, $runtimeContext)
            );
        } catch (\InvalidArgumentException $exception) {
            Tlog::getInstance()->addWarning(sprintf(
                'QueryBuilder: rule "%s" skipped: %s',
                $rule->getName(),
                $exception->getMessage()
            ));

            return false;
        }
    }

    /** @return string[] */
    private function getContextRestrictions(QueryBuilderRule $rule, RuntimeContext $runtimeContext): array
    {
        $context = Context::tryFrom($rule->getContext() ?? '');

        return match ($context) {
            Context::PRODUCT => $runtimeContext->productId !== null
                ? ['`product`.`id` = :product_id']
                : [],
            Context::CART => ['`product`.`id` IN (:cart_product_ids)'],
            Context::CATEGORY => $runtimeContext->categoryId !== null
                ? ['`product`.`id` IN (SELECT product_id FROM product_category WHERE category_id = :category_id)']
                : [],
            Context::BRAND => $runtimeContext->brandId !== null
                ? ['`product`.`brand_id` = :brand_id']
                : [],
            default => [],
        };
    }
}
