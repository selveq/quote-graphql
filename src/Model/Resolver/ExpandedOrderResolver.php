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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use ScandiPWA\QuoteGraphQl\Model\Customer\CheckCustomerAccount;

class ExpandedOrderResolver implements ResolverInterface
{
    // one message for "no such order" and "not yours" alike, so order ids cannot be enumerated
    private const ORDER_NOT_FOUND = 'Order id %1 does not exist.';

    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param CheckCustomerAccount $checkCustomerAccount
     * @param OrderRepository $orderRepository
     */
    public function __construct(
        private readonly CollectionFactoryInterface $collectionFactory,
        private readonly CheckCustomerAccount $checkCustomerAccount,
        private readonly OrderRepository $orderRepository
    ) {}

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
     * @throws GraphQlAuthenticationException
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $itemsData = [];
        $trackNumbers = [];

        $customerId = (int)$context->getUserId();
        $this->checkCustomerAccount->execute($customerId, $context->getUserType());

        $orderId = (int)$args['id'];

        try {
            /** @var Order $order */
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__(self::ORDER_NOT_FOUND, $orderId), $e);
        }

        if ($customerId !== (int)$order->getCustomerId()) {
            throw new GraphQlNoSuchEntityException(__(self::ORDER_NOT_FOUND, $orderId));
        }

        foreach ($order->getAllVisibleItems() as $item) {
            $itemsData[] = $item;
        }

        $tracksCollection = $order->getTracksCollection();

        foreach ($tracksCollection->getItems() as $track) {
            $trackNumbers[] = $track->getTrackNumber();
        }

        $shippingInfo = [
            'shipping_amount' => $order->getShippingAmount(),
            'shipping_incl_tax' => $order->getShippingInclTax(),
            'shipping_method' => $order->getShippingMethod(),
            'shipping_address' => $order->getShippingAddress(),
            'shipping_description' => $order->getShippingDescription(),
            'tracking_numbers' => $trackNumbers
        ];

        /** @var OrderPayment $payment */
        $payment = $order->getPayment();

        $base_info = [
            'id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'created_at' => $order->getCreatedAt(),
            'grand_total' => $order->getGrandTotal(),
            'sub_total' => $order->getSubtotalInclTax(),
            'currency_code' => $order->getOrderCurrencyCode(),
            'status' => $order->getStatus(),
            'status_label' => $order->getStatusLabel(),
            'total_qty_ordered' => $order->getTotalQtyOrdered(),
        ];

        return [
            // Order.order_number is String! on core's shared Order type, so omitting it nulls the query
            'order_number' => $order->getIncrementId(),
            'order_id' => $order->getId(),
            'base_order_info' => $base_info,
            'shipping_info' => $shippingInfo,
            'payment_info' => $payment->getData(),
            'products' => $itemsData
        ];
    }
}
