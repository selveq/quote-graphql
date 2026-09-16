# ScandiPWA QuoteGraphQl

Fork of [scandipwa/quote-graphql](https://github.com/scandipwa/quote-graphql) 3.1.0, maintained by Selveq for Magento 2.4.9 and PHP 8.3. Module name and namespace are unchanged, and the package replaces `scandipwa/quote-graphql` at every version, so it installs as a drop-in replacement. Selveq is not affiliated with or endorsed by Scandiweb.

## What it does

- Serves the checkout the ScandiPWA theme drives: `estimateShippingCosts` for rates, `saveAddressInformation` for the payment methods and totals that follow an address, `getPaymentMethods`, `getCartDisplayConfig`, and the cart fields the theme reads — prices, and items whose product data comes through `selveq/performance`'s post-processors.
- Answers the order queries `getOrderById`, `getOrderList` and `linkOrder`, each bound to the signed-in customer.
- Adds in-store pickup through `getStores`, and file customizable options sent as base64 through Magento's own cart mutations, validated against an extension allow-list and stored under generated names.
- Replaces three Magento classes store-wide: the quote model, so an add-to-cart refusal reaches the caller instead of passing as a silent success; the quote item processor, so a simple child of a bundle takes its parent's quantity rather than accumulating one; and the file option type, so an option's value survives a request that carries no `$_FILES`.

## Install

```sh
composer require selveq/quote-graphql
bin/magento setup:upgrade
```

Cart items answer `selveq/catalog-graphql`'s product fields when that package is installed, and Magento's own otherwise.

## License

[OSL-3.0](LICENSE), the license of the original work. Scandiweb's copyright notices are kept in every file, and each file Selveq changed carries a `Modifications © Selveq` notice.
