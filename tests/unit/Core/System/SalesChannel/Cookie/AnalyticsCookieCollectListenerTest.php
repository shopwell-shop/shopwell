<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopwell\Core\Content\Cookie\Service\CookieProvider;
use Shopwell\Core\Content\Cookie\Struct\CookieGroup;
use Shopwell\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopwell\Core\System\SalesChannel\Cookie\AnalyticsCookieCollectListener;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsCookieCollectListener::class)]
class AnalyticsCookieCollectListenerTest extends TestCase
{
    private AnalyticsCookieCollectListener $listener;

    /**
     * @var StaticEntityRepository<SalesChannelAnalyticsCollection>
     */
    private StaticEntityRepository $analyticsRepo;

    protected function setUp(): void
    {
        $this->analyticsRepo = new StaticEntityRepository([]);
        $this->listener = new AnalyticsCookieCollectListener($this->analyticsRepo);
    }

    public function testSalesChannelHasNoAnalyticsId(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('test-id');

        $context = Generator::generateSalesChannelContext(salesChannel: $salesChannel);

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $event = new CookieGroupCollectEvent(new CookieGroupCollection([$statisticalGroup, $marketingGroup]), new Request(), $context);

        $this->listener->__invoke($event);

        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled'));
        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled'));
    }

    public function testSalesChannelNeedsToLoadAnalyticsButIsNotActive(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setAnalyticsId('analytics-id');
        $context = Generator::generateSalesChannelContext(salesChannel: $salesChannel);

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $event = new CookieGroupCollectEvent(new CookieGroupCollection([$statisticalGroup, $marketingGroup]), new Request(), $context);

        $analyticsEntity = $this->createChannelAnalyticsEntity(active: false);

        $this->analyticsRepo->addSearch(new SalesChannelAnalyticsCollection([$analyticsEntity]));

        $this->listener->__invoke($event);

        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled'));
        static::assertNull($event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled'));
    }

    public function testStatisticalAndMarketingCookieGroupsNotPresent(): void
    {
        $context = $this->createSalesChannelContext();

        $cookieGroupCollection = new CookieGroupCollection([new CookieGroup('test')]);

        $event = new CookieGroupCollectEvent($cookieGroupCollection, new Request(), $context);
        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testCookiesAreAdded(): void
    {
        $context = $this->createSalesChannelContext();

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $cookieGroupCollection = new CookieGroupCollection([$statisticalGroup, $marketingGroup]);

        $event = new CookieGroupCollectEvent($cookieGroupCollection, new Request(), $context);

        $this->listener->__invoke($event);

        $adsCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL)?->getEntries()?->get('google-analytics-enabled');
        static::assertNotNull($adsCookie);

        $adsCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING)?->getEntries()?->get('google-ads-enabled');
        static::assertNotNull($adsCookie);
    }

    private function createChannelAnalyticsEntity(bool $active = true): SalesChannelAnalyticsEntity
    {
        $analyticsEntity = new SalesChannelAnalyticsEntity();
        $analyticsEntity->setId('analytics-id');
        $analyticsEntity->setActive($active);

        return $analyticsEntity;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $analyticsEntity = $this->createChannelAnalyticsEntity();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setAnalyticsId($analyticsEntity->getId());
        $salesChannel->setAnalytics($analyticsEntity);

        return Generator::generateSalesChannelContext(salesChannel: $salesChannel);
    }
}
