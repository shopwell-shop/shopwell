<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1730911642MoveNamespaceOfShowZipcodeInFrontOfCityConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1730911642;
    }

    public function update(Connection $connection): void
    {
        $connection->update('system_config', [
            'configuration_key' => 'core.loginRegistration.showZipcodeInFrontOfCity',
        ], [
            'configuration_key' => 'core.address.showZipcodeInFrontOfCity',
        ]);
    }
}
