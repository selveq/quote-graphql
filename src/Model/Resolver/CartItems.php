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

use Magento\Catalog\Helper\Image as HelperImage;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\QuoteGraphQl\Model\CartItem\GetItemsData;
use Magento\QuoteGraphQl\Model\Resolver\CartItems as SourceCartItems;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

class CartItems extends SourceCartItems
{
    /**
     * @param GetItemsData $getItemsData
     * @param Uid $uidEncoder
     * @param Emulation $emulation
     * @param HelperImage $helperImage
     * @param StoreManagerInterface $storeManager
     * @param DataPostProcessor $productPostProcessor
     */
    public function __construct(
        private readonly GetItemsData $getItemsData,
        private readonly Uid $uidEncoder,
        private readonly Emulation $emulation,
        private readonly HelperImage $helperImage,
        private readonly StoreManagerInterface $storeManager,
        private readonly DataPostProcessor $productPostProcessor
    ) {
        parent::__construct(
            $uidEncoder,
            $getItemsData
        );
    }

    /**
     * {@inheritdoc}
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
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $cart = $value['model'];
        $result = [];

        if ($cart->getData('has_error')) {
            $errors = $cart->getErrors();

            foreach ($errors as $error) {
                $result[] = new GraphQlInputException(__($error->getText()));
            }
        }

        $cartProductsData = $this->getCartProductsData($cart, $info);
        $cartItems = $cart->getAllVisibleItems();

        $storeId = $this->storeManager->getStore()->getId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        /** @var QuoteItem $cartItem */
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();
            $productId = $product->getId();

            if (!isset($cartProductsData[$productId])) {
                $result[] = new GraphQlNoSuchEntityException(
                    __("The product that was requested doesn't exist. Verify the product and try again.")
                );

                continue;
            }

            $productData = array_merge($cartProductsData[$productId],
                [
                    'thumbnail' => [
                        'path' => $product->getThumbnail(),
                        'url' => $this->getImageUrl('thumbnail', $product->getThumbnail(), $product)
                    ]
                ]
            );

            $result[] = [
                'id' => $cartItem->getItemId(),
                'sku' => $cartItem->getSku(),
                'uid' => $this->uidEncoder->encode((string)$cartItem->getItemId()),
                'quantity' => $cartItem->getQty(),
                'product' => $productData,
                'model' => $cartItem,
            ];
        }

        $this->emulation->stopEnvironmentEmulation();

        return $result;
    }

    /**
     * @param Quote $cart
     * @param ResolveInfo $info
     * @return array
     */
    public function getCartProductsData(Quote $cart, ResolveInfo $info): array
    {
        // getItems() serves the data the quote was loaded with, so a just-added item is missing from it
        $products = $this->getCartProducts($cart->getAllVisibleItems());
        $productsData = [];

        $productsPostData = $this->productPostProcessor->process(
            $products,
            'items/product',
            $info,
            ['isCartProduct' => true]
        );

        foreach ($products as $product) {
            $productId = $product->getId();

            $productsData[$productId] = $productsPostData[$productId];
            $productsData[$productId]['model'] = $product;
            $productsData[$productId]['uid'] = $this->uidEncoder->encode((string)$productId);
        }

        return $productsData;
    }

    /**
     * @param string $imageType
     * @param string|null $imagePath
     * @param mixed $product
     * @return string
     */
    protected function getImageUrl(
        string $imageType,
        ?string $imagePath,
        $product
    ): string {
        if (!isset($imagePath)) {
            return $this->helperImage->getDefaultPlaceholderUrl($imageType);
        }

        $imageId = sprintf('scandipwa_%s', $imageType);

        $image = $this->helperImage
            ->init(
                $product,
                $imageId,
                ['type' => $imageType]
            )
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false);

        return $image->getUrl();
    }

    /**
     * @param QuoteItem[] $cartItems
     * @return array
     */
    private function getCartProducts(array $cartItems): array
    {
        $itemsData = [];
        foreach ($cartItems as $cartItem) {
            $itemsData[] = $cartItem->getProduct();
        }
        return $itemsData;
    }
}
