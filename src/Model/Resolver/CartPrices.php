<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Resolver\CartPrices as SourceCartPrices;

class CartPrices extends SourceCartPrices
{
    /**
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $result = parent::resolve($field, $context, $info, $value, $args);

        /** @var Quote $quote */
        $quote = $result['model'];
        $currency = $quote->getQuoteCurrencyCode();

        // collectAddressTotals() already copied the applied taxes onto this address, so no second collect
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();

        return array_merge($result, [
            'applied_rule_ids' => $quote->getAppliedRuleIds(),
            'applied_taxes' => $this->getAppliedTaxes($address->getAppliedTaxes() ?? [], $currency),
            'coupon_code' => $quote->getCouponCode(),
            'quote_currency_code' => $currency
        ]);
    }

    /**
     * the fork's applied-tax shape: one entry per tax id, carrying the percentage the theme prints
     * @param array $appliedTaxes
     * @param string $currency
     * @return array
     */
    protected function getAppliedTaxes(array $appliedTaxes, string $currency): array
    {
        $appliedTaxesData = [];

        foreach ($appliedTaxes as $appliedTax) {
            $appliedTaxesData[] = [
                'label' => $appliedTax['id'],
                'amount' => ['value' => $appliedTax['amount'], 'currency' => $currency],
                'percent' => $appliedTax['percent']
            ];
        }

        return $appliedTaxesData;
    }
}
