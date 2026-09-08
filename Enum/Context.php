<?php

declare(strict_types=1);

namespace QueryBuilder\Enum;

enum Context: string
{
    case GLOBAL_SCOPE = 'GLOBAL';
    case CATEGORY = 'CATEGORY';
    case BRAND = 'BRAND';
    case PRODUCT = 'PRODUCT';
    case CART = 'CART';
    case ORDER = 'ORDER';
    case CUSTOMER = 'CUSTOMER';

    /** Translation key (querybuilder domain) of the label shown in the back-office. */
    public function label(): string
    {
        return match ($this) {
            self::GLOBAL_SCOPE => 'Global (all contexts)',
            self::CATEGORY => 'Category',
            self::BRAND => 'Brand',
            self::PRODUCT => 'Product',
            self::CART => 'Cart',
            self::ORDER => 'Order',
            self::CUSTOMER => 'Customer',
        };
    }

    /** Translation key (querybuilder domain) of the help text shown in the back-office. */
    public function description(): string
    {
        return match ($this) {
            self::GLOBAL_SCOPE => 'Rule not attached to an object: it can listen to the hooks of every context.',
            self::CATEGORY => 'Rule evaluated on a catalog category.',
            self::BRAND => 'Rule evaluated on a brand.',
            self::PRODUCT => 'Rule evaluated on a product.',
            self::CART => 'Rule evaluated on the cart of the current customer.',
            self::ORDER => 'Rule evaluated on an order.',
            self::CUSTOMER => 'Rule evaluated on the current (logged in) customer.',
        };
    }
}
