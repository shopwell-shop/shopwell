<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductIndexer::class)]
class ProductIndexerTest extends TestCase
{
    public function testUpdateSkipChildCountUpdater(): void
    {
        $indexer = new ProductIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(Connection::class),
            static::createStub(VariantListingUpdater::class),
            static::createStub(ProductCategoryDenormalizer::class),
            static::createStub(InheritanceUpdater::class),
            static::createStub(RatingAverageUpdater::class),
            static::createStub(SearchKeywordUpdater::class),
            static::createStub(ChildCountUpdater::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            static::createStub(StockStorage::class),
            static::createStub(EventDispatcher::class),
            static::createStub(CheapestPriceUpdater::class),
            static::createStub(ProductStreamUpdater::class),
            static::createStub(MessageBusInterface::class),
            Feature::isActive('v6.8.0.0') ? null : static::createStub(StatesUpdater::class),
            new NativeClock()
        );

        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, [Uuid::randomHex()]);
        $writtenEvent = new EntityWrittenContainerEvent($context, $nestedEvents, []);
        $writtenEvent->setCloned(true);

        $message = $indexer->update($writtenEvent);
        static::assertNotNull($message);
        static::assertContains(ProductIndexer::CHILD_COUNT_UPDATER, $message->getSkip());
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
