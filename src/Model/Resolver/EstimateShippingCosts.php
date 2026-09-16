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
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\EstimateAddressInterfaceFactory;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ShippingMethodManagement;
use Magento\Quote\Model\Webapi\ParamOverriderCartId;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\QuoteGraphQl\Model\Cart\AddressInputValidator;

class EstimateShippingCosts implements ResolverInterface
{
    // the theme estimates while the address form is still being typed, so validation waits for a complete address
    private const REQUIRED_FOR_VALIDATION = [
        'country_id', 'postcode', 'city', 'firstname', 'lastname', 'telephone', 'street'
    ];

    /**
     * @param ShippingMethodManagement $shippingMethodManagement
     * @param ParamOverriderCartId $overriderCartId
     * @param EstimateAddressInterfaceFactory $addressInterfaceFactory
     * @param StoreManagerInterface $storeManager
     * @param QuoteRepository $quoteRepository
     * @param GetCartForUser $getCartForUser
     * @param AddressInputValidator $addressInputValidator
     */
    public function __construct(
        private readonly ShippingMethodManagement $shippingMethodManagement,
        private readonly ParamOverriderCartId $overriderCartId,
        private readonly EstimateAddressInterfaceFactory $addressInterfaceFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly QuoteRepository $quoteRepository,
        private readonly GetCartForUser $getCartForUser,
        private readonly AddressInputValidator $addressInputValidator
    ) {}

    /**
     * {@inheritdoc}
     * @param ContextInterface $context
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
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
        $addressInput = $args['address'] ?? [];
        $this->validateAddressInput($addressInput);

        $cart = $this->resolveCart($args, $context);
        $this->syncStoreId($cart);

        $shippingAddressObject = $this->addressInterfaceFactory->create(['data' => $addressInput]);
        $shippingMethods = $this->shippingMethodManagement
            ->estimateByAddress((int)$cart->getId(), $shippingAddressObject);

        return array_map(function ($shippingMethod) {
            /** @var ShippingMethodInterface $shippingMethod */
            return [
                'carrier_code' => $shippingMethod->getCarrierCode(),
                'method_code' => $shippingMethod->getMethodCode(),
                'carrier_title' => $shippingMethod->getCarrierTitle(),
                'method_title' => $shippingMethod->getMethodTitle(),
                'error_message' => $shippingMethod->getErrorMessage(),
                'amount' => $shippingMethod->getAmount(),
                'base_amount' => $shippingMethod->getBaseAmount(),
                'price_excl_tax' => $shippingMethod->getPriceExclTax(),
                'price_incl_tax' => $shippingMethod->getPriceInclTax(),
                'available' => $shippingMethod->getAvailable()
            ];
        }, $shippingMethods);
    }

    /**
     * the same guard core's cart mutations use, so a masked id the caller does not hold is refused before any read
     * @param array|null $args
     * @param ContextInterface $context
     * @return CartInterface
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     * @throws NoSuchEntityException
     */
    protected function resolveCart(?array $args, ContextInterface $context): CartInterface
    {
        if (isset($args['guestCartId'])) {
            $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();

            return $this->getCartForUser->execute((string)$args['guestCartId'], $context->getUserId(), $storeId);
        }

        $cartId = $this->overriderCartId->getOverriddenValue();
        if (!$cartId) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        return $this->quoteRepository->getActive($cartId);
    }

    /**
     * a quote carried over from another store view must move before its rates are read
     * @param CartInterface $cart
     * @return void
     * @throws NoSuchEntityException
     */
    protected function syncStoreId(CartInterface $cart): void
    {
        // getStore()->getId() is a string and the quote's store id an int, so the comparison has to cast
        $storeId = (int)$this->storeManager->getStore()->getId();
        if ($storeId === (int)$cart->getStoreId()) {
            return;
        }

        $cart->setStoreId($storeId);
        $this->quoteRepository->save($cart);
    }

    /**
     * @param array $addressInput
     * @return void
     * @throws GraphQlInputException
     */
    protected function validateAddressInput(array $addressInput): void
    {
        foreach (self::REQUIRED_FOR_VALIDATION as $key) {
            if (empty($addressInput[$key])) {
                return;
            }
        }

        $this->addressInputValidator->validate($addressInput, 'shipping');
    }
}
