<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemRole\AdminMenuItemRoleDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationDefinition;
use Shopwell\Core\System\AdminMenu\Subscriber\AdminMenuCacheSubscriber;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuCacheSubscriber::class)]
class AdminMenuCacheSubscriberTest extends TestCase
{
    public function testSubscribesToTheWriteEvent(): void
    {
        static::assertSame(
            [EntityWrittenContainerEvent::class => 'invalidateOnWrite'],
            AdminMenuCacheSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function relevantEntityProvider(): iterable
    {
        yield 'menu item' => [AdminMenuItemDefinition::ENTITY_NAME];
        yield 'translation' => [AdminMenuItemTranslationDefinition::ENTITY_NAME];
        yield 'role binding' => [AdminMenuItemRoleDefinition::ENTITY_NAME];
        yield 'acl role' => [AclRoleDefinition::ENTITY_NAME];
    }

    #[DataProvider('relevantEntityProvider')]
    public function testInvalidatesTheCacheWhenRelevantDataChanges(string $entityName): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([AdminMenuCacheSubscriber::CACHE_TAG], true);

        $subscriber = new AdminMenuCacheSubscriber($cacheInvalidator);
        $subscriber->invalidateOnWrite($this->createEvent($entityName));
    }

    public function testIgnoresWritesOfOtherEntities(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->never())->method('invalidate');

        $subscriber = new AdminMenuCacheSubscriber($cacheInvalidator);
        $subscriber->invalidateOnWrite($this->createEvent('product'));
    }

    public function testInvalidatesEvenWhenTheWriteCarriesNoResults(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidate');

        $subscriber = new AdminMenuCacheSubscriber($cacheInvalidator);
        $subscriber->invalidateOnWrite($this->createEvent(AdminMenuItemDefinition::ENTITY_NAME, []));
    }

    /**
     * @param list<EntityWriteResult> $writeResults
     */
    private function createEvent(string $entityName, array $writeResults = []): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent($entityName, $writeResults, $context)]),
            []
        );
    }
}
