<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Config;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\PageCache\Model\Cache\Type;
use Magento\PageCache\Model\Config as PageCacheConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class CacheConfig extends \SR\Gateway\Model\Config\Config
{
    public const EXT_ALIAS = 'bytebencher_cloudflare';
    public const DEFAULT_PATH_GROUP = 'cache';
    public const WORKER_PATH_GROUP = 'worker';

    /**
     * Cloudflare caching application type for system/full_page_cache/caching_application
     */
    public const CLOUDFLARE = 3;

    private const KEY_ZONE_ID = 'zone_id';
    private const KEY_ACCOUNT_ID = 'account_id';
    private const KEY_API_TOKEN = 'api_token';
    private const KEY_API_URL = 'api_url';
    private const KEY_WORKER_NAME = 'worker_name';

    private ?string $siteTag = null;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly StateInterface $cacheState,
        string $pathPattern = self::EXT_ALIAS . '/%s/%s'
    ) {
        parent::__construct($scopeConfig, $pathPattern);
    }

    /**
     * Check if Cloudflare is selected as the global caching application
     */
    public function isCloudflareApplication(): bool
    {
        return (int) $this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TYPE) === self::CLOUDFLARE;
    }

    /**
     * Check if full_page_cache type is enabled in Cache Management
     */
    public function isPageCacheEnabled(): bool
    {
        return $this->cacheState->isEnabled(Type::TYPE_IDENTIFIER);
    }

    public function isActive($storeId = null): bool
    {
        return (bool) $this->getValue(self::KEY_CONFIG_ACTIVE, self::DEFAULT_PATH_GROUP, $storeId);
    }

    public function isActiveForWebsite(?string $websiteCode = null): bool
    {
        return (bool) $this->getWebsiteScopedValue(self::KEY_CONFIG_ACTIVE, self::DEFAULT_PATH_GROUP, $websiteCode);
    }

    public function getZoneId($storeId = null): ?string
    {
        return $this->getValue(self::KEY_ZONE_ID, self::DEFAULT_PATH_GROUP, $storeId);
    }

    public function getZoneIdForWebsite(?string $websiteCode = null): ?string
    {
        return $this->getWebsiteScopedValue(self::KEY_ZONE_ID, self::DEFAULT_PATH_GROUP, $websiteCode);
    }

    public function getApiToken($storeId = null): ?string
    {
        return $this->getValue(self::KEY_API_TOKEN, self::DEFAULT_PATH_GROUP, $storeId);
    }

    public function getApiTokenForWebsite(?string $websiteCode = null): ?string
    {
        return $this->getWebsiteScopedValue(self::KEY_API_TOKEN, self::DEFAULT_PATH_GROUP, $websiteCode);
    }

    public function getAccountId($storeId = null): ?string
    {
        return $this->getValue(self::KEY_ACCOUNT_ID, self::DEFAULT_PATH_GROUP, $storeId);
    }

    public function getAccountIdForWebsite(?string $websiteCode = null): ?string
    {
        return $this->getWebsiteScopedValue(self::KEY_ACCOUNT_ID, self::DEFAULT_PATH_GROUP, $websiteCode);
    }

    public function getApiUrl(): ?string
    {
        return $this->getValue(self::KEY_API_URL, self::DEFAULT_PATH_GROUP);
    }

    public function getWorkerName($storeId = null): ?string
    {
        return $this->getValue(self::KEY_WORKER_NAME, self::WORKER_PATH_GROUP, $storeId);
    }

    public function getWorkerNameForWebsite(?string $websiteCode = null): ?string
    {
        return $this->getWebsiteScopedValue(self::KEY_WORKER_NAME, self::WORKER_PATH_GROUP, $websiteCode);
    }

    public function isDebugEnabled(): bool
    {
        return (bool) $this->getValue(self::KEY_CONFIG_DEBUG, self::DEFAULT_PATH_GROUP);
    }

    public function isConfigured(): bool
    {
        return $this->isCloudflareApplication()
            && $this->isActive()
            && !empty($this->getZoneId())
            && !empty($this->getApiToken());
    }

    public function getResolvedApiUrl(): string
    {
        return sprintf((string) $this->getApiUrl(), (string) $this->getZoneId());
    }

    // ─── Worker configuration getters (bytebencher_cloudflare/worker/*) ───

    public function getWorkerDebug($storeId = null): bool
    {
        return (bool) $this->getValue(self::KEY_CONFIG_DEBUG, self::WORKER_PATH_GROUP, $storeId);
    }

    public function getWorkerDebugForWebsite(?string $websiteCode = null): bool
    {
        return (bool) $this->getWebsiteScopedValue(self::KEY_CONFIG_DEBUG, self::WORKER_PATH_GROUP, $websiteCode);
    }

    public function getWorkerTtl($storeId = null): int
    {
        $override = $this->getValue('default_ttl', self::WORKER_PATH_GROUP, $storeId);

        if ($override !== null && $override !== '') {
            return (int) $override;
        }

        $globalTtl = $this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TTL);

        if ($globalTtl === null || $globalTtl === '') {
            return 86400;
        }

        return (int) $globalTtl;
    }

    public function getWorkerTtlForWebsite(?string $websiteCode = null): int
    {
        $value = $this->getWebsiteScopedValue('default_ttl', self::WORKER_PATH_GROUP, $websiteCode);

        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return $this->getWorkerTtl();
    }

    public function getWorkerHfpTtl($storeId = null): int
    {
        $value = $this->getValue('hfp_ttl', self::WORKER_PATH_GROUP, $storeId);

        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return 120;
    }

    public function getWorkerHfpTtlForWebsite(?string $websiteCode = null): int
    {
        $value = $this->getWebsiteScopedValue('hfp_ttl', self::WORKER_PATH_GROUP, $websiteCode);

        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        return $this->getWorkerHfpTtl();
    }

    public function getWorkerAdminPath($storeId = null): string
    {
        return (string) ($this->getValue('admin_path', self::WORKER_PATH_GROUP, $storeId) ?: 'admin');
    }

    public function getWorkerAdminPathForWebsite(?string $websiteCode = null): string
    {
        return (string) ($this->getWebsiteScopedValue('admin_path', self::WORKER_PATH_GROUP, $websiteCode) ?: 'admin');
    }

    public function getWorkerBypassPaths($storeId = null): string
    {
        return (string) ($this->getValue('bypass_paths', self::WORKER_PATH_GROUP, $storeId) ?: '');
    }

    public function getWorkerBypassPathsForWebsite(?string $websiteCode = null): string
    {
        return (string) ($this->getWebsiteScopedValue('bypass_paths', self::WORKER_PATH_GROUP, $websiteCode) ?: '');
    }

    public function useWorkerR2Cache($storeId = null): bool
    {
        return (bool) $this->getValue('use_r2_cache', self::WORKER_PATH_GROUP, $storeId);
    }

    public function useWorkerR2CacheForWebsite(?string $websiteCode = null): bool
    {
        return (bool) $this->getWebsiteScopedValue('use_r2_cache', self::WORKER_PATH_GROUP, $websiteCode);
    }

    public function getWorkerR2BucketName($storeId = null): string
    {
        return (string) ($this->getValue('r2_bucket_name', self::WORKER_PATH_GROUP, $storeId) ?: '');
    }

    public function getWorkerR2BucketNameForWebsite(?string $websiteCode = null): string
    {
        return (string) ($this->getWebsiteScopedValue('r2_bucket_name', self::WORKER_PATH_GROUP, $websiteCode) ?: '');
    }

    public function getWorkerR2BucketBinding($storeId = null): string
    {
        return (string) ($this->getValue('r2_bucket_binding', self::WORKER_PATH_GROUP, $storeId) ?: 'R2_CACHE');
    }

    public function getWorkerR2BucketBindingForWebsite(?string $websiteCode = null): string
    {
        return (string) ($this->getWebsiteScopedValue('r2_bucket_binding', self::WORKER_PATH_GROUP, $websiteCode) ?: 'R2_CACHE');
    }

    private function getWebsiteScopedValue(string $field, string $group, ?string $websiteCode = null)
    {
        if ($websiteCode === null || $websiteCode === '') {
            return $this->getValue($field, $group);
        }

        return $this->scopeConfig->getValue(
            sprintf($this->pathPattern, $group, $field),
            ScopeInterface::SCOPE_WEBSITE,
            $websiteCode
        );
    }

    /**
     * Get hostname-based site-wide tag used for full cache flush
     * and to scope tags per site when multiple sites share the same Cloudflare zone.
     *
     * e.g. "all4pet.mystore.today" → "all4pet_mystore_today"
     */
    public function getSiteTag(): string
    {
        if ($this->siteTag === null) {
            try {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl();
                $host = (string) parse_url($baseUrl, PHP_URL_HOST);
                $this->siteTag = str_replace(['.', '-'], '_', $host);
            } catch (\Exception) {
                $this->siteTag = 'default';
            }
        }

        return $this->siteTag;
    }
}
