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

namespace ScandiPWA\QuoteGraphQl\Model\Cart;

use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser as SourceGetCartForUser;

class GetCartForUser extends SourceGetCartForUser
{
    public const string ERROR_TYPE = 'stock';
    public const string ERROR_MESSAGE = 'Some of the products are out of stock.';

    /**
     * {@inheritdoc}
     */
    public function execute(string $cartHash, ?int $customerId, int $storeId): Quote
    {
        $cart = parent::execute($cartHash, $customerId, $storeId);

        // magento raises this as a notification, but the PWA reads any cart error as fatal
        $cart->removeMessageByText(self::ERROR_TYPE, self::ERROR_MESSAGE);

        return $cart;
    }
}
