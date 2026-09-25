<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\App\Event\AppActivatedEvent;
use Shopwell\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopwell\Core\Framework\App\Event\AppUpdatedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopwell\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Shopwell\Core\System\SalesChannel\SalesChannelException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileCacheInvalidator::class)]
class SalesChannelFileCacheInvalidatorTest extends TestCase
{
    public function testItInvalidatesSalesChannelFileIdTagsForWrites(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([
                SalesChannelFileCacheInvalidator::buildCacheTag($firstId),
                SalesChannelFileCacheInvalidator::buildCacheTag($secondId),
            ], true);

        $event = new EntityWrittenEvent('sales_channel_file', [
            new EntityWriteResult($firstId, [
                'salesChannelId' => Uuid::randomHex(),
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
            ], 'sales_channel_file', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult($secondId, [], 'sales_channel_file', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext());

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidate($event);
    }

    public function testItInvalidatesSalesChannelFileIdTagsForDeletes(): void
    {
        $id = Uuid::randomHex();
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([SalesChannelFileCacheInvalidator::buildCacheTag($id)], true);

        $event = new EntityDeletedEvent('sales_channel_file', [
            new EntityWriteResult($id, [], 'sales_channel_file', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidate($event);
    }

    public function testItThrowsOnCombinedPrimaryKey(): void
    {
        $event = new EntityWrittenEvent(ProductCategoryDefinition::ENTITY_NAME, [
            new EntityWriteResult(
                ['productId' => Uuid::randomHex(), 'categoryId' => Uuid::randomHex()],
                [],
                ProductCategoryDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ], Context::createDefaultContext());

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $this->expectExceptionObject(SalesChannelException::unexpectedCombinedPrimaryKey(ProductCategoryDefinition::ENTITY_NAME));

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidate($event);
    }

    public function testItBuildsSalesChannelFileIdCacheTag(): void
    {
        static::assertSame('sales-channel-file-example-id', SalesChannelFileCacheInvalidator::buildCacheTag('example-id'));
    }

    public function testItInvalidatesDiscoveryTag(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([SalesChannelFileCacheInvalidator::buildDiscoveryCacheTag()], true);

        (new SalesChannelFileCacheInvalidator($cacheInvalidator))->invalidateDiscovery();
    }

    public function testItSubscribesToTemplateDiscoveryInvalidationEvents(): void
    {
        $events = SalesChannelFileCacheInvalidator::getSubscribedEvents();

        foreach ([
            AppActivatedEvent::class,
            AppDeactivatedEvent::class,
            AppUpdatedEvent::class,
            PluginPostActivateEvent::class,
            PluginPostDeactivateEvent::class,
            PluginPostUpdateEvent::class,
            UpdatePostFinishEvent::class,
        ] as $event) {
            static::assertSame('invalidateDiscovery', $events[$event]);
        }
    }
}
