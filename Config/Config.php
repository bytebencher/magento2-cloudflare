<?php
/*
 * Copyright © 2022 ByteBencher. All rights reserved.
 * See LICENCE file for license details.
 */

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const EXT_ALIAS = 'bytebencher_cloudflare';

    /**
     * Is used to init $this->pathPattern
     *
     * @var string
     */
    public const DEFAULT_PATH_PATTERN = self::EXT_ALIAS . '/%s/%s';

    public const GROUP_PATH_GENERAL = 'general';

    /**
     * Is used to init $this->pathGroup
     *
     * @var string
     */
    public const DEFAULT_PATH_GROUP = self::GROUP_PATH_GENERAL;

    /**#@+
     * XML Config parts
     * ex: '{self::EXT_ALIAS}/{self::..._GROUP}/{KEY_CONFIG_...}'
     */

    // general
    public const KEY_CONFIG_ACTIVE = 'active';
    public const KEY_CONFIG_IMAGE_QUALITY = 'image_quality';
    public const KEY_CONFIG_IMAGE_FIT = 'image_fit';
    //public const KEY_CONFIG_ = '';
    /**#@- */

    protected ScopeConfigInterface $scopeConfig;
    protected ?string $pathPattern = null;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        string $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->pathPattern = $pathPattern;
    }


    /**
     * @param mixed|null $storeId
     * @return bool
     */
    public function isActive($storeId = null): bool
    {
        return (bool)$this->getActive($storeId);
    }

    /**
     * @param mixed|null $storeId
     * @return string|null
     */
    public function getActive($storeId = null): ?string
    {
        return $this->getValue(static::KEY_CONFIG_ACTIVE, static::DEFAULT_PATH_GROUP, $storeId);
    }

    /**
     * @param mixed|null $storeId
     * @return int
     */
    public function getImageQuality($storeId = null): int
    {
        return (int)$this->getValue(static::KEY_CONFIG_IMAGE_QUALITY, static::DEFAULT_PATH_GROUP, $storeId);
    }

    /**
     * @param mixed|null $storeId
     * @return string
     */
    public function getImageFit($storeId = null): string
    {
        $value = $this->getValue(static::KEY_CONFIG_IMAGE_FIT, static::DEFAULT_PATH_GROUP, $storeId);
        if (empty($value)) {
            $value = \ByteBencher\Cloudflare\Model\System\Config\Source\ImageFit::NONE;
        }
        return $value;
    }

    /**
     * @param string $field
     * @param string|null $group
     * @param mixed|null $storeId
     *
     * @return mixed|null
     */
    public function getValue(string $field, ?string $group = null, $storeId = null)
    {
        if ($this->pathPattern === null) {
            return null;
        }

        return $this->scopeConfig->getValue(
            sprintf($this->pathPattern, $group ?: static::DEFAULT_PATH_GROUP, $field),
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
