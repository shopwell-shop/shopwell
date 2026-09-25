<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopwell\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Shopwell\Core\Framework\Adapter\Session\SessionFactory;
use Shopwell\Core\Framework\Adapter\Session\StatefulFlashBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Cache\CacheCookieEventSubscriber;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheCookieEventSubscriber::class)]
class CacheCookieEventSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                HttpCacheCookieEvent::class => 'passCacheForFlashMessages',
                CacheHashRequiredExtension::NAME . '.post' => 'onRequireCacheHash',
            ],
            CacheCookieEventSubscriber::getSubscribedEvents()
        );
    }

    public function testCacheHashNotRequiredWhenNoFlashMessagesArePresent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(false);

        $sessionFactoryMock = $this->createMock(SessionFactory::class);
        $sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new CacheHashRequiredExtension(
            new Request(),
            static::createStub(SalesChannelContext::class),
            new Cart('test')
        );
        $event->result = false;

        $this->buildSubscriber($sessionFactoryMock)->onRequireCacheHash($event);

        static::assertFalse($event->result);
    }

    public function testCacheHashIsRequiredWhenFlashMessagesArePresent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(true);

        $sessionFactoryMock = $this->createMock(SessionFactory::class);
        $sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new CacheHashRequiredExtension(
            new Request(),
            static::createStub(SalesChannelContext::class),
            new Cart('test')
        );
        $event->result = false;

        $this->buildSubscriber($sessionFactoryMock)->onRequireCacheHash($event);

        static::assertTrue($event->result);
    }

    public function testCacheIsUsedWhenNoFlashMessagesArePresent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(false);
        $flashBagMock->expects($this->once())
            ->method('displayedAnyFlashes')
            ->willReturn(false);

        $sessionFactoryMock = $this->createMock(SessionFactory::class);
        $sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new HttpCacheCookieEvent(
            new Request(),
            static::createStub(SalesChannelContext::class),
            []
        );

        $this->buildSubscriber($sessionFactoryMock)->passCacheForFlashMessages($event);

        static::assertTrue($event->isCacheable);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsUsedWhenNoFlashBagIsAvailable(): void
    {
        $sessionFactoryMock = $this->createMock(SessionFactory::class);
        $sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn(null);

        $event = new HttpCacheCookieEvent(
            new Request(),
            static::createStub(SalesChannelContext::class),
            []
        );

        $this->buildSubscriber($sessionFactoryMock)->passCacheForFlashMessages($event);

        static::assertTrue($event->isCacheable);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsPassedWhenFlashesArePresentOnCacheCookieEvent(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(true);
        $flashBagMock->expects($this->never())
            ->method('displayedAnyFlashes');

        $sessionFactoryMock = $this->createMock(SessionFactory::class);
        $sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new HttpCacheCookieEvent(
            new Request(),
            static::createStub(SalesChannelContext::class),
            []
        );

        $this->buildSubscriber($sessionFactoryMock)->passCacheForFlashMessages($event);

        // when flashes are present, we can't use the cache for the next requests, until the flashes are displayed
        static::assertFalse($event->isCacheable);
        static::assertFalse($event->doNotStore);
    }

    public function testCacheIsNotStoredWhenFlashesAreDisplayedDuringRequest(): void
    {
        $flashBagMock = $this->createMock(StatefulFlashBag::class);
        $flashBagMock->expects($this->once())
            ->method('hasAnyFlashes')
            ->willReturn(false);
        $flashBagMock->expects($this->once())
            ->method('displayedAnyFlashes')
            ->willReturn(true);

        $sessionFactoryMock = $this->createMock(SessionFactory::class);
        $sessionFactoryMock->expects($this->once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $event = new HttpCacheCookieEvent(
            new Request(),
            static::createStub(SalesChannelContext::class),
            []
        );

        $this->buildSubscriber($sessionFactoryMock)->passCacheForFlashMessages($event);

        static::assertTrue($event->isCacheable);
        // the current request should not be stored, but all further requests can use the cache
        static::assertTrue($event->doNotStore);
    }

    private function buildSubscriber(SessionFactory $sessionFactory): CacheCookieEventSubscriber
    {
        return new CacheCookieEventSubscriber($sessionFactory);
    }
}
