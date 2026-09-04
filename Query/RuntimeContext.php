<?php

declare(strict_types=1);

namespace QueryBuilder\Query;

/**
 * Runtime data available when a rule is executed (current customer, cart,
 * product...). Values are exposed as named SQL parameters to the dictionary
 * expressions and join clauses (:customer_id, :cart_product_ids, :locale...).
 */
final readonly class RuntimeContext
{
    //Bound when there is no current product so "product.id = :product_id" stays
    //executable everywhere: "= true" never matches, "= false" lets everything through
    private const NO_CURRENT_PRODUCT_ID = 0;

    /** @param array<string, mixed> $parameters extra project parameters, keyed by placeholder name without colon */
    public function __construct(
        public ?int $customerId = null,
        public ?int $productId = null,
        public ?int $cartId = null,
        public array $cartProductIds = [],
        public ?int $orderId = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public string $locale = 'fr_FR',
        public array $parameters = [],
    ) {
    }

    /** @return array<string, mixed> placeholder name (without colon) => value */
    public function getBindableParameters(): array
    {
        return array_merge(
            [
                'customer_id' => $this->customerId,
                'product_id' => $this->productId ?? self::NO_CURRENT_PRODUCT_ID,
                'cart_id' => $this->cartId,
                'cart_product_ids' => $this->cartProductIds,
                'order_id' => $this->orderId,
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'locale' => $this->locale,
            ],
            $this->parameters
        );
    }
}
