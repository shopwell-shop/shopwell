<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Organization\Aggregate\OrganizationUnit\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\DataAbstractionLayer\OrganizationUnitIndexer;
use Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;

/**
 * The indexer is what keeps a tree definition's `level`, `path` and `child_count` columns true after
 * a write. Its contract is small but sharp: the ids it hands to the two updaters must be unique and
 * free of empty values, and a partial write must be expanded recursively while a full reindex must
 * not walk the tree it is already walking.
 *
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(OrganizationUnitIndexer::class)]
class OrganizationUnitIndexerTest extends TestCase
{
    public function testTheIndexerIsNamedAndOffersBothUpdaters(): void
    {
        $indexer = $this->indexer();

        static::assertSame('organization_unit.indexer', $indexer->getName());
        static::assertSame([
            OrganizationUnitIndexer::CHILD_COUNT_UPDATER,
            OrganizationUnitIndexer::TREE_UPDATER,
        ], $indexer->getOptions());
    }

    public function testTheIndexerRefusesToBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->indexer()->getDecorated();
    }

    public function testTheTotalIsTheCountOfTheIterator(): void
    {
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetchCount')->willReturn(42);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with(static::isInstanceOf(EntityDefinition::class), null)
            ->willReturn($iterator);

        static::assertSame(42, $this->indexer($iteratorFactory)->getTotal());
    }

    public function testAnExhaustedIteratorEndsTheRunWithNull(): void
    {
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn([]);

        $iteratorFactory = static::createStub(IteratorFactory::class);
        $iteratorFactory->method('createIterator')->willReturn($iterator);

        static::assertNull($this->indexer($iteratorFactory)->iterate(['offset' => 12]));
    }

    public function testIterateWrapsTheBatchAndItsOffsetIntoAMessage(): void
    {
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturn(['id-1', 'id-2']);
        $iterator->method('getOffset')->willReturn(['offset' => 2]);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())
            ->method('createIterator')
            ->with(static::anything(), ['offset' => 0])
            ->willReturn($iterator);

        $message = $this->indexer($iteratorFactory)->iterate(['offset' => 0]);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame(['id-1', 'id-2'], $message->getData());
        static::assertSame(['offset' => 2], $message->getOffset());
    }

    public function testAWriteOfAnotherEntityDoesNotStartTheIndexer(): void
    {
        $event = EntityWrittenContainerEvent::createWithWrittenEvents(
            ['blog' => [new EntityWriteResult('blog-id', [], 'blog', EntityWriteResult::OPERATION_UPDATE)]],
            Context::createDefaultContext(),
            [],
        );

        static::assertNull($this->indexer()->update($event));
    }

    public function testAWriteOfOrganizationUnitsIndexesTheUniqueIds(): void
    {
        $event = EntityWrittenContainerEvent::createWithWrittenEvents(
            [OrganizationUnitDefinition::ENTITY_NAME => [
                new EntityWriteResult('unit-1', [], OrganizationUnitDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
                new EntityWriteResult('unit-2', [], OrganizationUnitDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
                new EntityWriteResult('unit-1', [], OrganizationUnitDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
            ]],
            Context::createDefaultContext(),
            [],
        );

        $message = $this->indexer()->update($event);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame(['unit-1', 'unit-2'], $message->getData());
        static::assertSame($event->getContext(), $message->getContext());
    }

    public function testHandleCleansTheIdsAndExpandsAPartialIndexRecursively(): void
    {
        $context = Context::createDefaultContext();

        $childCountUpdater = $this->createMock(ChildCountUpdater::class);
        $childCountUpdater->expects($this->once())
            ->method('update')
            ->with(OrganizationUnitDefinition::ENTITY_NAME, ['unit-1', 'unit-2'], $context);

        $treeUpdater = $this->createMock(TreeUpdater::class);
        $treeUpdater->expects($this->once())
            ->method('batchUpdate')
            ->with(['unit-1', 'unit-2'], OrganizationUnitDefinition::ENTITY_NAME, $context, true);

        $indexer = $this->indexer(treeUpdater: $treeUpdater, childCountUpdater: $childCountUpdater);

        $indexer->handle(new EntityIndexingMessage(['unit-1', 'unit-1', '', 'unit-2'], null, $context));
    }

    public function testAFullIndexDoesNotWalkTheTreeRecursivelyAgain(): void
    {
        $context = Context::createDefaultContext();

        $treeUpdater = $this->createMock(TreeUpdater::class);
        $treeUpdater->expects($this->once())
            ->method('batchUpdate')
            ->with(['unit-1'], OrganizationUnitDefinition::ENTITY_NAME, $context, false);

        $indexer = $this->indexer(treeUpdater: $treeUpdater);

        $indexer->handle(new EntityIndexingMessage(['unit-1'], null, $context, isFullIndexing: true));
    }

    public function testHandleIgnoresAnEmptyIdListAndANonArrayPayload(): void
    {
        $childCountUpdater = $this->createMock(ChildCountUpdater::class);
        $childCountUpdater->expects($this->never())->method('update');

        $treeUpdater = $this->createMock(TreeUpdater::class);
        $treeUpdater->expects($this->never())->method('batchUpdate');

        $indexer = $this->indexer(treeUpdater: $treeUpdater, childCountUpdater: $childCountUpdater);

        $indexer->handle(new EntityIndexingMessage([], null, Context::createDefaultContext()));
        $indexer->handle(new EntityIndexingMessage('unit-1', null, Context::createDefaultContext()));
    }

    private function indexer(
        ?IteratorFactory $iteratorFactory = null,
        ?TreeUpdater $treeUpdater = null,
        ?ChildCountUpdater $childCountUpdater = null,
    ): OrganizationUnitIndexer {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('getDefinition')->willReturn(static::createStub(EntityDefinition::class));

        return new OrganizationUnitIndexer(
            $iteratorFactory ?? $this->createMock(IteratorFactory::class),
            $repository,
            $childCountUpdater ?? $this->createMock(ChildCountUpdater::class),
            $treeUpdater ?? $this->createMock(TreeUpdater::class),
        );
    }
}
