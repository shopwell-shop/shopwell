<?php

declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1696262484AddDefaultSendMailOptions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696262484;
    }

    public function update(Connection $connection): void
    {
        $rawConfig = $connection->fetchAssociative(
            'SELECT id, configuration_value FROM system_config WHERE configuration_key = :key',
            ['key' => 'core.mailerSettings.sendMailOptions']
        );

        if ($rawConfig === false) {
            return;
        }

        /** @var array{_value: string} $config */
        $config = json_decode($rawConfig['configuration_value'], true, 512, \JSON_THROW_ON_ERROR);

        $configValue = $config['_value'] ?? '';

        if ($configValue !== '-t') {
            return;
        }

        $connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => '-t -i'], \JSON_THROW_ON_ERROR),
        ], [
            'id' => $rawConfig['id'],
        ]);
    }
}
