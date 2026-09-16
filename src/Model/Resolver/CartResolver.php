<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Webapi\Controller\Rest\ParamOverriderCustomerId;
use UnexpectedValueException;

abstract class CartResolver implements ResolverInterface
{
    /**
     * @param GuestCartRepositoryInterface $guestCartRepository
     * @param ParamOverriderCustomerId $overriderCustomerId
     * @param CartManagementInterface $quoteManagement
     */
    public function __construct(
        protected readonly GuestCartRepositoryInterface $guestCartRepository,
        protected readonly ParamOverriderCustomerId $overriderCustomerId,
        protected readonly CartManagementInterface $quoteManagement
    ) {}

    /**
     * @param array|null $args
     * @return CartInterface
     * @throws UnexpectedValueException
     */
    protected function getCart(?array $args = null): CartInterface
    {
        $guestCartId = $args['guestCartId'] ?? null;
        $cart = $guestCartId === null
            ? $this->getCartForLoggedInUser()
            : $this->getCartForGuest((string)$guestCartId);

        // magento hands back a new empty cart rather than failing when the id is unknown
        if ($cart->getId() === null) {
            throw new UnexpectedValueException('Unable to retrieve cart, cart ID is null');
        }

        return $cart;
    }

    /**
     * @param string $guestCartId
     * @return CartInterface
     * @throws UnexpectedValueException
     */
    private function getCartForGuest(string $guestCartId)
    {
        try {
            return $this->guestCartRepository->get($guestCartId);
        } catch (NoSuchEntityException $e) {
            throw new UnexpectedValueException("Unable to retrieve cart. guestCardId is invalid", 12, $e);
        }
    }

    /**
     * @return CartInterface
     * @throws UnexpectedValueException
     */
    private function getCartForLoggedInUser()
    {
        try {
            return $this->quoteManagement->getCartForCustomer(
                $this->overriderCustomerId->getOverriddenValue()
            );
        } catch (NoSuchEntityException $e) {
            throw new UnexpectedValueException(
                __("Unable to retrieve cart. guestCartId is missing or you are not logged in"),
                13,
                $e
            );
        }
    }
}
