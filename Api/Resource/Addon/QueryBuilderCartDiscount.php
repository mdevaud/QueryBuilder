<?php

declare(strict_types=1);

namespace QueryBuilder\Api\Resource\Addon;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;
use QueryBuilder\Service\AddonRuntime;
use Symfony\Component\Serializer\Attribute\Groups;
use Thelia\Api\Resource\Cart;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\ResourceAddonTrait;
use Thelia\Model\Cart as CartModel;

/**
 * Exposes on the front cart resource the rule discount granted by the
 * ApplyCartDiscount actions: rate, label, amount (taxes included) and free
 * shipping, so a decoupled front can show the label next to the discount total
 * the core already carries in `discount`.
 */
final class QueryBuilderCartDiscount implements ResourceAddonInterface
{
    use ResourceAddonTrait;

    #[Groups([Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE])]
    public ?float $rate = null;

    #[Groups([Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE])]
    public ?string $label = null;

    #[Groups([Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE])]
    public ?float $amount = null;

    #[Groups([Cart::GROUP_FRONT_READ, Cart::GROUP_FRONT_READ_SINGLE])]
    public bool $freeShipping = false;

    public static function getResourceParent(): string
    {
        return Cart::class;
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return null;
    }

    public static function extendQuery(ModelCriteria $query, ?Operation $operation = null, array $context = []): void
    {
        //No table behind this addon: the discount is resolved on demand in buildFromModel()
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        $runtime = AddonRuntime::current();

        if (!$activeRecord instanceof CartModel || $runtime === null) {
            return $this;
        }

        $applied = $runtime->cartDiscountCalculator->resolve($activeRecord);

        if ($applied === null) {
            return $this;
        }

        $this->rate = $applied->rate;
        $this->label = $applied->label;
        $this->amount = $applied->amount;
        $this->freeShipping = $applied->freeShipping;

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        return $this;
    }

    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
    }
}
