<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Api\Controller\CacheController;
use Shopwell\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Shopwell\Elasticsearch\Framework\Indexing\IndexManager;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheController::class)]
class CacheControllerTest extends TestCase
{
    public function testClearCache(): void
    {
        $cacheClearerMock = $this->createMock(CacheClearer::class);
        $cacheClearerMock->expects($this->once())
            ->method('clear');

        $controller = new CacheController(
            $cacheClearerMock,
            static::createStub(CacheInvalidator::class),
            new NullAdapter(),
            static::createStub(EntityIndexerRegistry::class),
            new EventDispatcher()
        );

        $controller->clearCache();
    }

    public function testClearDelayedCache(): void
    {
        $cacheInvalidatorMock = $this->createMock(CacheInvalidator::class);
        $cacheInvalidatorMock->expects($this->once())
            ->method('invalidateExpired');

        $indexManager = $this->createMock(IndexManager::class);
        $indexManager->expects($this->never())
            ->method('refreshIndices');

        $eventDispatcher = new AssertingEventDispatcher($this, [
            InvalidateExpiredCacheRequestEvent::class => 1,
        ]);

        $controller = new CacheController(
            static::createStub(CacheClearer::class),
            $cacheInvalidatorMock,
            new NullAdapter(),
            static::createStub(EntityIndexerRegistry::class),
            $eventDispatcher,
        );

        $controller->clearDelayedCache(new Request());
    }
}
