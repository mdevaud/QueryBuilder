<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use QueryBuilder\Action\ApplyCartDiscountAction;
use QueryBuilder\Discount\CartDiscount;
use QueryBuilder\Model\QueryBuilderActionQuery;
use QueryBuilder\Model\QueryBuilderRuleQuery;
use QueryBuilder\Query\RuntimeContext;
use Thelia\Log\Tlog;

/**
 * Resolves the cart-level discount carried by the ApplyCartDiscount actions:
 * among the active rules matching the runtime context (customer eligibility),
 * the highest rate wins and free shipping is granted as soon as one eligible
 * action grants it.
 *
 * Not readonly: the result is memoized per context for the request (the cart
 * events can fire several times per request).
 */
final class CartDiscountResolutionService
{
    /** @var array<string, CartDiscount|null> */
    private array $memoizedDiscounts = [];

    public function __construct(
        private readonly RuleEngine $ruleEngine,
    ) {
    }

    public function getCartDiscount(RuntimeContext $runtimeContext): ?CartDiscount
    {
        $memoKey = md5(serialize($runtimeContext));

        if (!\array_key_exists($memoKey, $this->memoizedDiscounts)) {
            $this->memoizedDiscounts[$memoKey] = $this->resolve($runtimeContext);
        }

        return $this->memoizedDiscounts[$memoKey];
    }

    private function resolve(RuntimeContext $runtimeContext): ?CartDiscount
    {
        $actionsByRuleId = [];

        $actions = QueryBuilderActionQuery::create()
            ->filterByCode(ApplyCartDiscountAction::CODE)
            ->filterByActivate(1)
            ->orderByPosition()
            ->find();

        foreach ($actions as $action) {
            $actionsByRuleId[(int) $action->getRuleId()][] = $action;
        }

        if ($actionsByRuleId === []) {
            return null;
        }

        $rules = QueryBuilderRuleQuery::create()
            ->filterById(array_keys($actionsByRuleId), Criteria::IN)
            ->filterByActivate(1)
            ->orderByPosition()
            ->find();

        $bestRate = null;
        $bestLabel = null;
        $freeShipping = false;

        foreach ($rules as $rule) {
            try {
                if (!$this->ruleEngine->ruleMatches($rule, $runtimeContext)) {
                    continue;
                }
            } catch (\InvalidArgumentException $exception) {
                //Placeholder runtime absent du contexte courant (ex: visiteur anonyme)
                Tlog::getInstance()->addWarning(sprintf(
                    'QueryBuilder: cart discount rule "%s" skipped: %s',
                    $rule->getName(),
                    $exception->getMessage()
                ));

                continue;
            }

            foreach ($actionsByRuleId[(int) $rule->getId()] as $action) {
                $cartDiscount = CartDiscount::fromActionParameters($action->getParametersArray(), $action->getName());

                if ($cartDiscount === null) {
                    Tlog::getInstance()->addWarning(sprintf(
                        'QueryBuilder: ApplyCartDiscount action #%d of rule "%s" has no effect (no rate, no free shipping), skipped.',
                        $action->getId(),
                        $rule->getName()
                    ));

                    continue;
                }

                if ($cartDiscount->rate !== null && ($bestRate === null || $cartDiscount->rate > $bestRate)) {
                    $bestRate = $cartDiscount->rate;
                    $bestLabel = $cartDiscount->label;
                }

                $freeShipping = $freeShipping || $cartDiscount->freeShipping;
            }
        }

        if ($bestRate === null && !$freeShipping) {
            return null;
        }

        return new CartDiscount(rate: $bestRate, freeShipping: $freeShipping, label: $bestLabel);
    }
}
