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
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory as TrackCollectionFactory;
use ScandiPWA\QuoteGraphQl\Model\Customer\CheckCustomerAccount;

class OrderListResolver implements ResolverInterface
{
    /**
     * @param CollectionFactoryInterface $collectionFactory
     * @param CheckCustomerAccount $checkCustomerAccount
     * @param TrackCollectionFactory $trackCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactoryInterface $collectionFactory,
        private readonly CheckCustomerAccount $checkCustomerAccount,
        private readonly TrackCollectionFactory $trackCollectionFactory
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
        $items = [];

        $customerId = $context->getUserId();

        $this->checkCustomerAccount->execute($customerId, $context->getUserType());

        $orders = $this->collectionFactory->create($customerId);

        $orderIds = [];
        foreach ($orders as $order) {
            $orderIds[] = (int)$order->getId();
        }

        $trackNumbers = $this->getTrackNumbersByOrder($orderIds);

        foreach ($orders as $order) {
            $shippingInfo = [
                'shipping_amount' => $order->getShippingAmount(),
                'shipping_method' => $order->getShippingMethod(),
                'shipping_address' => $order->getShippingAddress(),
                'shipping_description' => $order->getShippingDescription(),
                'tracking_numbers' => $trackNumbers[(int)$order->getId()] ?? []
            ];

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

            $items[] = [
                // Order.order_number is String! on core's shared Order type, so omitting it nulls the query
                'order_number' => $order->getIncrementId(),
                'order_id' => $order->getId(),
                'base_order_info' => $base_info,
                'shipping_info' => $shippingInfo,
                'payment_info' => $order->getPayment()->getData()
            ];
        }

        return ['items' => $items];
    }

    /**
     * one collection for the whole list: asking each order for its own tracks costs a query per order
     * @param int[] $orderIds
     * @return array
     */
    protected function getTrackNumbersByOrder(array $orderIds): array
    {
        if (!$orderIds) {
            return [];
        }

        $trackNumbers = [];
        $tracks = $this->trackCollectionFactory->create()->addFieldToFilter('order_id', ['in' => $orderIds]);

        foreach ($tracks as $track) {
            $trackNumbers[(int)$track->getOrderId()][] = $track->getTrackNumber();
        }

        return $trackNumbers;
    }
}
