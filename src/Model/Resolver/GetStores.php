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

use Magento\Directory\Model\Country\Postcode\ConfigInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryInStorePickup\Model\GetPickupLocations;
use Magento\InventoryInStorePickupApi\Api\Data\SearchRequestInterface;
use Magento\InventoryInStorePickupApi\Model\SearchRequestBuilderInterface;
use Magento\InventoryInStorePickupGraphQl\Model\Resolver\PickupLocations\SearchRequestBuilder\ExtensionProvider;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Model\StoreManagerInterface;

class GetStores implements ResolverInterface
{
    // core keeps this path private, so the radius is read through a copy of it
    protected const string SEARCH_RADIUS = 'carriers/instore/search_radius';

    /**
     * @var string[]
     */
    private array $countryNames = [];

    /**
     * @param SearchRequestBuilderInterface $searchRequest
     * @param GetPickupLocations $getPickupLocations
     * @param CountryFactory $countryFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $postCodesConfig
     * @param ExtensionProvider $extensionProvider
     */
    public function __construct(
        private readonly SearchRequestBuilderInterface $searchRequest,
        private readonly GetPickupLocations $getPickupLocations,
        private readonly CountryFactory $countryFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ConfigInterface $postCodesConfig,
        private readonly ExtensionProvider $extensionProvider
    ) {}

    /**
     * {@inheritdoc}
     * @throws GraphQlInputException
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
        $search = $args['search'] ?? null;
        $country = $args['country'] ?? null;

        if (!isset($search) || !isset($country)) {
            throw new GraphQlInputException(
                __('Required parameter "search" or "country" is missing.')
            );
        }

        $result = [];

        if ($search === '') {
            $searchRequest = $this->getAllStores($args);
        } else {
            $postCodes = $this->postCodesConfig->getPostCodes();

            if (!isset($postCodes[$country])) {
                throw new GraphQlInputException(
                    __('No in-delivery support for provided country. Please select another country.')
                );
            }

            $searchRequest = $this->getStoresBySearch($args);
        }

        try {
            $searchResponse = $this->getPickupLocations->execute($searchRequest);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }

        foreach ($searchResponse->getItems() as $item) {
            $result[] = [
                'city' => $item->getCity(),
                'country' => $this->countryName($item->getCountryId()),
                'description' => $item->getDescription(),
                'name' => $item->getName(),
                'phone' => $item->getPhone(),
                'pickup_location_code' => $item->getPickupLocationCode(),
                'postcode' => $item->getPostcode(),
                'region' => $item->getRegion(),
                'street' => $item->getStreet()
            ];
        }

        return ['stores' => $result];
    }

    /**
     * a page of pickup locations repeats the same few country codes, so each one is loaded once
     * @param string|null $countryId
     * @return string|null
     */
    protected function countryName(?string $countryId): ?string
    {
        if (!$countryId) {
            return null;
        }

        if (!array_key_exists($countryId, $this->countryNames)) {
            $this->countryNames[$countryId] = $this->countryFactory->create()->loadByCode($countryId)->getName();
        }

        return $this->countryNames[$countryId];
    }

    /**
     * @param array $args
     * @return SearchRequestInterface
     * @throws LocalizedException
     */
    public function getAllStores($args)
    {
        return $this->searchRequest
            ->setScopeType(SalesChannelInterface::TYPE_WEBSITE)
            ->setScopeCode($this->storeManager->getWebsite()->getCode())
            ->setPageSize(50)
            ->setSearchRequestExtension($this->extensionProvider->getExtensionAttributes($args))
            ->create();
    }

    /**
     * @param array $args
     * @return SearchRequestInterface
     * @throws LocalizedException
     */
    public function getStoresBySearch($args)
    {
        return $this->searchRequest
            ->setScopeType(SalesChannelInterface::TYPE_WEBSITE)
            ->setAreaSearchTerm(sprintf(
                '%s:%s',
                $args['search'],
                $args['country']
            ))
            ->setScopeCode($this->storeManager->getWebsite()->getCode())
            ->setAreaRadius((int)$this->scopeConfig->getValue(self::SEARCH_RADIUS))
            ->setPageSize(50)
            ->setSearchRequestExtension($this->extensionProvider->getExtensionAttributes($args))
            ->create();
    }
}
