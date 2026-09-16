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

namespace ScandiPWA\QuoteGraphQl\Model\Quote\Item;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Processor as SourceProcessor;

class Processor extends SourceProcessor
{
    /**
     * {@inheritdoc}
     */
    public function prepare(Item $item, DataObject $request, Product $candidate): void
    {
        // qty is set only once the parent is known, because stock is checked against it
        if ($request->getResetCount() && !$candidate->getStickWithinParent() && $item->getId() == $request->getId()) {
            $item->setData(CartItemInterface::KEY_QTY, 0);
        }

        $parent = $item->getParentItem();

        if ($candidate->getTypeId() !== ProductType::TYPE_BUNDLE
            && $parent
            && $parent->getProductType() === ProductType::TYPE_BUNDLE
        ) {
            $item->setQty($candidate->getCartQty());
        } else {
            $item->addQty($candidate->getCartQty());
        }

        if (!$item->getParentItem() || $item->getParentItem()->isChildrenCalculated()) {
            $item->setPrice($candidate->getFinalPrice());
        }

        $customPrice = $request->getCustomPrice();
        if (!empty($customPrice) && !$candidate->getParentProductId()) {
            $item->setCustomPrice($customPrice);
            $item->setOriginalCustomPrice($customPrice);
        }
    }
}
