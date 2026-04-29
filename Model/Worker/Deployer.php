<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Model\Worker;

use Laminas\Http\Request as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir\Reader;
use ByteBencher\Cloudflare\Config\CacheConfig;
use SR\Gateway\Api\Http\Client\ClientInterface;
use SR\Gateway\Model\Http\TransferBuilderFactory;
use SR\Gateway\Model\Request\ClientConfigBuilder;

class Deployer
{
    private const API_URL_PATTERN = 'https://api.cloudflare.com/client/v4/accounts/%s/workers/scripts/%s';
    private const COMPATIBILITY_DATE = '2026-04-28';
    private const CONNECT_TIMEOUT = 10;
    private const MODULE_NAME = 'ByteBencher_Cloudflare';
    private const REQUEST_TIMEOUT = 60;
    private const SCRIPT_PART_NAME = 'main.js';
    private const SCRIPT_PATH = '/CFWorker/FPC-worker.js';
    private const SCRIPT_CONTENT_TYPE = 'application/javascript';

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Reader $moduleDirReader,
        private readonly File $fileDriver,
        private readonly ClientInterface $restClient,
        private readonly TransferBuilderFactory $transferBuilderFactory
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
        $url = sprintf(
            self::API_URL_PATTERN,
            rawurlencode((string) $this->config->getAccountIdForWebsite($websiteCode)),
            rawurlencode($workerName)
        );
        [$payload, $boundary] = $this->buildMultipartPayload($scriptPath, $websiteCode);

        try {
            $transfer = $this->transferBuilderFactory->create()
                ->setMethod(HttpRequest::METHOD_PUT)
                ->setUri($url)
                ->setHeaders([
                    'Authorization' => 'Bearer ' . trim((string) $this->config->getApiTokenForWebsite($websiteCode)),
                    'Accept' => 'application/json',
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ])
                ->setBody($payload)
                ->setClientConfig([
                    'timeout' => self::REQUEST_TIMEOUT,
                    'verifypeer' => true,
                    'verifyhost' => 2,
                    ClientConfigBuilder::PARAM_CURL_EXTRA_OPTIONS => [
                        CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    ],
                ])
                ->shouldEncode(false)
                ->build();

            $result = $this->restClient->placeRequest($transfer);
        } catch (\Exception $exception) {
            throw new LocalizedException(
                __('Cloudflare worker deployment request failed: %1', $exception->getMessage()),
                $exception
            );
        }

        $response = $result['object'] ?? null;
        if (!is_array($response)) {
            throw new LocalizedException(
                __('Cloudflare worker deployment returned an unexpected response.')
            );
        }

        return $response;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildMultipartPayload(string $scriptPath, ?string $websiteCode): array
    {
        try {
            $scriptContents = $this->fileDriver->fileGetContents($scriptPath);
            $boundary = '--------------------------' . bin2hex(random_bytes(12));
        } catch (\Throwable $exception) {
            throw new LocalizedException(
                __('Unable to prepare the bundled Cloudflare worker for upload.'),
                $exception
            );
        }

        $parts = [
            $this->buildMultipartField($boundary, 'metadata', $this->buildMetadata($websiteCode)),
            $this->buildMultipartFile(
                $boundary,
                self::SCRIPT_PART_NAME,
                self::SCRIPT_PART_NAME,
                self::SCRIPT_CONTENT_TYPE,
                $scriptContents
            ),
            '--' . $boundary . '--',
        ];

        return [implode("\r\n", $parts) . "\r\n", $boundary];
    }

    private function buildMultipartField(string $boundary, string $name, string $value): string
    {
        return implode("\r\n", [
            '--' . $boundary,
            sprintf('Content-Disposition: form-data; name="%s"', $name),
            '',
            $value,
        ]);
    }

    private function buildMultipartFile(
        string $boundary,
        string $name,
        string $filename,
        string $contentType,
        string $contents
    ): string {
        return implode("\r\n", [
            '--' . $boundary,
            sprintf('Content-Disposition: form-data; name="%s"; filename="%s"', $name, $filename),
            'Content-Type: ' . $contentType,
            '',
            $contents,
        ]);
    }
}
