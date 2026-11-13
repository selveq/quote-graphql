<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\QuoteGraphQl\Model\Quote;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote as SourceQuote;
use Magento\Quote\Model\Quote\Item;

class Quote extends SourceQuote
{
    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();

        // customer_id alone decides: $_customer is only set once getCustomer() ran, so a registered quote saved earlier read as a guest
        $customerIsGuest = $this->getCustomerId() == null;
        $this->setCustomerIsGuest($customerIsGuest);

        return $this;
    }

    /**
     * @param Product $product
     * @param DataObject|float|null $request
     * @param string|null $processMode
     * @return Item|string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function addProduct(
        Product $product,
        $request = null,
        $processMode = AbstractType::PROCESS_MODE_FULL
    ) {
        // GraphQL resolves products without store scope, so the candidate is re-read the way core's updateItem does
        $candidate = clone $this->productRepository->getById(
            $product->getId(),
            false,
            (int)$this->getStore()->getId()
        );

        return parent::addProduct($candidate, $request, $processMode);
    }
}
