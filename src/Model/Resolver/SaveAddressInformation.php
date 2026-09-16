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

use Exception;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\InventoryInStorePickupApi\Api\Data\PickupLocationInterface;
use Magento\Quote\Api\Data\AddressExtensionInterfaceFactory;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Magento\Quote\Api\Data\TotalsItemInterface;
use Magento\Quote\Model\Cart\Totals;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use ScandiPWA\QuoteGraphQl\Model\Cart\AddressInputValidator;

class SaveAddressInformation implements ResolverInterface
{
    // core keeps this one private, on Carrier\InStorePickup, so the fork carries its own copy
    protected const string PICKUP_METHOD_CODE  = 'pickup';

    // Zero Subtotal Checkout's own method code
    protected const string FREE_PAYMENT = 'free';

    /**
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingInformationInterfaceFactory $shippingInformation
     * @param ParamOverriderCartId $overriderCartId
     * @param AddressInterfaceFactory $addressInterfaceFactory
     * @param AddressExtensionInterfaceFactory $addressExtensionInterfaceFactory
     * @param GetCartForUser $getCartForUser
     * @param AddressInputValidator $addressInputValidator
     */
    public function __construct(
        private readonly ShippingInformationManagementInterface $shippingInformationManagement,
        private readonly ShippingInformationInterfaceFactory $shippingInformation,
        private readonly ParamOverriderCartId $overriderCartId,
        private readonly AddressInterfaceFactory $addressInterfaceFactory,
        private readonly AddressExtensionInterfaceFactory $addressExtensionInterfaceFactory,
        private readonly GetCartForUser $getCartForUser,
        private readonly AddressInputValidator $addressInputValidator
    ) {}

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
     * @throws Exception
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $requestAddressInformation = $args['addressInformation'];

        [
            ShippingInformationInterface::SHIPPING_ADDRESS => $shippingAddress,
            ShippingInformationInterface::BILLING_ADDRESS => $billingAddress,
            ShippingInformationInterface::SHIPPING_CARRIER_CODE => $shippingCarrierCode,
            ShippingInformationInterface::SHIPPING_METHOD_CODE => $shippingMethodCode
        ] = $requestAddressInformation;

        $this->addressInputValidator->validate($shippingAddress, 'shipping');
        $this->addressInputValidator->validate($billingAddress, 'billing');

        $cartId = $this->resolveCartId($args, $context);

        $shippingAddressObject = $this->addressInterfaceFactory->create(['data' => $shippingAddress]);
        $billingAddressObject = $this->addressInterfaceFactory->create(['data' => $billingAddress]);

        if ($shippingMethodCode === self::PICKUP_METHOD_CODE && isset($shippingAddress['extension_attributes'])) {
            foreach ($shippingAddress['extension_attributes'] as $attribute) {
                if ($attribute['attribute_code'] === PickupLocationInterface::PICKUP_LOCATION_CODE) {
                    $shippingAddressObject->setExtensionAttributes(
                        $this->addressExtensionInterfaceFactory->create()->setPickupLocationCode($attribute['value'])
                    );

                    break;
                }
            }
        }

        $addressInformation = $this->shippingInformation->create([
            'data' => [
                'shipping_address' => $shippingAddressObject,
                'billing_address' => $billingAddressObject,
                'shipping_carrier_code' => $shippingCarrierCode,
                'shipping_method_code' => $shippingMethodCode
            ]
        ]);

        return $this->requestPaymentMethods($addressInformation, $cartId);
    }

    /**
     * @param ShippingInformationInterface $addressInformation
     * @param int $cartId
     * @return array
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    protected function requestPaymentMethods(ShippingInformationInterface $addressInformation, int $cartId): array
    {
        try {
            $rawPaymentInformation = $this->shippingInformationManagement
                ->saveAddressInformation($cartId, $addressInformation);
        } catch (NoSuchEntityException $e) {
            // core reports a missing carrier or shipping method here, which is the caller's input to fix
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        } catch (InputException | StateException $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }

        /** @var Totals $rawTotals */
        $rawTotals = $rawPaymentInformation->getTotals();

        // grand_total on the totals object is stale, so it is taken from the segments: magento/magento2#7769
        $rawTotals->setGrandTotal($rawTotals->getTotalSegments()['grand_total']['value']);
        $totals = array_merge(
            $rawTotals->getData(),
            ['items' => array_map(function ($item) {
                /** @var TotalsItemInterface $item */
                return [
                    TotalsItemInterface::KEY_ITEM_ID => $item->getItemId(),
                    TotalsItemInterface::KEY_PRICE => $item->getPrice(),
                    TotalsItemInterface::KEY_BASE_PRICE => $item->getBasePrice(),
                    TotalsItemInterface::KEY_QTY => $item->getQty(),
                    TotalsItemInterface::KEY_ROW_TOTAL => $item->getRowTotal(),
                    TotalsItemInterface::KEY_BASE_ROW_TOTAL => $item->getBaseRowTotal(),
                    TotalsItemInterface::KEY_ROW_TOTAL_WITH_DISCOUNT => $item->getRowTotalWithDiscount(),
                    TotalsItemInterface::KEY_TAX_AMOUNT => $item->getTaxAmount(),
                    TotalsItemInterface::KEY_BASE_TAX_AMOUNT => $item->getBaseTaxAmount(),
                    TotalsItemInterface::KEY_TAX_PERCENT => $item->getTaxPercent(),
                    TotalsItemInterface::KEY_DISCOUNT_AMOUNT => $item->getDiscountAmount(),
                    TotalsItemInterface::KEY_BASE_DISCOUNT_AMOUNT => $item->getBaseDiscountAmount(),
                    TotalsItemInterface::KEY_DISCOUNT_PERCENT => $item->getDiscountPercent(),
                    TotalsItemInterface::KEY_PRICE_INCL_TAX => $item->getPriceInclTax(),
                    TotalsItemInterface::KEY_BASE_PRICE_INCL_TAX => $item->getBasePriceInclTax(),
                    TotalsItemInterface::KEY_ROW_TOTAL_INCL_TAX => $item->getRowTotalInclTax(),
                    TotalsItemInterface::KEY_BASE_ROW_TOTAL_INCL_TAX => $item->getBaseRowTotalInclTax(),
                    TotalsItemInterface::KEY_OPTIONS => $item->getOptions(),
                    TotalsItemInterface::KEY_WEEE_TAX_APPLIED_AMOUNT => $item->getWeeeTaxAppliedAmount(),
                    TotalsItemInterface::KEY_WEEE_TAX_APPLIED => $item->getWeeeTaxApplied(),
                    TotalsItemInterface::KEY_NAME => $item->getName(),
                ];
            }, $rawTotals->getItems())]
        );

        $paymentMethods = $rawPaymentInformation->getPaymentMethods();

        // a zero-total order can only be paid the free way, so that method replaces the whole list
        foreach ($paymentMethods as $payment) {
            if ($payment->getCode() === self::FREE_PAYMENT) {
                return [
                    'payment_methods' => [
                        [
                            'code' => $payment->getCode(),
                            'title' => $payment->getTitle()
                        ]
                    ],
                    'totals' => $totals
                ];
            }
        }

        return [
            'payment_methods' => array_map(
                function ($payment) {
                    /** @var PaymentMethodInterface $payment */
                    return [
                        'code' => $payment->getCode(),
                        'title' => $payment->getTitle(),
                    ];
                },
                $paymentMethods
            ),
            'totals' => $totals
        ];
    }

    /**
     * the same guard core's cart mutations use, so a masked id the caller does not hold is refused before any write
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
