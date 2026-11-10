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
#[Package('after-sales')]
class Migration1794302222InitializeOrderReturnDefaults extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794302222;
    }

    public function update(Connection $connection): void
    {
        foreach (['enabled' => true, 'allowGuest' => true, 'windowDays' => 30, 'windowStartsAtDelivery' => true, 'approvalDeadlineHours' => 48, 'autoApprove' => false, 'returnDeadlineDays' => 14, 'restockOnReceive' => true] as $name => $value) {
            $key = 'core.orderReturn.' . $name;
            if ($connection->fetchOne('SELECT id FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL', ['key' => $key])) {
                continue;
            }
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(), 'configuration_key' => $key,
                'configuration_value' => json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
