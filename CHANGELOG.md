# Changelog

## 4.0.1

- A quote keeps `customer_is_guest` false for a signed-in customer. The flag is computed from `customer_id` alone, where it previously also read `$_customer`, which is only populated once `getCustomer()` has run.

## 4.0.0

Forked from `scandipwa/quote-graphql` 3.1.0. Module name and namespace are unchanged, and the package replaces `scandipwa/quote-graphql` at every version, so it installs as a drop-in replacement.

- `getPaymentMethods`, `estimateShippingCosts` and `saveAddressInformation` now resolve the cart through core's own ownership guard, so a masked cart id the caller does not hold is refused instead of read and written.
- `getOrderById` answers one indistinguishable not-found for an order that does not exist and for an order that is not the caller's, so order ids can no longer be enumerated.
- `linkOrder` links only to the signed-in customer's own account; it refuses any other email address before reading the session or the order.
- `saveAddressInformation` and `estimateShippingCosts` validate the address through core's cart-mutation validation and answer an actionable input error naming the field, where a bad country, a foreign region or an over-long value previously produced an internal error or was accepted silently.
- Add-to-cart refusals surface again: the `Magento\Quote\Model\Quote` override returns what core returns, so a refusal core reports as a message is no longer discarded as a silent success.
- Bundle item quantities accumulate as core's do; the `Model\Bundle\Type` override that repeated the parent's work and dropped the accumulation is removed, together with its preference and the class itself.
- The guest-cart merge observer is removed: the event it listened to is dispatched nowhere, and the theme merges through core's `mergeCarts`.
- Two classes that only restated core, `Cart\CheckCartCheckoutAllowance` and `Cart\BuyRequest\BundleDataProvider`, are removed with their preferences.
- File-option validation is restored store-wide: core's validator runs again for admin and storefront uploads, and the GraphQL base64 path validates its own uploads against the option's extension list, refuses one that is oversize or has no name, caps the stored title and reports a failed write as a failure.
- The first item added to a cart now resolves its product inside the add-to-cart response itself.
- Estimating shipping no longer saves the quote.
- The unused `Helper\ImageUpload` and `Resolver\CartCouponException` classes are removed.
- `getStores` no longer reads `search` or `country` before checking they exist, so a call omitting either returns the intended `GraphQlInputException` instead of tripping an undefined-key warning first.
- Removed the dead `TotalsObject` type resolver, an unimplemented stub whose `resolveType()` always returned `''`, together with the `interface TotalsObject` it backed in `schema.graphqls`; `PaymentTotals` now declares its fields directly.
- `getOrderList` and `getOrderById` now return `order_number` and `order_id`. Both merge their fields into core's `Order` type, where `order_number` is non-nullable, so a client selecting it previously received a null-for-non-nullable error that nulled the entire query.
- Customizable-option file uploads no longer take any part of their storage path from the client: the submitted name is reduced with `basename()` and checked against an extension allow-list, then kept only as the display title, and the bytes are written under a generated random name.
- Wrapped the `module.xml` dependency entries in `<sequence>`; as bare children of `<module>` they did not validate against `module.xsd` and never affected load order.
- `PaymentTotals` still answers null for `items_count`, `customer_is_guest`, `store_id`, `shipping_address`, `minimum_order_amount`, `is_in_store_pickup_available`, `applied_rule_ids` and `coupon_code`; the storefront reads none of them.
- `getPaymentMethods`, `getOrderList` and `getOrderById` declare `@cache(cacheable: false)` because each answers from the caller's cart, order history or session, and `getBraintreeConfig`, `getCartDisplayConfig` and `getStores` declare cache identities returning the store-configuration and pickup-location tags, so a store without a cacheability allow-list neither pools a bound answer nor leaves a public one uninvalidated.
- A re-submitted file option validates again: its secret key is the one Magento derives from the stored file, where the generated file name was kept before and could never match.
