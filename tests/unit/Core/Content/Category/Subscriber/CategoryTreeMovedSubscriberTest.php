<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Category\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\Subscriber\CategoryTreeMovedSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryTreeMovedSubscriber::class)]
class CategoryTreeMovedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = CategoryTreeMovedSubscriber::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertArrayHasKey('Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent', $events);
        static::assertSame('detectSalesChannelEntryPoints', $events['Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent']);
    }

    public function testNotRootChange(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->never())->method('sendIndexingMessage');
        $subscriber = new CategoryTreeMovedSubscriber($registry);

        $event = new EntityWrittenContainerEvent(Context::createCLIContext(), new NestedEventCollection(), []);
        $subscriber->detectSalesChannelEntryPoints($event);
    }

    public function testDetectSalesChannelEntryPoints(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->once())->method('sendIndexingMessage')->with(['category.indexer', 'product.indexer']);
        $subscriber = new CategoryTreeMovedSubscriber($registry);

        $event = new EntityWrittenEvent(
            SalesChannelDefinition::ENTITY_NAME,
            [
                new EntityWriteResult('test', ['navigationCategoryId' => 'asd'], SalesChannelDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
            ],
            Context::createCLIContext()
        );

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $subscriber->detectSalesChannelEntryPoints($event);
    }
}
