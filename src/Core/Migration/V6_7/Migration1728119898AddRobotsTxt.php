<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1728119898AddRobotsTxt extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728119898;
    }

    public function update(Connection $connection): void
    {
        $query = 'INSERT INTO system_config SET
                    id = :id,
                    configuration_value = :configValue,
                    configuration_key = :configKey,
                    created_at = :createdAt;';

        $connection->executeStatement($query, [
            'id' => Uuid::randomBytes(),
            'configKey' => 'core.basicInformation.robotsRules',
            'configValue' => json_encode([
                '_value' => <<<'TXT'
                    Disallow: /account/
                    Disallow: /checkout/
                    Disallow: /widgets/
                    Allow: /widgets/cms/
                    Allow: /widgets/menu/offcanvas
                    TXT,
            ]),
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
