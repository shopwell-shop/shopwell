<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\Null\NullGatewayTransportFactory;

/**
 * Seeds the gateway a shop sends over until an administrator configures a provider.
 *
 * The gateway is the null one: a message that reaches it still travels the whole pipeline and is
 * recorded in the send log, it just never leaves the platform. It therefore makes the component
 * usable and observable right after the installation without signing up at a provider first.
 *
 * It carries the lowest priority, so every gateway an administrator adds is picked before this one.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792500003AddDefaultSmsGateway extends MigrationStep
{
    /**
     * The name the transport factory of the null gateway is registered with.
     */
    final public const TECHNICAL_NAME = NullGatewayTransportFactory::TECHNICAL_NAME;

    /**
     * The last value of the routing order - a gateway is picked earlier the lower its priority is.
     */
    final public const PRIORITY = 999;

    public function getCreationTimestamp(): int
    {
        return 1792500003;
    }

    public function update(Connection $connection): void
    {
        if ($this->hasGateway($connection)) {
            return;
        }

        $connection->insert('sms_gateway', [
            'id' => Uuid::randomBytes(),
            'technical_name' => self::TECHNICAL_NAME,
            // the provider column of the administration names it, a name of its own stays empty
            'label' => null,
            'enabled' => true,
            // available in every sales channel, it is the baseline and not an account of a channel
            'sales_channel_id' => null,
            'priority' => self::PRIORITY,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    /**
     * The unique key of the table spans the sales channel and mysql treats two null channels as
     * different values, so the key alone does not keep the seed from running twice.
     */
    private function hasGateway(Connection $connection): bool
    {
        return (bool) $connection->fetchOne(
            'SELECT 1 FROM `sms_gateway` WHERE `technical_name` = :technicalName AND `sales_channel_id` IS NULL',
            ['technicalName' => self::TECHNICAL_NAME]
        );
    }
}
