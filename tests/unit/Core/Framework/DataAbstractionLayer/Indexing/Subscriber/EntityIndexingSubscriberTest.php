<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\EntityIndexingSubscriber;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityIndexingSubscriber::class)]
class EntityIndexingSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertArrayHasKey(EntityWrittenContainerEvent::class, EntityIndexingSubscriber::getSubscribedEvents());
    }

    public function testRefresh(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->once())->method('refresh');

        $subscriber = new EntityIndexingSubscriber($registry);
        $subscriber->refreshIndex(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));
    }
}
