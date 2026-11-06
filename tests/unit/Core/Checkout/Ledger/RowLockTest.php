<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Ledger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RowLock::class)]
class RowLockTest extends TestCase
{
    private const ACCOUNT_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CUSTOMER_ID = 'b1b2c3d4e5f60718293a4b5c6d7e8f90';

    public function testLockAnswersTheRowWithUuidColumnsAsHex(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT * FROM `customer_wallet` WHERE `customer_id` = :customer_id FOR UPDATE',
                ['customer_id' => Uuid::fromHexToBytes(self::CUSTOMER_ID)],
                ['customer_id' => ParameterType::BINARY]
            )
            ->willReturn([
                'id' => Uuid::fromHexToBytes(self::ACCOUNT_ID),
                'customer_id' => Uuid::fromHexToBytes(self::CUSTOMER_ID),
                'balance' => 12.5,
                'currency' => 'CNY',
            ]);

        $row = (new RowLock($connection))->lock('customer_wallet', ['customer_id' => self::CUSTOMER_ID]);

        static::assertSame([
            'id' => self::ACCOUNT_ID,
            'customer_id' => self::CUSTOMER_ID,
            'balance' => 12.5,
            'currency' => 'CNY',
        ], $row);
    }

    public function testLockAnswersNullWhenTheRowDoesNotExist(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        static::assertNull((new RowLock($connection))->lock('customer_wallet', ['customer_id' => self::CUSTOMER_ID]));
    }

    public function testNullUuidColumnsStayNull(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => Uuid::fromHexToBytes(self::ACCOUNT_ID),
            'tier_id' => null,
        ]);

        $row = (new RowLock($connection))->lock('loyalty_account', ['customer_id' => self::CUSTOMER_ID]);

        static::assertNull($row['tier_id'] ?? null);
    }

    public function testCreateUnlessExistsToleratesAParallelTransactionThatWon(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->willThrowException(new UniqueConstraintViolationException(static::createStub(DriverException::class), null));

        // the loser of the race keeps the transaction alive; the winner's row is locked afterwards
        (new RowLock(static::createStub(Connection::class)))
            ->createUnlessExists($repository, ['id' => self::ACCOUNT_ID], Context::createDefaultContext());
    }

    public function testCreateUnlessExistsWritesTheRow(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('create')
            ->with([['id' => self::ACCOUNT_ID]], Context::createDefaultContext());

        (new RowLock(static::createStub(Connection::class)))
            ->createUnlessExists($repository, ['id' => self::ACCOUNT_ID], Context::createDefaultContext());
    }
}
