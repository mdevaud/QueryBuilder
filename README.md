# QueryBuilder

Rule engine for Thelia 3. A merchant composes a condition tree in the back-office, the module compiles it to parameterized SQL on the server and runs the actions bound to the rule: a product list at a theme hook point, a discount on the cart lines, a discount on the cart total or free shipping. A JSON API serves decoupled fronts.

Only the fields of a data dictionary and a closed list of operators are accepted, and every value is bound: no SQL comes from the stored JSON. Any active module can extend the dictionary with its own fields, joins and hook points.

## Compatibility

- Thelia 3 (`thelia/core` ^3.0), Flexy front theme, default-twig back-office
- PHP 8.3+
- `openstudio/query-builder-bundle` ^1.1 (the back-office editor)

## Installation

```bash
composer require thelia/query-builder-module
php Thelia module:activate QueryBuilder
php bin/console cache:clear
```

Until version 1.1.0 of the bundle is published on Packagist, tell Composer where to find it first:

```bash
composer config repositories.query-builder-bundle vcs https://github.com/openstudio-fr/query-builder-bundle
composer require "openstudio/query-builder-bundle:dev-release/1.1.0 as 1.1.0"
```

Symfony Flex registers the bundle in `config/bundles.php`. If the condition editor stays empty in the back-office, check that the file contains `OpenStudio\QueryBuilderBundle\OpenStudioQueryBuilderBundle::class => ['all' => true]`.

The activation creates the tables `query_builder_rule`, `query_builder_action` and `query_builder_suggestion`, and adds a "Query Builder" entry to the Tools menu.

## Concepts

| Concept | Where | What it is |
|---|---|---|
| Rule | table `query_builder_rule` | A context (PRODUCT, CART...), the hook codes it is bound to, an optional condition tree (empty means always), an activation flag |
| Action | table `query_builder_action` | Attached to a rule: a code mapped to a PHP class, a type (`display`, `action`, `filter`), its own tree selecting the products it works on, JSON parameters |
| Context | enum `QueryBuilder\Enum\Context` | GLOBAL, CATEGORY, BRAND, PRODUCT, CART, ORDER, CUSTOMER. Filters the hooks, fields and actions offered to a rule |
| Field, join | YAML dictionary | The fields offered in the editor and the join graph used to compile them |
| Hook | YAML dictionary | A `theme_hook()` point of the front theme, or a virtual code served through the API only |

A rule runs when one of its hooks is called and its condition tree matches the current visit (the product being displayed, the products of the cart, the customer...). It then runs its active actions in order.

## Back-office

The screens live under `/admin/query_builder`: the list of rules, a rule screen in three steps (identification, context and hooks, conditions) followed by its actions, and an action screen with the parameters of the selected action code.

The condition editor is the `QueryBuilderType` form type of the bundle, configured with the `native` processor: the stored tree is the react-querybuilder structure (`combinator`, `not`, `rules` with `field`, `operator`, `value`), without the ids the editor keeps for itself. The fields offered depend on the context of the rule; changing the context drops the conditions on fields the new context does not offer. A readable summary of the tree is shown above the editor.

The form type checks every submitted tree against the declared fields and per-field operators; the module then checks the fields against the context of the rule (`SqlBuilder::validateTree()`). A tree naming a field or an operator outside the dictionary is refused at save time and at run time.

### JavaScript build

The editor script (React, react-querybuilder, the Stimulus controller of the bundle and the two controllers of the module) is built with webpack from `Resources/` into `templates/backOffice/default-twig/assets/dist/`, which is committed and served by the Thelia asset resolver on the module pages only. To rebuild it:

```bash
cd Resources
npm install
npm run build
```

The bundle controller is read from `vendor/openstudio/query-builder-bundle/assets`, four levels up from `Resources/` when the module sits in `local/modules/QueryBuilder`. Set `QUERY_BUILDER_BUNDLE_ASSETS` to that directory otherwise.

## Data dictionary

The base dictionary is `Config/query_builder.yml`; its header documents the full syntax. Every active module may ship its own `Config/query_builder.yml`, merged over the base one in module position order: joins and fields are replaced by code, hooks are merged by code (the last module wins on the label), the `disabled` list removes base fields.

Two kinds of fields:

