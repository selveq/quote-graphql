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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class GetPaymentMethods implements ResolverInterface
{
    // Zero Subtotal Checkout's own method code
    public const string FREE_PAYMENT = 'free';

    /**
     * @param PaymentMethodManagementInterface $paymentMethodManagement
     * @param ParamOverriderCartId $overriderCartId
     * @param GetCartForUser $getCartForUser
     */
    public function __construct(
        private readonly PaymentMethodManagementInterface $paymentMethodManagement,
        private readonly ParamOverriderCartId $overriderCartId,
        private readonly GetCartForUser $getCartForUser
    ) {}

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $cartId = $this->resolveCartId($args, $context);
        $paymentMethods = $this->paymentMethodManagement->getList($cartId);

        // a zero-total order can only be paid the free way, so that method replaces the whole list
        foreach ($paymentMethods as $payment) {
            if ($payment->getCode() === self::FREE_PAYMENT) {
                return [
                    [
                        'code' => $payment->getCode(),
                        'title' => $payment->getTitle(),
                    ]
                ];
            }
        }

        return array_map(
            function ($payment) {
                /** @var PaymentMethodInterface $payment */
                return [
                    'code' => $payment->getCode(),
                    'title' => $payment->getTitle(),
                ];
            },
            $paymentMethods
        );
    }

    /**
     * the same guard core's cart mutations use, so a masked id the caller does not hold is refused before any read
     * @param array|null $args
     * @param ContextInterface $context
     * @return int
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     */
    protected function resolveCartId(?array $args, ContextInterface $context): int
    {
        if (isset($args['guestCartId'])) {
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

            return (int)$this->getCartForUser
                ->execute((string)$args['guestCartId'], $context->getUserId(), $storeId)
                ->getId();
        }

        $cartId = $this->overriderCartId->getOverriddenValue();
        if (!$cartId) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        return (int)$cartId;
    }
}
