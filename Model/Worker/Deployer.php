<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Model\Worker;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use ByteBencher\Cloudflare\Config\CacheConfig;

class Deployer
{
    private const API_URL_PATTERN = 'https://api.cloudflare.com/client/v4/accounts/%s/workers/scripts/%s';
    private const COMPATIBILITY_DATE = '2026-04-28';
    private const CONNECT_TIMEOUT = 10;
    private const MODULE_NAME = 'ByteBencher_Cloudflare';
    private const REQUEST_TIMEOUT = 60;
    private const SCRIPT_PART_NAME = 'main.js';
    private const SCRIPT_PATH = '/CFWorker/FPC-worker.js';

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Reader $moduleDirReader,
        private readonly File $fileDriver
    ) {
    }

    public function deploy(?string $websiteCode = null): string
    {
        $websiteCode = $websiteCode !== null && $websiteCode !== '' ? $websiteCode : null;
        $this->assertConfiguration($websiteCode);

        $workerName = trim((string) $this->config->getWorkerNameForWebsite($websiteCode));
        $scriptPath = $this->getScriptPath();
        $response = $this->uploadWorker($workerName, $scriptPath, $websiteCode);

        if (($response['success'] ?? false) !== true) {
            throw new LocalizedException(
                __('Cloudflare worker deployment failed: %1', $this->extractErrorMessage($response))
            );
        }

        return $workerName;
    }

    private function assertConfiguration(?string $websiteCode): void
    {
        if (!function_exists('curl_init')) {
            throw new LocalizedException(__('The cURL PHP extension is required to deploy the Cloudflare worker.'));
        }

        if (!$this->config->isActiveForWebsite($websiteCode)) {
            throw new LocalizedException(__('Enable Cloudflare cache before deploying the worker.'));
        }

        if (trim((string) $this->config->getAccountIdForWebsite($websiteCode)) === '') {
            throw new LocalizedException(__('Set the Cloudflare Account ID before deploying the worker.'));
        }

        if (trim((string) $this->config->getWorkerNameForWebsite($websiteCode)) === '') {
            throw new LocalizedException(__('Set the Cloudflare Worker Name before deploying the worker.'));
        }

        if (trim((string) $this->config->getApiTokenForWebsite($websiteCode)) === '') {
            throw new LocalizedException(__('Set the Cloudflare API Token before deploying the worker.'));
        }

        if (!$this->fileDriver->isExists($this->getScriptPath())) {
            throw new LocalizedException(__('The bundled Cloudflare worker script could not be found.'));
        }
    }

    private function buildBindings(?string $websiteCode): array
    {
        $bindings = [
            [
                'type' => 'plain_text',
                'name' => 'DEBUG',
                'text' => $this->config->getWorkerDebugForWebsite($websiteCode) ? 'true' : 'false',
            ],
            [
                'type' => 'plain_text',
                'name' => 'DEFAULT_TTL',
                'text' => (string) $this->config->getWorkerTtlForWebsite($websiteCode),
            ],
            [
                'type' => 'plain_text',
                'name' => 'HFP_TTL',
                'text' => (string) $this->config->getWorkerHfpTtlForWebsite($websiteCode),
            ],
            [
                'type' => 'plain_text',
                'name' => 'ADMIN_PATH',
                'text' => $this->config->getWorkerAdminPathForWebsite($websiteCode),
            ],
        ];

        $bypassPaths = trim($this->config->getWorkerBypassPathsForWebsite($websiteCode));
        if ($bypassPaths !== '') {
            $bindings[] = [
                'type' => 'plain_text',
                'name' => 'BYPASS_PATHS',
                'text' => $bypassPaths,
            ];
        }

        return $bindings;
    }

    private function buildMetadata(?string $websiteCode): string
    {
        try {
            return json_encode([
                'main_module' => self::SCRIPT_PART_NAME,
                'bindings' => $this->buildBindings($websiteCode),
                'compatibility_date' => self::COMPATIBILITY_DATE,
                'annotations' => [
                    'workers/message' => 'Deployed from Magento Admin',
                ],
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LocalizedException(
                __('Unable to encode the worker deployment metadata.'),
                $exception
            );
        }
    }

    private function extractErrorMessage(array $response, ?int $statusCode = null): string
    {
        $messages = [];

        foreach ($response['errors'] ?? [] as $error) {
            if (!empty($error['message'])) {
                $messages[] = $error['message'];
            }
        }

        foreach ($response['messages'] ?? [] as $message) {
            if (!empty($message['message'])) {
                $messages[] = $message['message'];
            }
        }

        if ($messages === []) {
            return $statusCode !== null
                ? (string) __('Unexpected response from Cloudflare (HTTP %1).', $statusCode)
                : (string) __('Unexpected response from Cloudflare.');
        }

        return implode(' ', array_unique($messages));
    }

    private function getScriptPath(): string
    {
        return $this->moduleDirReader->getModuleDir('', self::MODULE_NAME) . self::SCRIPT_PATH;
    }

    private function uploadWorker(string $workerName, string $scriptPath, ?string $websiteCode): array
    {
        $payload = [
            'metadata' => $this->buildMetadata($websiteCode),
            self::SCRIPT_PART_NAME => new \CURLFile($scriptPath, 'application/javascript', self::SCRIPT_PART_NAME),
        ];

        $url = sprintf(
            self::API_URL_PATTERN,
            rawurlencode((string) $this->config->getAccountIdForWebsite($websiteCode)),
            rawurlencode($workerName)
        );

        $curlHandle = curl_init($url);
        if ($curlHandle === false) {
            throw new LocalizedException(__('Unable to initialize the Cloudflare worker deployment request.'));
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim((string) $this->config->getApiTokenForWebsite($websiteCode)),
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
        ]);

        $rawResponse = curl_exec($curlHandle);
        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $errorMessage = curl_error($curlHandle);
        curl_close($curlHandle);

        if ($rawResponse === false) {
            throw new LocalizedException(
                __('Cloudflare worker deployment request failed: %1', $errorMessage ?: __('Unknown cURL error.'))
            );
        }

        try {
            $response = json_decode($rawResponse, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new LocalizedException(
                __('Cloudflare worker deployment returned an unexpected response (HTTP %1).', $statusCode)
            );
        }

        if (!is_array($response)) {
            throw new LocalizedException(
                __('Cloudflare worker deployment returned an unexpected response (HTTP %1).', $statusCode)
            );
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new LocalizedException(
                __('Cloudflare worker deployment failed: %1', $this->extractErrorMessage($response, $statusCode))
            );
        }

        return $response;
    }
}
