<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\QuoteGraphQl\Model\Cart\BuyRequest;

use Magento\Quote\Model\Cart\Data\CartItem;

class GroupedDataProvider
{
    private const string OPTION_TYPE = 'grouped';

    /**
     * @param CartItem $cartItem
     * @return array|array[]
     * @phpcs:disable Magento2.Functions.DiscouragedFunction
     */
    public function execute(CartItem $cartItem): array
    {
        $groupedData = [];

        foreach ($cartItem->getSelectedOptions() as $optionData) {
            $optionData = \explode('/', base64_decode($optionData->getId()));

            if ($this->isProviderApplicable($optionData) === false) {
                continue;
            }

            [, $simpleProductId, $quantity] = $optionData;

            $groupedData[$simpleProductId] = $quantity;
        }

        if (empty($groupedData)) {
            return $groupedData;
        }

        return ['super_group' => $groupedData];
    }

    /**
     * @param array $optionData
     * @return bool
     */
    private function isProviderApplicable(array $optionData): bool
    {
        return $optionData[0] === self::OPTION_TYPE;
    }
}
