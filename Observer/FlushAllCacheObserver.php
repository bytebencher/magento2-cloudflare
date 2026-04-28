<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ByteBencher\Cloudflare\Model\CloudflareClient;
use ByteBencher\Cloudflare\Config\CacheConfig;

class FlushAllCacheObserver implements ObserverInterface
{
    public function __construct(
        private readonly CloudflareClient $cloudflareClient,
        private readonly CacheConfig $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isConfigured()) {
            return;
        }

        $this->cloudflareClient->purgeAll();
    }
}
