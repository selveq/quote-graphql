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

namespace ScandiPWA\QuoteGraphQl\Model\Resolver\ShippingAddress;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\QuoteGraphQl\Model\Resolver\ShippingAddress\SelectedShippingMethod as SourceSelectedShippingMethod;

class SelectedShippingMethod extends SourceSelectedShippingMethod
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

        if (!isset($result)) {
            return null;
        }

        $address = $value['model'];

        return array_merge($result, [
            'address' => [
                'city' => $address->getCity(),
                'country' => [
                    'code' => $address->getCountryId()
                ],
                'email' => $address->getEmail(),
                'firstname' => $address->getFirstname(),
                'lastname' => $address->getLastname(),
                'postcode' => $address->getPostcode(),
                'region' => [
                    'label' => $address->getRegion()
                ],
                'street' => $address->getStreet(),
                'telephone' => $address->getTelephone(),
                'vat_id' => $address->getVatId()
            ],
            'amount_incl_tax' => $address->getShippingInclTax(),
            'tax_amount' => $address->getShippingTaxAmount()
        ]);
    }
}
