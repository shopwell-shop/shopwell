<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Cashier;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierSessionLock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCashierSessionLock::class)]
class PaymentCashierSessionLockTest extends TestCase
{
    public function testLocksTheSessionBeforeWritingAndReturnsTheWriteResult(): void
    {
        $id = Uuid::randomHex();
        $locked = false;
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write): mixed => $write($connection));
        $connection->expects($this->once())->method('fetchOne')->with(
            'SELECT id FROM payment_cashier_session WHERE id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($id)],
            ['id' => ParameterType::BINARY],
        )->willReturnCallback(static function () use (&$locked): string {
            $locked = true;

            return 'row';
        });
        $result = (new PaymentCashierSessionLock($connection))->transaction($id, static function () use (&$locked): string {
            static::assertTrue($locked);

            return 'bound-order';
        });
        static::assertSame('bound-order', $result);
    }

    public function testPropagatesWriteFailureToTheTransaction(): void
    {
        $error = new \RuntimeException('binding failed');
        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $write): mixed => $write($connection));
        $connection->expects($this->once())->method('fetchOne')->willReturn('row');
        $this->expectExceptionObject($error);
        (new PaymentCashierSessionLock($connection))->transaction(Uuid::randomHex(), static fn () => throw $error);
    }
}
