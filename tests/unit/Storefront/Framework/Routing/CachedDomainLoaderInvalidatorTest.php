<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Storefront\Framework\Routing\CachedDomainLoader;
use Shopwell\Storefront\Framework\Routing\CachedDomainLoaderInvalidator;
use Shopwell\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Shopwell\Tests\Unit\Storefront\Theme\MockedCacheInvalidator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CachedDomainLoaderInvalidator::class)]
class CachedDomainLoaderInvalidatorTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [EntityWrittenContainerEvent::class => [['invalidate', 2000]]],
            CachedDomainLoaderInvalidator::getSubscribedEvents()
        );
    }

    public function testInvalidateIsCalledForSalesChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(SalesChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame(
            [CachedDomainLoader::CACHE_KEY, CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY],
            $mockedInvalidator->getForceInvalidatedTags()
        );
    }

    public function testInvalidateIsCalledForThemeSalesChannelWrittenEvent(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ThemeSalesChannelDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame(
            [CachedDomainLoader::CACHE_KEY, CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY],
            $mockedInvalidator->getForceInvalidatedTags()
        );
    }

    public function testInvalidateIsNotCalledForNonSalesChannelWrites(): void
    {
        $context = Context::createDefaultContext();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [], $context)]),
            []
        );

        $mockedInvalidator = new MockedCacheInvalidator();

        $invalidationSubscriber = new CachedDomainLoaderInvalidator(
            $mockedInvalidator
        );

        $invalidationSubscriber->invalidate($event);

        static::assertSame([], $mockedInvalidator->getForceInvalidatedTags());
    }
}
