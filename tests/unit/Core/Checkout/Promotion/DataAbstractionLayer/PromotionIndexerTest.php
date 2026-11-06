<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionExclusionUpdater;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer;
use Shopwell\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopwell\Core\Checkout\Promotion\PromotionCollection;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionIndexer::class)]
class PromotionIndexerTest extends TestCase
{
    public function testSavingAPromotionDoesNotRecountItsRedemptions(): void
    {
        $promotionId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $exclusionUpdater = $this->createMock(PromotionExclusionUpdater::class);
        $exclusionUpdater->expects($this->once())->method('update')->with([$promotionId]);

        $redemptionUpdater = $this->createMock(PromotionRedemptionUpdater::class);
        $redemptionUpdater->expects($this->never())->method('update');

        $indexer = $this->createIndexer(static::createStub(IteratorFactory::class), $exclusionUpdater, $redemptionUpdater);

        $message = $indexer->update(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(
                PromotionDefinition::ENTITY_NAME,
                [new EntityWriteResult($promotionId, ['priority' => 42], PromotionDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
                $context
            ),
        ]), []));

        static::assertNotNull($message);
        $indexer->handle($message);
    }

    public function testRefreshingTheIndexRecountsTheRedemptions(): void
    {
        $promotionId = Uuid::randomHex();

        $query = static::createStub(IterableQuery::class);
        $query->method('fetch')->willReturn([$promotionId]);

        $iteratorFactory = static::createStub(IteratorFactory::class);
        $iteratorFactory->method('createIterator')->willReturn($query);

        $redemptionUpdater = $this->createMock(PromotionRedemptionUpdater::class);
        $redemptionUpdater->expects($this->once())->method('update')->with([$promotionId]);

        $indexer = $this->createIndexer($iteratorFactory, static::createStub(PromotionExclusionUpdater::class), $redemptionUpdater);

        $message = $indexer->iterate(null);

        static::assertNotNull($message);
        $indexer->handle($message);
    }

    private function createIndexer(
        IteratorFactory $iteratorFactory,
        PromotionExclusionUpdater $exclusionUpdater,
        PromotionRedemptionUpdater $redemptionUpdater
    ): PromotionIndexer {
        return new PromotionIndexer(
            $iteratorFactory,
            StaticEntityRepository::of(PromotionCollection::class, definition: new PromotionDefinition()),
            $exclusionUpdater,
            $redemptionUpdater,
            new CollectingEventDispatcher()
        );
    }
}
