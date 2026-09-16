<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\QuoteGraphQl\Model\Resolver;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class LinkOrder implements ResolverInterface
{
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession $checkoutSession
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {}

    /**
     * link the session's guest order to the signed-in customer
     * {@inheritdoc}
     * @param ContextInterface $context
     * @throws GraphQlAuthorizationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $customer = $this->getSignedInCustomer($context);

        // an order may only be linked to the account making the call, whatever address the mutation names
        if (strcasecmp((string)($args['customer_email'] ?? ''), (string)$customer->getEmail()) !== 0) {
            throw new GraphQlAuthorizationException(
                __('An order can only be linked to the email address of the signed-in customer.')
            );
        }

        $order = $this->checkoutSession->getLastRealOrder();

        if (!$this->validateOrder($order, $customer)) {
            return false;
        }

        $order->setCustomerId($customer->getId());
        $order->setCustomerIsGuest(0);
        $this->orderRepository->save($order);

        return true;
    }

    /**
     * @param Order $order
     * @param CustomerInterface $customer
     * @return bool
     */
    public function validateOrder($order, $customer): bool
    {
        if (!$order->getId() || $order->getCustomerId()) {
            return false;
        }

        if ((int)$order->getStoreId() !== (int)$customer->getStoreId()) {
            return false;
        }

        return true;
    }

    /**
     * @param ContextInterface $context
     * @return CustomerInterface
     * @throws GraphQlAuthorizationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getSignedInCustomer(ContextInterface $context): CustomerInterface
    {
        $customerId = (int)$context->getUserId();

        if (!$customerId) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        return $this->customerRepository->getById($customerId);
    }
}
