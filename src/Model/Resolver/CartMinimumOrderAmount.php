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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote\Validator\MinimumOrderAmount\ValidationMessage;

class CartMinimumOrderAmount implements ResolverInterface
{
    /**
     * @param ValidationMessage $amountValidationMessage
     */
    public function __construct(
        private readonly ValidationMessage $amountValidationMessage
    ) {}

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $quote = $value['model'];

        $minimumOrderAmountReached = $quote->validateMinimumAmount();
        $minimumOrderDescription = $this->amountValidationMessage->getMessage();

        return [
            'minimum_order_amount_reached' => $minimumOrderAmountReached,
            'minimum_order_description' => $minimumOrderDescription
        ];
    }
}
