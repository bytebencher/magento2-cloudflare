<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Model\System\Config\Source;

use Magento\PageCache\Model\System\Config\Source\Application;
use ByteBencher\Cloudflare\Config\CacheConfig;

class ApplicationPlugin
{
    public function afterToOptionArray(Application $subject, array $result): array
    {
        $result[] = [
            'value' => CacheConfig::CLOUDFLARE,
            'label' => __('Cloudflare CDN'),
        ];

        return $result;
    }

    public function afterToArray(Application $subject, array $result): array
    {
        $result[CacheConfig::CLOUDFLARE] = __('Cloudflare CDN');

        return $result;
    }
}