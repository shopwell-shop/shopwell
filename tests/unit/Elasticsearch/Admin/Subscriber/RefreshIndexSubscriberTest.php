<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopwell\Elasticsearch\Admin\AdminSearchRegistry;
use Shopwell\Elasticsearch\Admin\Subscriber\RefreshIndexSubscriber;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(RefreshIndexSubscriber::class)]
class RefreshIndexSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertArrayHasKey(RefreshIndexEvent::class, RefreshIndexSubscriber::getSubscribedEvents());
    }

    public function testHandedWithSkipOption(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('iterate')->with(new AdminIndexingBehavior(false, ['product']));

        $subscriber = new RefreshIndexSubscriber($registry);
        $subscriber->handled(new RefreshIndexEvent(false, ['product']));
    }

    public function testHandedWithOnlyOption(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('iterate')->with(new AdminIndexingBehavior(false, [], ['product']));

        $subscriber = new RefreshIndexSubscriber($registry);
        $subscriber->handled(new RefreshIndexEvent(false, [], ['product']));
    }
}
