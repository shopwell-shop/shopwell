<?php

declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopwell\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\Stock\StockStorage;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('framework')]
class ProductIndexerTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private const AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION = 7085;
    private const UPDATE_IDS_CHUNK_SIZE_OF_INDEXER = 50;
    private const MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE = 49;
    private const AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE = 51;

    private ProductIndexer $indexer;

    private Connection&Stub $connectionMock;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->connectionMock = static::createStub(Connection::class);
        $this->messageBus = self::getContainer()->get('messenger.default_bus');

        $this->indexer = new ProductIndexer(
            self::getContainer()->get(IteratorFactory::class),
            self::getContainer()->get('product.repository'),
            $this->connectionMock,
            self::getContainer()->get(VariantListingUpdater::class),
            self::getContainer()->get(ProductCategoryDenormalizer::class),
            self::getContainer()->get(InheritanceUpdater::class),
            self::getContainer()->get(RatingAverageUpdater::class),
            self::getContainer()->get(SearchKeywordUpdater::class),
            self::getContainer()->get(ChildCountUpdater::class),
            self::getContainer()->get(ManyToManyIdFieldUpdater::class),
            self::getContainer()->get(StockStorage::class),
            self::getContainer()->get('event_dispatcher'),
            self::getContainer()->get(CheapestPriceUpdater::class),
            self::getContainer()->get(ProductStreamUpdater::class),
            $this->messageBus,
            Feature::isActive('v6.8.0.0') ? null : self::getContainer()->get(StatesUpdater::class),
            new NativeClock()
        );
    }

    public function testUpdateDoesNotReturnTooBigMessage(): void
    {
        $uuids = $this->getUuids(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION);
        $this->prepareGetChildrenIdsMethod(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);
        $writtenEvent = new EntityWrittenContainerEvent($context, $nestedEvents, []);
        $writtenEvent->setCloned(true);

        $message = $this->indexer->update($writtenEvent);
        static::assertNotNull($message);
        static::assertContains(ProductIndexer::CHILD_COUNT_UPDATER, $message->getSkip());
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInProductIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'ProductIndexer.php';
        });

        $expectedAmountOfMessagesForParentsAndChildren = (int) ceil(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        // Round down because one chunk is returned by the method and not sent in the ProductIndexer directly
        $expectedAmountOfMessagesForUpdatedProducts = (int) floor(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        $expectedAmountOfMessages = $expectedAmountOfMessagesForParentsAndChildren + $expectedAmountOfMessagesForUpdatedProducts;
        static::assertCount($expectedAmountOfMessages, $messagesDispatchedInProductIndexer);
    }

    public function testUpdateIncludesRelatedProductsInSmallMessage(): void
    {
        $productId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $this->connectionMock->method('fetchFirstColumn')->willReturn([$parentId], [$childId]);
        $context = Context::createDefaultContext();

        $message = $this->indexer->update(new EntityWrittenContainerEvent(
            $context,
            $this->prepareEvent($context, [$productId]),
            []
        ));

        static::assertNotNull($message);
        $data = $message->getData();
        static::assertIsArray($data);
        static::assertEqualsCanonicalizing([$productId, $parentId, $childId], array_values($data));
    }

    #[DataProvider('updateCases')]
    public function testUpdate(
        int $numberOfIds,
        int $expectedCountOfMessagesDispatchedInProductIndexer
    ): void {
        $uuids = $this->getUuids($numberOfIds);
        $this->prepareGetChildrenIdsMethod($numberOfIds);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);

        $message = $this->indexer->update(new EntityWrittenContainerEvent($context, $nestedEvents, []));
        static::assertNotNull($message);
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInProductIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'ProductIndexer.php';
        });

        static::assertCount($expectedCountOfMessagesDispatchedInProductIndexer, $messagesDispatchedInProductIndexer);
    }

    public static function updateCases(): \Generator
    {
        yield 'Amount of Uuids so low, that the message bus is only used once for parents and children' => [
            'numberOfIds' => self::MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInProductIndexer' => 1,
        ];
        yield 'Amount of Uuids just so high, that the message bus is used once for to-be-updated products and two times for parents and children' => [
            'numberOfIds' => self::AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInProductIndexer' => 3,
        ];
    }

    /**
     * @return list<string>
     */
    private function getUuids(int $numberOfIds): array
    {
        $uuids = [];
        for ($i = 0; $i < $numberOfIds; ++$i) {
            $uuids[] = Uuid::randomHex();
        }

        return $uuids;
    }

    private function prepareGetChildrenIdsMethod(int $numberOfUuids): void
    {
        $this->connectionMock->method('fetchFirstColumn')->willReturn($this->getUuids($numberOfUuids));
    }

    /**
     * @param list<string> $uuids
     */
    private function prepareEvent(Context $context, array $uuids): NestedEventCollection
    {
        $results = [];
        foreach ($uuids as $uuid) {
            $results[] = new EntityWriteResult(
                $uuid,
                [],
                ProductDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            );
        }

        return new NestedEventCollection([
            new EntityWrittenEvent(
                ProductDefinition::ENTITY_NAME,
                $results,
                $context
            ),
        ]);
    }
}
