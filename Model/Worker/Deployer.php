<?php

declare(strict_types=1);

namespace SR\Cloudflare\Model\Worker;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use SR\Cloudflare\Config\CacheConfig;

class Deployer
{
    private const API_URL_PATTERN = 'https://api.cloudflare.com/client/v4/accounts/%s/workers/scripts/%s';
    private const COMPATIBILITY_DATE = '2026-04-28';
    private const MODULE_NAME = 'SR_Cloudflare';
    private const SCRIPT_PART_NAME = 'main.js';
    private const SCRIPT_PATH = '/CFWorker/FPC-worker.js';

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Reader $moduleDirReader,
        private readonly File $fileDriver
    ) {
    }

    public function deploy(): string
    {
        $this->assertConfiguration();

        $workerName = (string) $this->config->getWorkerName();
        $scriptPath = $this->getScriptPath();
        $response = $this->uploadWorker($workerName, $scriptPath);

        if (($response['success'] ?? false) !== true) {
            throw new LocalizedException(
                __('Cloudflare worker deployment failed: %1', $this->extractErrorMessage($response))
            );
        }

        return $workerName;
    }

    private function assertConfiguration(): void
    {
        if (!function_exists('curl_init')) {
            throw new LocalizedException(__('The cURL PHP extension is required to deploy the Cloudflare worker.'));
        }

        if (!$this->config->isActive()) {
            throw new LocalizedException(__('Enable Cloudflare cache before deploying the worker.'));
        }

        if (!$this->config->getAccountId()) {
            throw new LocalizedException(__('Set the Cloudflare Account ID before deploying the worker.'));
        }

        if (!$this->config->getWorkerName()) {
            throw new LocalizedException(__('Set the Cloudflare Worker Name before deploying the worker.'));
        }

        if (!$this->config->getApiToken()) {
            throw new LocalizedException(__('Set the Cloudflare API Token before deploying the worker.'));
        }

        if (!$this->fileDriver->isExists($this->getScriptPath())) {
            throw new LocalizedException(__('The bundled Cloudflare worker script could not be found.'));
        }
    }

    private function buildBindings(): array
    {
        $bindings = [
            [
                'type' => 'plain_text',
                'name' => 'DEBUG',
                'text' => $this->config->getWorkerDebug() ? 'true' : 'false',
            ],
            [
                'type' => 'plain_text',
                'name' => 'DEFAULT_TTL',
                'text' => (string) $this->config->getWorkerTtl(),
            ],
            [
                'type' => 'plain_text',
                'name' => 'HFP_TTL',
                'text' => (string) $this->config->getWorkerHfpTtl(),
            ],
            [
                'type' => 'plain_text',
                'name' => 'ADMIN_PATH',
                'text' => $this->config->getWorkerAdminPath(),
            ],
        ];

        $bypassPaths = trim($this->config->getWorkerBypassPaths());
        if ($bypassPaths !== '') {
            $bindings[] = [
                'type' => 'plain_text',
                'name' => 'BYPASS_PATHS',
                'text' => $bypassPaths,
            ];
        }

        return $bindings;
    }

    private function buildMetadata(): string
    {
        $metadata = [
            'main_module' => self::SCRIPT_PART_NAME,
            'bindings' => $this->buildBindings(),
            'compatibility_date' => self::COMPATIBILITY_DATE,
            'annotations' => [
                'workers/message' => 'Deployed from Magento Admin',
            ],
        ];

        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
        if ($json === false) {
            throw new LocalizedException(__('Unable to encode the worker deployment metadata.'));
        }

        return $json;
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

    private function uploadWorker(string $workerName, string $scriptPath): array
    {
        $curlHandle = curl_init(
            sprintf(
                self::API_URL_PATTERN,
                rawurlencode((string) $this->config->getAccountId()),
                rawurlencode($workerName)
            )
        );

        if ($curlHandle === false) {
            throw new LocalizedException(__('Unable to initialize the Cloudflare worker deployment request.'));
        }

        $payload = [
            'metadata' => $this->buildMetadata(),
            self::SCRIPT_PART_NAME => new \CURLFile($scriptPath, 'application/javascript', self::SCRIPT_PART_NAME),
        ];

        curl_setopt_array($curlHandle, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config->getApiToken(),
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $rawResponse = curl_exec($curlHandle);
        if ($rawResponse === false) {
            $errorMessage = curl_error($curlHandle);
            curl_close($curlHandle);
            throw new LocalizedException(
                __('Cloudflare worker deployment request failed: %1', $errorMessage ?: __('Unknown cURL error.'))
            );
        }

        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        curl_close($curlHandle);

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