- `field: table.column`: the joins from `product` to the table are added automatically from the `joins` graph. A join flagged `multivalued` (one product, several rows) compiles its comparisons to `EXISTS` or `NOT EXISTS` subqueries, so that `notIn` or `!=` exclude the whole product instead of matching another joined row.
- `expression: <SQL>`: a self-contained SQL expression, subqueries allowed, which may reference runtime placeholders.

Runtime placeholders: `:customer_id`, `:cart_id`, `:cart_product_ids`, `:product_id` (bound to 0 outside a product page, so "is the current product = false" lets everything through), `:order_id`, `:category_id`, `:brand_id`, `:locale`, `:cart_total` (products total of the cart, taxes included) and `:delivery_country_id`, plus the ones provided by the project through `RuntimeParameterProviderInterface`. A placeholder without a value in the current visit (an anonymous visitor on a rule that needs `:customer_id`, for instance) makes the rule skip with a logged warning, and the page renders anyway.

An expression may contain the `:value` token: the value entered in the editor is then expanded inside the expression, one placeholder per item, and the field must restrict its `operators` to `in` and `notIn`, which only set the polarity.

Field types: `text`, `number`, `date`, `datetime` (entered as a date, compared on `DATE()`), `boolean`. A field may declare `values_query` (a `value` column, an optional `label` column, `:locale` allowed): the editor then offers a "list of values" select next to the free operators. Beyond 300 rows or on SQL error the field falls back to the free input. Labels of fields and hooks are translation keys of the `querybuilder` domain.

Fields shipped: product reference, title, visibility, category, category title, brand, price, creation date, product in the cart, current product, product bought in the last three months, cart products total, delivery country.

Hook points shipped, per context (the `theme_hook()` codes of Flexy): `home.top` and `home.bottom` (GLOBAL), `category.top` and `category.bottom`, `brand.top` and `brand.bottom`, `product.top`, `product.details.bottom` and `product.bottom`, `cart.top` and `cart.bottom`, `account.top` and `account.bottom` (CUSTOMER). A GLOBAL rule can be bound to any declared hook. Declare a hook only when a theme calls it, or when a front consumes it through the API.

## Front

The module implements `Thelia\Core\Hook\Theme\ThemeHookInterface` for every hook code of the dictionary: a `theme_hook('product.top', {product: product})` call in the theme runs the rules bound to `product.top` for the current visit and renders one product list per display action. The theme passes the resource it displays (product, category, brand); its id feeds the runtime context.

The product list is rendered with `templates/frontOffice/default/QueryBuilder/product-list.html.twig`, which relies on the Flexy component `Layouts:CrossSelling:Base` (products fetched by the theme, picked order kept). A theme overrides it by shipping `templates/frontOffice/<theme>/modules/QueryBuilder/product-list.html.twig`; the variables are `hook`, `rule_name`, `action_name` and `product_ids`.

The cart discount granted by a rule is shown on the checkout pages by `cart-discount.html.twig` (same override path), once per request, on the `checkout.top` and `cart.bottom` points.

For a custom rendering, the Twig function returns the same structure as the API:

```twig
{% set recommendations = query_builder_products('cart.recommendations', { product_id: product.id }) %}
{% for productId in recommendations.product_ids %}...{% endfor %}
```

Optional parameters: `product_id`, `order_id`, `category_id`, `brand_id`. On error the lists are empty and the error is logged.

## API

`GET /api/front/query_builder/products/{hookCode}` runs the display rules of a hook code for the current visit (session customer and cart) and returns:

| Field | Content |
|---|---|
| `hookCode` | The hook code |
| `productIds` | Unique product ids selected by the display actions, in selection order |
| `offers` | Discount offers keyed by product id (`rate`, `label`, `cumulative`) |
| `actions` | One entry per executed display action (`rule`, `action`, `product_ids`, `offers`) |
| `products` | The selected products as front `Product` resources, in the same order |

Query parameters: `product_id`, `category_id`, `brand_id`, `order_id`. A hook code the dictionary does not declare answers 404. Several rules bound to the same hook share one slot, capped by the highest action limit.

Two addons complete the core resources on the front: `QueryBuilderCartDiscount` on the cart (`rate`, `label`, `amount`, `freeShipping`) and `QueryBuilderProductOffer` on the products (`rate`, `label`, `cumulative`).

## Actions

### DisplayProductsList

Selects the products matching the action tree, `limit` at most (default 3). A product never appears in its own recommendations.

