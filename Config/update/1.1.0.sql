# 1.1.0 — discounts become stateless (ApplyDiscount actions), the suggestion
# table no longer carries an offer snapshot.

ALTER TABLE `query_builder_suggestion`
    DROP COLUMN `discount_rate`,
    DROP COLUMN `discount_label`,
    DROP COLUMN `discount_cumulative`;
