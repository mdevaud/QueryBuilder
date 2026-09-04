<?php

declare(strict_types=1);

namespace QueryBuilder\Action;

use QueryBuilder\Model\QueryBuilderAction;
use QueryBuilder\Query\RuntimeContext;

/**
 * Grants a cart-level discount to the customers matching the rule eligibility:
 * a percentage off the cart products total and/or free shipping.
 *
 * Parameters:
 *  - cart_discount_rate (float, optional): percentage off the cart products
 *    total (taxes included, shipping excluded)
 *  - cart_discount_free_shipping (bool, default false): sets the order
 *    postage to zero
 *
 * The discount goes through the core Thelia discount channel (cart.discount /
 * order.discount) — NOT through the coupon machinery. Unlike display actions,
 * this action is not driven by hooks: CartDiscountListener applies it on the
 * cart events and on ORDER_SET_POSTAGE, via CartDiscountResolutionService.
 * The action condition tree is therefore irrelevant here (the discount has no
 * product target); eligibility is the RULE condition tree. execute() is a
 * no-op on hook execution.
 */
final readonly class ApplyCartDiscountAction implements ActionInterface
{
    public const CODE = 'ApplyCartDiscount';

    public static function getCode(): string
    {
        return self::CODE;
    }

    public static function getType(): string
    {
        return self::TYPE_ACTION;
    }

    public static function getLabel(): string
    {
        return 'Remise globale panier';
    }

    public static function getSupportedContexts(): array
    {
        return [];
    }

    public function execute(QueryBuilderAction $action, RuntimeContext $runtimeContext): ActionResult
    {
        return new ActionResult();
    }
}
