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

namespace ScandiPWA\QuoteGraphQl\Model\CartItem\DataProvider\CustomizableOptionValue;

use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Type\Text as TextOptionType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\Option as SelectedOption;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\CustomizableOptionValue\PriceUnitLabel;
use Magento\QuoteGraphQl\Model\CartItem\DataProvider\CustomizableOptionValueInterface;

class File implements CustomizableOptionValueInterface
{
    /**
     * @param PriceUnitLabel $priceUnitLabel
     */
    public function __construct(
        private readonly PriceUnitLabel $priceUnitLabel
    ) {}

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function getData(
        QuoteItem $cartItem,
        Option $option,
        SelectedOption $selectedOption
    ): array {
        /** @var TextOptionType $optionTypeRenderer */
        $optionTypeRenderer = $option->groupFactory($option->getType());
        $optionTypeRenderer->setOption($option);
        $priceValueUnits = $this->priceUnitLabel->getData($option->getPriceType());

        $selectedOptionValueData = [
            'id' => $selectedOption->getId(),
            'label' => '',
            'value' => json_decode($selectedOption->getValue())->title,
            'price' => [
                'type' => strtoupper($option->getPriceType()),
                'units' => $priceValueUnits,
                'value' => $option->getPrice(),
            ],
        ];

        return [$selectedOptionValueData];
    }
}
