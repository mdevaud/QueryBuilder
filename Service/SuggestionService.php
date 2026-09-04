<?php

declare(strict_types=1);

namespace QueryBuilder\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use QueryBuilder\Model\QueryBuilderAction;
use QueryBuilder\Model\QueryBuilderSuggestion;
use QueryBuilder\Model\QueryBuilderSuggestionQuery;

/**
 * Persistent state of the sticky selections (displayed suggestions and
 * persisted product discounts): a cycle lasts persist_days × 24 hours or ends
 * with the purchase of the product. One row per (customer, product, action).
 */
final class SuggestionService
{
    /** @return int[] product ids of the still-active suggestions of this action, oldest display first */
    public function getActiveProductIds(int $customerId, int $actionId): array
    {
        $productIds = [];

        $suggestions = $this->createActiveQuery($customerId)
            ->filterByActionId($actionId)
            ->orderByDisplayedAt()
            ->find();

        foreach ($suggestions as $suggestion) {
            $productIds[] = (int) $suggestion->getProductId();
        }

        return $productIds;
    }

    /**
     * Registers a new display cycle for the given products. Existing rows are
     * refreshed (a product re-selected after expiry or purchase starts a new
     * cycle), new ones are created.
     *
     * @param int[] $productIds
     */
    public function recordSuggestions(
        int $customerId,
        QueryBuilderAction $action,
        array $productIds,
        int $persistDays,
        ?string $hook = null,
    ): void {
        if ($productIds === []) {
            return;
        }

        $now = new \DateTime();
        $expiresAt = (new \DateTime())->modify(sprintf('+%d day', max(1, $persistDays)));

        //Les cycles existants sont chargés en une seule requête (pas de findOne par produit)
        $existingSuggestions = [];
        $suggestions = QueryBuilderSuggestionQuery::create()
            ->filterByCustomerId($customerId)
            ->filterByActionId($action->getId())
            ->filterByProductId($productIds, Criteria::IN)
            ->find();

        foreach ($suggestions as $suggestion) {
            $existingSuggestions[(int) $suggestion->getProductId()] = $suggestion;
        }

        foreach ($productIds as $productId) {
            $suggestion = $existingSuggestions[(int) $productId]
                ?? (new QueryBuilderSuggestion())
                    ->setCustomerId($customerId)
                    ->setProductId($productId)
                    ->setActionId($action->getId());

            $suggestion
                ->setRuleId($action->getRuleId())
                ->setHook($hook)
                ->setDisplayedAt($now)
                ->setExpiresAt($expiresAt)
                ->setPurchasedAt(null)
                ->save();
        }
    }

    /** Pushes back the expiry of still-active cycles (a discounted product in the cart keeps its price). */
    public function extendCycles(int $customerId, int $actionId, array $productIds, int $persistDays): void
    {
        if ($productIds === []) {
            return;
        }

        $this->createActiveQuery($customerId)
            ->filterByActionId($actionId)
            ->filterByProductId($productIds, Criteria::IN)
            ->update([
                'ExpiresAt' => (new \DateTime())->modify(sprintf('+%d day', max(1, $persistDays))),
                'UpdatedAt' => new \DateTime(),
            ]);
    }

    /**
     * Ends the active cycles of the action for every customer. Called when the
     * action condition tree changes: the sticky path never re-checks the tree,
     * so a still-running cycle would keep serving products the new conditions
     * exclude until its natural expiry.
     */
    public function expireActiveCycles(int $actionId): void
    {
        $now = new \DateTime();

        QueryBuilderSuggestionQuery::create()
            ->filterByActionId($actionId)
            ->filterByPurchasedAt(null, Criteria::ISNULL)
            ->filterByExpiresAt($now, Criteria::GREATER_THAN)
            ->update(['ExpiresAt' => $now, 'UpdatedAt' => $now]);
    }

    /**
     * End of the latest cycle of every product ever selected for this action,
     * active cycles included (their end is in the future).
     *
     * @return array<int, string> product id => cycle end (sortable datetime string)
     */
    public function getLastCycleEndsByProductId(int $customerId, int $actionId): array
    {
        $cycleEnds = [];

        $suggestions = QueryBuilderSuggestionQuery::create()
            ->filterByCustomerId($customerId)
            ->filterByActionId($actionId)
            ->find();

        foreach ($suggestions as $suggestion) {
            $cycleEnd = $suggestion->getPurchasedAt() ?? $suggestion->getExpiresAt();
            $cycleEnds[(int) $suggestion->getProductId()] = $cycleEnd?->format('Y-m-d H:i:s') ?? '';
        }

        return $cycleEnds;
    }

    /**
     * Rotation order for the refill: products never selected first (keeping
     * the eligibility order), then already-cycled products from the oldest
     * cycle end — an expired product goes to the back of the queue instead of
     * being immediately re-selected.
     *
     * @param int[] $eligibleProductIds
     * @param array<int, string> $lastCycleEnds
     *
     * @return int[]
     */
    public function orderRefillCandidates(array $eligibleProductIds, array $lastCycleEnds): array
    {
        $fresh = [];
        $cycled = [];

        foreach ($eligibleProductIds as $productId) {
            if (isset($lastCycleEnds[$productId])) {
                $cycled[$productId] = $lastCycleEnds[$productId];
            } else {
                $fresh[] = $productId;
            }
        }

        asort($cycled);

        return array_merge($fresh, array_keys($cycled));
    }

    /** Ends the display cycle of the purchased products (the suggestion dies with the purchase). */
    public function markPurchased(int $customerId, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $now = new \DateTime();

        //Mass update: single SQL query, no per-row hydration/save
        $this->createActiveQuery($customerId)
            ->filterByProductId($productIds, Criteria::IN)
            ->update(['PurchasedAt' => $now, 'UpdatedAt' => $now]);
    }

    private function createActiveQuery(int $customerId): QueryBuilderSuggestionQuery
    {
        return QueryBuilderSuggestionQuery::create()
            ->filterByCustomerId($customerId)
            ->filterByPurchasedAt(null, Criteria::ISNULL)
            ->filterByExpiresAt(new \DateTime(), Criteria::GREATER_THAN);
    }
}
