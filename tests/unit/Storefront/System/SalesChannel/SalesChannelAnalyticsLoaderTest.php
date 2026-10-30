<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Event\StorefrontRenderEvent;
use Shopwell\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelAnalyticsLoader::class)]
class SalesChannelAnalyticsLoaderTest extends TestCase
{
    public function testSalesChannelDoesNotHaveAnalytics(): void
    {
        $event = $this->getEvent(Generator::generateSalesChannelContext());
        $repository = StaticEntityRepository::of(SalesChannelAnalyticsCollection::class);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayNotHasKey('storefrontAnalytics', $event->getParameters());
    }

    public function testSalesChannelHasAnalytics(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($salesChannelContext);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('storefrontAnalytics', $event->getParameters());
        static::assertInstanceOf(SalesChannelAnalyticsEntity::class, $event->getParameters()['storefrontAnalytics']);
    }

    public function testSalesChannelAnalyticsNotFound(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($salesChannelContext);
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('storefrontAnalytics', $event->getParameters());
        static::assertNull($event->getParameters()['storefrontAnalytics']);
    }

    public function testIsNotActiveWithoutAnalytics(): void
    {
        $repository = StaticEntityRepository::of(SalesChannelAnalyticsCollection::class);

        $loader = new SalesChannelAnalyticsLoader($repository);

        static::assertFalse(
            $loader->isActive(Generator::generateSalesChannelContext()),
            'A sales channel without an analytics id is never active. The empty static repository throws if the loader searches it.'
        );
    }

    public function testIsActiveWithActiveAnalytics(): void
    {
        static::assertTrue($this->createLoader(true)->isActive($this->contextWithAnalytics()));
    }

    public function testIsNotActiveWithInactiveAnalytics(): void
    {
        static::assertFalse($this->createLoader(false)->isActive($this->contextWithAnalytics()));
    }

    public function testIsNotActiveWhenTheAnalyticsAreNotFound(): void
    {
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        static::assertFalse((new SalesChannelAnalyticsLoader($repository))->isActive($this->contextWithAnalytics()));
    }

    private function createLoader(bool $active): SalesChannelAnalyticsLoader
    {
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId(Uuid::randomHex());
        $analytics->setActive($active);

        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        return new SalesChannelAnalyticsLoader($repository);
    }

    private function contextWithAnalytics(): SalesChannelContext
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId(Uuid::randomHex());

        return $salesChannelContext;
    }

    private function getEvent(SalesChannelContext $salesChannelContext): StorefrontRenderEvent
    {
        return new StorefrontRenderEvent(
            'test.html.twig',
            [],
            new Request(),
            $salesChannelContext,
        );
    }
}
