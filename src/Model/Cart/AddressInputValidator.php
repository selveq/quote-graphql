<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Cart;

use Magento\Framework\Exception\InputException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Quote\Model\Address\Validator\AddressAttributeValidatorInterface;
use Magento\QuoteGraphQl\Model\Cart\QuoteAddressFactory;

class AddressInputValidator
{
    /**
     * @param QuoteAddressFactory $quoteAddressFactory
     * @param AddressAttributeValidatorInterface $addressAttributeValidator
     */
    public function __construct(
        private readonly QuoteAddressFactory $quoteAddressFactory,
        private readonly AddressAttributeValidatorInterface $addressAttributeValidator
    ) {}

    /**
     * run over a checkout address the validation core's own cart mutations run
     * @param array $addressInput
     * @param string $addressType
     * @return void
     * @throws GraphQlInputException
     */
    public function validate(array $addressInput, string $addressType): void
    {
        // core keys its cart-mutation validation on country_code and reads street unguarded
        $addressInput['country_code'] = $addressInput['country_id'] ?? '';
        $addressInput['street'] = $addressInput['street'] ?? [];

        try {
            $this->addressAttributeValidator->validate(
                $this->quoteAddressFactory->createBasedOnInputData($addressInput),
                $addressType
            );
        } catch (InputException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }
    }
}
