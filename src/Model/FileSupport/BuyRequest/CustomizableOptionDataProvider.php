<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_QuoteGraphQl
 * @copyright   Copyright 2020 Adobe. All Rights Reserved.
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\QuoteGraphQl\Model\FileSupport\BuyRequest;

use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Size;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Quote\Model\Cart\BuyRequest\BuyRequestDataProviderInterface;
use Magento\Quote\Model\Cart\Data\CartItem;
use ScandiPWA\QuoteGraphQl\Model\FileSupport\SanitizesUploadedFileNames;

class CustomizableOptionDataProvider implements BuyRequestDataProviderInterface
{
    use SanitizesUploadedFileNames;

    public const string OPTION_TYPE = 'custom-option';
    public const string QUOTE_MEDIA_PATH = 'custom_options/quote/';
    public const string ORDER_MEDIA_PATH = 'custom_options/order/';

    // the media directory is group-readable so the web server can serve what the customer uploaded
    private const DIRECTORY_MODE = 0755;

    /**
     * @var string
     */
    protected $mediaPath;

    /**
     * @var WriteInterface
     */
    private WriteInterface $mediaDirectory;

    /**
     * @param Filesystem $filesystem
     * @param ProductCustomOptionRepositoryInterface $optionRepository
     * @param Size $fileSize
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        private readonly ProductCustomOptionRepositoryInterface $optionRepository,
        private readonly Size $fileSize
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->mediaPath = $filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function execute(CartItem $cartItem): array
    {
        $customizableOptionsData = [];

        foreach ($cartItem->getSelectedOptions() as $optionData) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $optionData = \explode('/', base64_decode($optionData->getId()));

            if ($this->isProviderApplicable($optionData) === false) {
                continue;
            }

            $this->validateInput($optionData);

            [$optionType, $optionId, $optionValue] = $optionData;

            // a wishlist item arrives with its file already stored, encoded into the option value, and
            // core's ValidatorInfo refuses a record that is not an array
            if (str_starts_with($optionValue, "file-")) {
                [, $encodedFileInfo] = \explode('-', $optionValue);
                $optionValue = json_decode(base64_decode($encodedFileInfo), true);
            }

            if ($optionType == self::OPTION_TYPE) {
                $customizableOptionsData[$optionId][] = $optionValue;
            }
        }

        foreach ($cartItem->getEnteredOptions() as $option) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $optionData = \explode('/', base64_decode($option->getUid()));

            if ($this->isProviderApplicable($optionData) === false) {
                continue;
            }

            [$optionType, $optionId] = $optionData;

            $fileData = $this->getFileData($option->getUid(), $option->getValue(), $cartItem->getSku(), $optionId);
            if ($optionType == self::OPTION_TYPE) {
                if ($fileData !== false) {
                    $this->createFileAndFolder($option->getUid(), $fileData['raw'], $fileData['stored_name']);
                    unset($fileData['raw'], $fileData['stored_name']);
                    $customizableOptionsData[$optionId][] = $fileData;
                } else {
                    $customizableOptionsData[$optionId][] = $option->getValue();
                }
            }
        }

        return ['options' => $this->flattenOptionValues($customizableOptionsData)];
    }

    /**
     * @param string $uid
     * @param string|null $optionData
     * @param string $sku
     * @param string $optionId
     * @return array|false
     * @throws LocalizedException
     */
    private function getFileData($uid, $optionData, string $sku, string $optionId)
    {
        $data = json_decode((string)$optionData, true);

        if (!is_array($data)) {
            return false;
        }

        $filename = $data['file_name'] ?? '';
        $filedata = $data['file_data'] ?? '';

        if (!$filename && !$filedata) {
            return false;
        }

        // an upload with no usable name is refused rather than stored as the raw request payload
        if (!$filename || !$filedata) {
            throw new LocalizedException(__('The uploaded file must have a name and content.'));
        }

        // the client-supplied name is shown as the title only; the bytes go to a generated path
        $title = $this->sanitizeUploadedFileName((string)$filename, $this->optionExtensions($sku, $optionId));
        $storedName = $this->generateStoredFileName($title);
        $insidePath = $this->sanitizePathSegment((string)$uid) . '/_/' . $storedName;

        return [
            'type' => 'application/octet-stream',
            'title' => $title,
            'quote_path' => self::QUOTE_MEDIA_PATH . $insidePath,
            'order_path' => self::ORDER_MEDIA_PATH . $insidePath,
            'fullpath' => $this->mediaPath . self::QUOTE_MEDIA_PATH . $insidePath,
            'secret_key' => $this->buildSecretKey((string)$filedata),
            'raw' => $filedata,
            'stored_name' => $storedName
        ];
    }

