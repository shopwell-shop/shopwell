<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopwell\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Shopwell\Elasticsearch\Framework\Indexing\IndexerOffset;
use Shopwell\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopwell\Elasticsearch\Framework\SystemUpdateListener;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemUpdateListener::class)]
class SystemUpdateListenerTest extends TestCase
{
    public function testShouldDoNothingWhenNotSet(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $listener = new SystemUpdateListener(
            static::createStub(AbstractKeyValueStorage::class),
            static::createStub(ElasticsearchIndexer::class),
            $messageBus,
            $mappingUpdater
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(0, $messageBus->getMessages());
    }

    public function testShouldScheduleWithValues(): void
    {
        $messageBus = new CollectingMessageBus();

        $mappingUpdater = $this->createMock(IndexMappingUpdater::class);
        $mappingUpdater
            ->expects($this->once())
            ->method('update');

        $storage = static::createStub(AbstractKeyValueStorage::class);
        $storage
            ->method('get')
            ->willReturn(['*']);

        $message = static::createStub(ElasticsearchIndexingMessage::class);
        $message->method('getOffset')
            ->willReturn(static::createStub(IndexerOffset::class));

        $indexer = static::createStub(ElasticsearchIndexer::class);
        $indexer
            ->method('iterate')
            ->willReturnCallback(static function ($offset) use ($message) {
                return $offset === null
                    ? $message
                    : null;
            });

        $listener = new SystemUpdateListener(
            $storage,
            $indexer,
            $messageBus,
            $mappingUpdater
        );

        $listener(static::createStub(UpdatePostFinishEvent::class));

        static::assertCount(1, $messageBus->getMessages());
    }
}
