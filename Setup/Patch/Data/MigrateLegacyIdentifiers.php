<?php

declare(strict_types=1);

namespace ByteBencher\Cloudflare\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MigrateLegacyIdentifiers implements DataPatchInterface
{
    private const LEGACY_CONFIG_PREFIX = 'srcloudflare/';
    private const NEW_CONFIG_PREFIX = 'bytebencher_cloudflare/';

    private const ACL_RESOURCE_MAP = [
        'SR_Cloudflare::srcloudflare' => 'ByteBencher_Cloudflare::bytebencher_cloudflare',
        'SR_Cloudflare::srcloudflare_settings' => 'ByteBencher_Cloudflare::bytebencher_cloudflare_settings',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $configTable = $this->moduleDataSetup->getTable('core_config_data');
        $authorizationRuleTable = $this->moduleDataSetup->getTable('authorization_rule');

        $this->moduleDataSetup->startSetup();

        $legacyConfigRows = $connection->fetchAll(
            $connection->select()
                ->from($configTable, ['scope', 'scope_id', 'path', 'value'])
                ->where('path LIKE ?', self::LEGACY_CONFIG_PREFIX . '%')
        );

        foreach ($legacyConfigRows as $row) {
            $newPath = preg_replace(
                '#^' . preg_quote(self::LEGACY_CONFIG_PREFIX, '#') . '#',
                self::NEW_CONFIG_PREFIX,
                (string) $row['path'],
                1
            );

            if ($newPath === null) {
                continue;
            }

            if ($newPath === $row['path']) {
                continue;
            }

            $exists = (bool) $connection->fetchOne(
                $connection->select()
                    ->from($configTable, ['config_id'])
                    ->where('scope = ?', $row['scope'])
                    ->where('scope_id = ?', (int) $row['scope_id'])
                    ->where('path = ?', $newPath)
            );

            if ($exists) {
                continue;
            }

            $connection->insert(
                $configTable,
                [
                    'scope' => $row['scope'],
                    'scope_id' => (int) $row['scope_id'],
                    'path' => $newPath,
                    'value' => $row['value'],
                ]
            );
        }

        $connection->delete(
            $configTable,
            ['path LIKE ?' => self::LEGACY_CONFIG_PREFIX . '%']
        );

        foreach (self::ACL_RESOURCE_MAP as $legacyResource => $newResource) {
            $connection->update(
                $authorizationRuleTable,
                ['resource_id' => $newResource],
                ['resource_id = ?' => $legacyResource]
            );
        }

        $this->moduleDataSetup->endSetup();
    }
}
