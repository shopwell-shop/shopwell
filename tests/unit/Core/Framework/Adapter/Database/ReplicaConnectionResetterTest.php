<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Database\ReplicaConnectionResetter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReplicaConnectionResetter::class)]
class ReplicaConnectionResetterTest extends TestCase
{
    public function testSwitchesBackToReplicaWhenConnectedToPrimary(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('isConnectedToPrimary')->willReturn(true);
        $connection->expects($this->once())->method('ensureConnectedToReplica');

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testKeepsConnectionDuringOpenTransaction(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(1);
        $connection->method('isConnectedToPrimary')->willReturn(true);
        $connection->expects($this->never())->method('ensureConnectedToReplica');

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testDoesNothingWhenAlreadyOnReplica(): void
    {
        $connection = $this->createMock(PrimaryReadReplicaConnection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('isConnectedToPrimary')->willReturn(false);
        $connection->expects($this->never())->method('ensureConnectedToReplica');

        (new ReplicaConnectionResetter($connection))->reset();
    }

    public function testIgnoresConnectionWithoutReplica(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method(static::anything());

        (new ReplicaConnectionResetter($connection))->reset();
    }
}
