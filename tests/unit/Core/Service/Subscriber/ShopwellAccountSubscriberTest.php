<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Event\ShopwellAccountLoginEvent;
use Shopwell\Core\Framework\Store\Event\ShopwellAccountLogoutEvent;
use Shopwell\Core\Service\ServiceLifecycle;
use Shopwell\Core\Service\Subscriber\ShopwellAccountSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwellAccountSubscriber::class)]
class ShopwellAccountSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testReevaluatesServicesOnLogin(): void
    {
        $event = new ShopwellAccountLoginEvent($this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new ShopwellAccountSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testReevaluatesServicesOnLogout(): void
    {
        $event = new ShopwellAccountLogoutEvent($this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new ShopwellAccountSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = ShopwellAccountSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(ShopwellAccountLoginEvent::class, $events);
        static::assertArrayHasKey(ShopwellAccountLogoutEvent::class, $events);
        static::assertSame('reevaluateServices', $events[ShopwellAccountLoginEvent::class]);
        static::assertSame('reevaluateServices', $events[ShopwellAccountLogoutEvent::class]);
    }
}
