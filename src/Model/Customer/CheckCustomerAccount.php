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

namespace ScandiPWA\QuoteGraphQl\Model\Customer;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;

class CheckCustomerAccount
{
    /**
     * @param AuthenticationInterface $authentication
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
        private readonly AuthenticationInterface $authentication,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountManagementInterface $accountManagement
    ) {}

    /**
     * @param int|null $customerId
     * @param int|null $customerType
     * @return bool
     * @throws GraphQlAuthenticationException
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(?int $customerId, ?int $customerType): bool
    {
        if ($this->isCustomerGuest($customerId, $customerType)) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        try {
            $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(
                __('Customer with id "%customer_id" does not exist.', ['customer_id' => $customerId]),
                $e
            );
        }

        if ($this->authentication->isLocked($customerId)) {
            throw new GraphQlAuthenticationException(__('The account is locked.'));
        }

        $confirmationStatus = $this->accountManagement->getConfirmationStatus($customerId);
        if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
            throw new GraphQlAuthenticationException(__("This account isn't confirmed. Verify and try again."));
        }

        return true;
    }

    /**
     * @param int|null $customerId
     * @param int|null $customerType
     * @return bool
     */
    private function isCustomerGuest(?int $customerId, ?int $customerType): bool
    {
        if ($customerId === null || $customerType === null) {
            return true;
        }

        return $customerId == 0 || $customerType == UserContextInterface::USER_TYPE_GUEST;
    }
}
