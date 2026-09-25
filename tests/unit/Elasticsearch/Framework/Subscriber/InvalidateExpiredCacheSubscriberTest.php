<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\IndexManager;
use Shopwell\Elasticsearch\Framework\Subscriber\InvalidateExpiredCacheSubscriber;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InvalidateExpiredCacheSubscriber::class)]
class InvalidateExpiredCacheSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            InvalidateExpiredCacheRequestEvent::class => 'refreshOpensearchIndices',
        ], InvalidateExpiredCacheSubscriber::getSubscribedEvents());
    }

    public function testRefreshOpensearchIndicesDoesNothingWithoutQueryParam(): void
    {
        $indexManagerMock = $this->createMock(IndexManager::class);
        $indexManagerMock->expects($this->never())->method('refreshIndices');

        $subscriber = new InvalidateExpiredCacheSubscriber($indexManagerMock);
        $event = new InvalidateExpiredCacheRequestEvent(new Request());

        $subscriber->refreshOpensearchIndices($event);
    }

    public function testRefreshOpensearchIndicesRefreshesIndicesWithQueryParam(): void
    {
        $indexManagerMock = $this->createMock(IndexManager::class);
        $indexManagerMock->expects($this->once())->method('refreshIndices');

        $subscriber = new InvalidateExpiredCacheSubscriber($indexManagerMock);
        $request = new Request(query: ['refreshOpenSearch' => 'true']);
        $event = new InvalidateExpiredCacheRequestEvent($request);

        $subscriber->refreshOpensearchIndices($event);
    }
}
