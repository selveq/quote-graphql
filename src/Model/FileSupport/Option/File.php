<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright 2013 Adobe. All Rights Reserved.
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\QuoteGraphQl\Model\FileSupport\Option;

use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product\Option\Type\File as SourceFile;
use Magento\Catalog\Model\Product\Option\Type\File\ValidatorFile;
use Magento\Catalog\Model\Product\Option\Type\File\ValidatorInfo;
use Magento\Catalog\Model\Product\Option\UrlBuilder;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\Quote\Model\Quote\Item\OptionFactory;

class File extends SourceFile
{
    /**
     * @var Json
     */
    protected $serializer;

    /**
     * @param Session $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param OptionFactory $itemOptionFactory
     * @param Database $coreFileStorageDatabase
     * @param ValidatorInfo $validatorInfo
     * @param ValidatorFile $validatorFile
     * @param UrlBuilder $urlBuilder
     * @param Escaper $escaper
     * @param array $data
     * @param Filesystem|null $filesystem
     * @param Json|null $serializer
     * @param ProductHelper|null $productHelper
     */
    public function __construct(
        Session $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        OptionFactory $itemOptionFactory,
        Database $coreFileStorageDatabase,
        ValidatorInfo $validatorInfo,
        ValidatorFile $validatorFile,
        UrlBuilder $urlBuilder,
        Escaper $escaper,
        array $data = [],
        ?Filesystem $filesystem = null,
        ?Json $serializer = null,
        ?ProductHelper $productHelper = null
    ) {
        parent::__construct(
            $checkoutSession,
            $scopeConfig,
            $itemOptionFactory,
            $coreFileStorageDatabase,
            $validatorInfo,
            $validatorFile,
            $urlBuilder,
            $escaper,
            $data,
            $filesystem,
            $serializer,
            $productHelper
        );

        $this->serializer = $serializer ?: ObjectManager::getInstance()->get(Json::class);
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function prepareForCart()
    {
        $option = $this->getOption();
        $optionId = $option->getId();
        $buyRequest = $this->getRequest();

        $requestOptions = $buyRequest->getOptions();

        if ($this->getIsValid() && $this->getUserValue() !== null) {
            $value = $this->getUserValue();

            // $_FILES['options'] does not exist on this path, so the value travels in the buy request
            $requestOptions[$this->getOption()->getId()] = $value;
            $result = $this->serializer->serialize($value);
        } else if ($this->getIsValid() && isset($requestOptions[$this->getOption()->getId()])) {
            $result = $this->serializer->serialize($requestOptions[$this->getOption()->getId()]);
        } else {
            // dropping it stops bad file data reaching later reorders and reconfigurations through buyRequest
            if (is_array($requestOptions)) {
                unset($requestOptions[$this->getOption()->getId()]);
            }
            $result = null;
        }
        $buyRequest->setOptions($requestOptions);

        $optionActionKey = 'options_' . $optionId . '_file_action';
        $buyRequest->unsetData($optionActionKey);

        return $result;
    }
}
