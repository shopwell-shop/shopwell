<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Stock\StockLoadRequest;
use Shopwell\Core\Content\Product\Stock\StockStorage;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(StockStorage::class)]
class StockStorageTest extends TestCase
{
    public function testLoadDoesNothing(): void
    {
        $ids = new IdsCollection();

        $productIds = $ids->getList(['p-1', 'p-2', 'p-3']);
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $connection = static::createStub(Connection::class);
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $stockStorage = new StockStorage($connection, $dispatcher);

        static::assertSame(
            [],
            $stockStorage->load(new StockLoadRequest(array_values($productIds)), $salesChannelContext)->all()
        );
    }

    public function testEmptyChangesDoNotDispatchEvent(): void
    {
        $connection = static::createStub(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->never())->method('dispatch');

        $stockStorage = new StockStorage($connection, $dispatcher);
        $stockStorage->alter([], Context::createDefaultContext());
    }
}
