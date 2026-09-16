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
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\App\Emulation;
use ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

class ProductResolver implements ResolverInterface
{
    use ResolveInfoFieldsTrait;

    /**
     * @param ProductRepository $productRepository
     * @param Product $productDataProvider
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPostProcessor $postProcessor
     * @param AttributeFactory $attributeFactory
     * @param Emulation $emulation
     * @param HelperImage $helperImage
     */
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly Product $productDataProvider,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DataPostProcessor $postProcessor,
        private readonly AttributeFactory $attributeFactory,
        private readonly Emulation $emulation,
        private readonly HelperImage $helperImage
    ) {}

    /**
     * the products of an order, resolved in one pass over the whole item list
     * {@inheritdoc}
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!isset($value['products'])) {
            return [];
        }

        $productIds = array_map(function ($item) {
            return $item['product_id'];
        }, $value['products']);

        $attributeCodes = $this->getFieldsFromProductInfo($info, 'order_products');

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();

        $products = $this->productDataProvider
            ->getList(
                $searchCriteria,
                $attributeCodes,
                false,
                true
            )
            ->getItems();

        $productsData = $this->postProcessor->process(
            $products,
            'order_products',
            $info
        );

        $data = [];

        foreach ($value['products'] as $key => $item) {
            $productId = $item->getProductId();

            $productItem = $productsData[$productId] ?? $this->getEmptyProductItem($item);

            /** @var Item $item */
            $data[$key] = $productItem;
            $data[$key]['qty'] = $item->getQtyOrdered();
            $data[$key]['row_total'] = $item->getRowTotalInclTax();
            $data[$key]['original_price'] = $item->getOriginalPrice();
            $data[$key]['license_key'] = $item['license_key'];
        }

        return $data;
    }

    /**
     * @param Item $item
     * @return array
     */
    protected function getEmptyProductItem(Item $item): array
    {
        $this->emulation->startEnvironmentEmulation(
            (int)$item->getStoreId(),
            Area::AREA_FRONTEND,
            true
        );

        $result = [
            'name' => $item->getName(),
            'entity_id' => $item->getProductId(),
            'type_id' => 'simple',
            'model' => $this->attributeFactory->create(),
            'image' => [
                'path' => '',
                'label' => '',
                'url' => $this->helperImage->getDefaultPlaceholderUrl('image')
            ],
            'small_image' => [
                'path' => '',
                'label' => '',
                'url' => $this->helperImage->getDefaultPlaceholderUrl('small_image')
            ],
            'thumbnail' => [
                'path' => '',
                'label' => '',
                'url' => $this->helperImage->getDefaultPlaceholderUrl('thumbnail')
            ]
        ];

        $this->emulation->stopEnvironmentEmulation();

        return $result;
    }
}
