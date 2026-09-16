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

namespace ScandiPWA\QuoteGraphQl\Model\Product\Option\Type\File;

use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\Product\Option\Type\File\ValidatorFile as OriginalValidatorFile;
use Magento\Framework\DataObject;

class ValidatorFile extends OriginalValidatorFile
{
    /**
     * no-op: the file arrives here as base64 JSON, never in $_FILES, so core's validation cannot run
     * @param DataObject $processingParams
     * @param Option $option
     * @return null
     */
    public function validate($processingParams, $option)
    {
        return null;
    }
}
