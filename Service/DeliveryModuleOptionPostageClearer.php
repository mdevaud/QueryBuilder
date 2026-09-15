<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Thelia\Api\Resource\DeliveryModuleOption;

/**
 * A free delivery is announced on every delivery option (Flexy delivery step,
 * DeliveryModule API resource), not only on the cart summary: the option keeps
 * the amount its module computed otherwise, so the customer would read a price
 * on the card and "Free delivery" in the summary for the same choice.
 *
 * Zero rather than null: null is what a module writes when its option is not
 * valid; a zero is a price, and the fronts render a zero as free.
 */
final readonly class DeliveryModuleOptionPostageClearer
{
    /**
     * @param DeliveryModuleOption[] $deliveryModuleOptions
     */
    public function clear(array $deliveryModuleOptions): void
    {
        foreach ($deliveryModuleOptions as $deliveryModuleOption) {
            if (!$deliveryModuleOption instanceof DeliveryModuleOption) {
                continue;
            }

            $deliveryModuleOption
                ->setPostage(0.0)
                ->setPostageTax(0.0)
                ->setPostageUntaxed(0.0);
        }
    }
}
