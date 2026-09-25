<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Kernel;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaConnection
{
    public static function ensurePrimary(): void
    {
        $connection = Kernel::getConnection();

        if ($connection instanceof PrimaryReadReplicaConnection) {
            $connection->ensureConnectedToPrimary();
        }
    }
}