With `persist_days`, the selection becomes sticky for an identified customer: the products shown are served again for N times 24 hours or until purchased, and only the freed slots are refilled, products never suggested first, then the oldest cycles. A product bought ends its cycle (`ORDER_PAY`). Saving an action whose tree changed expires its running cycles; saving only its parameters does not. An anonymous visitor gets the stateless selection.

### ApplyDiscount

Parameters: `discount_rate` (percentage, required), `discount_label`, `discount_cumulative`, and the optional `limit` and `persist_days` of a sticky selection (an anonymous visitor then gets no discount at all).

The discount applies on the cart lines: at every cart change, customer login or currency change, the module re-reads the catalog prices of each line as the core does and writes the discounted price in the promotion columns of the line. A non-stackable rule (default) replaces a catalog promotion only when it is better; a stackable rule applies on top of it. When the rule stops applying, the line goes back to the catalog prices. The order lines inherit the cart line prices, so the amount is the same in the cart, at payment and on the order.

The tree of a discount action is evaluated on every surface, cart events included. Conditions that describe a recommendation ("product in the cart = false") cancel the discount as soon as the product enters the cart: keep the tree of a discount on stable criteria (brand, category, visibility).

The catalog prices shown on listings and product pages are not changed by this module: the offer is exposed through the `QueryBuilderProductOffer` addon for the front to display.

### ApplyCartDiscount

Parameters: `cart_discount_rate` (percentage of the cart products total, taxes included, shipping excluded) and `cart_discount_free_shipping`. The tree of the action is ignored: the eligibility is the rule tree. When several rules apply, the best rate wins and the shipping is free as soon as one rule offers it.

The amount goes through the core discount channel (`cart.discount`, then the order), without the coupon machinery: a coupon and a rule discount coexist and their amounts add up. The module listens to the cart events at priority 1, right after the core reset the column with the coupons, and keeps a per-request ledger so that nested cart events never add the amount twice.

Free shipping follows the coupon path of the core: the cart postage is cleared on `CART_SET_POSTAGE` (priority 133) and the postage estimator of the core is told the shipping is free. The prices of the delivery options are left as they are, as for a coupon. The legacy `ORDER_SET_POSTAGE` point is kept for a front still going through the order session.

## Extension points

- `QueryBuilder\Query\QueryScopeInterface`: restricts every product query (the catalog of the logged in customer, for instance).
- `QueryBuilder\Query\RuntimeParameterProviderInterface`: exposes project placeholders to the expressions (`:customer_typology_id`...).
- `QueryBuilder\Action\ActionInterface`: a new predefined action, offered in the editor.
- `QueryBuilder\Event\QueryBuilderRulesChangedEvent`: dispatched after any back-office change of a rule or an action, for the caches that embed rule results.
- A `Config/query_builder.yml` in any active module, see the dictionary above.

The implementations are autoconfigured through their interface.

## Translations

Two domains: `querybuilder` for the labels translated in PHP (contexts, actions, dictionary labels, editor wording, front fragments), in `I18n/<locale>.php`, and `querybuilder.bo.default-twig` for the back-office screens, in `I18n/backOffice/default-twig/<locale>.php`. English and French are shipped.

## Debug commands

```bash
php Thelia querybuilder:dictionary [CONTEXT]
php Thelia querybuilder:compile '{"combinator":"and","rules":[{"field":"product_ref","operator":"=","value":"ABC"}]}' --customer=42 --cart-total=120 --delivery-country=64 --execute
php Thelia querybuilder:run product.top --customer=42 --product=123
```

`querybuilder:compile` prints the SQL and its parameters; `--execute` also runs it. The tree is the stored react-querybuilder structure.

## Tests

From the root of a shop where the module is installed:

```bash
php vendor/bin/phpunit -c vendor/thelia/modules/QueryBuilder/phpunit.xml.dist --testsuite unit

php bin/test-prepare
php Thelia module:activate QueryBuilder
php vendor/bin/phpunit -c vendor/thelia/modules/QueryBuilder/phpunit.xml.dist --testsuite integration
```

The unit suite also runs outside a shop, against the vendor of a Thelia checkout:

```bash
THELIA_VENDOR_AUTOLOAD=/path/to/thelia/vendor/autoload.php php phpunit.phar -c phpunit.xml.dist --testsuite unit
```

`.github/workflows/ci.yml` installs a fresh `thelia/thelia-project` shop, requires the module from the checkout and runs both suites.

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
