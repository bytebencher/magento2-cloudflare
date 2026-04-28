<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Observer;

use Magento\Framework\App\Cache\Tag\Resolver;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use ByteBencher\Cloudflare\Model\CloudflareClient;
use ByteBencher\Cloudflare\Config\CacheConfig;

class PurgeByTags implements ObserverInterface
{
    public function __construct(
        private readonly CloudflareClient $cloudflareClient,
        private readonly Resolver $tagResolver,
        private readonly CacheConfig $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isConfigured()) {
            return;
        }

        $object = $observer->getEvent()->getObject();

        if (!is_object($object)) {
            return;
        }

        $tags = $this->tagResolver->getTags($object);

        if (!empty($tags)) {
            $this->cloudflareClient->purgeByTags(array_unique($tags));
        }
    }
}
