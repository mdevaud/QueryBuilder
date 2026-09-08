# Changelog

All notable changes to this module are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the module adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Port of the module to Thelia 3. The Thelia 2 line (1.0.0 to 1.2.0) lived in the project the module was written for and is not published.

### Added
- Back-office screens on the default-twig theme (Twig, Bootstrap 5): rule list, rule in three steps, action screen, entry in the Tools menu.
- Condition editor provided by `openstudio/query-builder-bundle` (`QueryBuilderType`, `native` processor, per-field operators), with a readable summary of the tree, a condition counter and the hook grid filtered by context.
- Front rendering through the `theme_hook()` points of Flexy (`ThemeHookInterface`) with a product list template based on the Flexy cross-selling component, overridable by the theme.
- Twig function `query_builder_products()` returning the hook result (ids, offers, actions).
- API Platform resource `GET /api/front/query_builder/products/{hookCode}` with the product resources embedded, and two front addons: `QueryBuilderCartDiscount` on the cart, `QueryBuilderProductOffer` on the products.
- Product discounts applied on the cart lines (promotion columns), with the stackable and non-stackable policies against a catalog promotion.
- Free shipping on `CART_SET_POSTAGE` and through the postage estimator of the core.
- Cart discount fragment shown on the checkout pages (`checkout.top`, `cart.bottom`).
- Dictionary fields for the cart products total (`:cart_total`) and the delivery country (`:delivery_country_id`); `--cart-total` and `--delivery-country` options on the debug commands.
- Flexy hook codes declared per context in the base dictionary.
- English and French translations of every label (`querybuilder` and `querybuilder.bo.default-twig` domains).
- Unit tests (SQL compiler, dictionary, discount arithmetic), a module activation integration test and a GitHub Actions workflow running them on a fresh shop.

### Changed
- Requires PHP 8.3, Thelia 3 and `openstudio/query-builder-bundle` ^1.1.
- Dictionary overrides are looked up in the directory of each module, wherever Composer installed it.
- A `datetime` field is entered as a date and compared on its date part.
- Dictionary labels, context labels and action labels are translation keys.
- Cart discount amounts are computed by a single service shared by the cart listener, the API addon and the theme hook.

### Removed
- Smarty templates, plugin and vendored react-querybuilder build of the Thelia 2 line.
- The `/query_builder/products/{hookCode}` Symfony route, replaced by the API Platform resource.
- The `product.top` and `product.bottom` `BaseHook` front hooks, replaced by the theme hook implementation.

[Unreleased]: https://github.com/thelia-modules/QueryBuilder/commits/main