    /**
     * @param string $uid
     * @param string $value
     * @param string $filename name to store the file under; must already be sanitized
     * @return void
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function createFileAndFolder($uid, $value, $filename)
    {
        $bytes = $this->decodeUploadedFile((string)$value);

        if ($bytes === false) {
            throw new LocalizedException(__('The uploaded file could not be read.'));
        }

        $this->assertUploadedFileSize(strlen($bytes), (int)$this->fileSize->getMaxFileSize());

        $directory = sprintf(
            '%s%s/_',
            self::QUOTE_MEDIA_PATH,
            $this->sanitizePathSegment((string)$uid)
        );
        $path = $directory . '/' . $this->assertStorableFileName((string)$filename);

        if (!$this->mediaDirectory->isDirectory($directory)) {
            $this->mediaDirectory->create($directory);
            $this->mediaDirectory->changePermissions($directory, self::DIRECTORY_MODE);
        }

        // writeFile() throws on a failed write, so a full disk is reported instead of a silent success
        $this->mediaDirectory->writeFile($path, $bytes);
    }

    /**
     * the key core re-derives from the stored file and compares the record against: it must be the
     * hash of the bytes, never their name, or a re-submitted option is dropped as never entered
     * @param string $value
     * @return string
     */
    protected function buildSecretKey(string $value): string
    {
        // an unreadable payload is refused by createFileAndFolder() moments later, so the empty-string
        // key this would build never reaches a stored record
        return substr(hash('sha256', (string)$this->decodeUploadedFile($value)), 0, 20);
    }

    /**
     * the bytes behind a data URI, false when the payload cannot be read; createFileAndFolder() writes
     * exactly these, so the secret key hashes what the file on disk ends up holding
     * @param string $value
     * @return string|false
     */
    protected function decodeUploadedFile(string $value): string|false
    {
        return base64_decode(substr($value, strpos($value, ',') + 1), true);
    }

    /**
     * the option's own file_extension list, narrowing the built-in one; empty when the option cannot be read
     * @param string $sku
     * @param string $optionId
     * @return string[]
     */
    private function optionExtensions(string $sku, string $optionId): array
    {
        try {
            $declared = (string)$this->optionRepository->get($sku, (int)$optionId)->getFileExtension();
        } catch (NoSuchEntityException | LocalizedException) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($extension) => strtolower(trim($extension, " \t.")),
            explode(',', $declared)
        )));
    }

    /**
     * flatten option values for non-multiselect customizable options
     * @param array $customizableOptionsData
     * @return array
     */
    protected function flattenOptionValues(array $customizableOptionsData): array
    {
        foreach ($customizableOptionsData as $optionId => $optionValue) {
            if (count($optionValue) === 1) {
                $customizableOptionsData[$optionId] = $optionValue[0];
            }
        }

        return $customizableOptionsData;
    }

    /**
     * @param array $optionData
     * @return bool
     */
    protected function isProviderApplicable(array $optionData): bool
    {
        if ($optionData[0] !== self::OPTION_TYPE) {
            return false;
        }

        return true;
    }

    /**
     * @param array $optionData
     * @return void
     * @throws LocalizedException
     */
    protected function validateInput(array $optionData): void
    {
        if (count($optionData) !== 3) {
            throw new LocalizedException(
                __('Wrong format of the entered option data')
            );
        }
    }
}
