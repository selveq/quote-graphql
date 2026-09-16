<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\Resolver\GetStores;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;
use Magento\InventoryInStorePickupGraphQl\Model\Resolver\PickupLocations\Identity as PickupLocationsIdentity;

class Identity implements IdentityInterface
{
    /**
     * {@inheritdoc}
     */
    public function getIdentities(array $resolvedData): array
    {
        $ids = [];

        foreach ($resolvedData['stores'] ?? [] as $store) {
            $ids[] = sprintf('%s_%s', PickupLocationsIdentity::CACHE_TAG, $store['pickup_location_code']);
        }

        if (!$ids) {
            return [];
        }

        array_unshift($ids, PickupLocationsIdentity::CACHE_TAG);

        return $ids;
    }
}
