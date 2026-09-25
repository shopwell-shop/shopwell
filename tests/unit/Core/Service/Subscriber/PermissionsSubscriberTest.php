<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\Event\PermissionsGrantedEvent;
use Shopwell\Core\Service\Event\PermissionsRevokedEvent;
use Shopwell\Core\Service\Permission\PermissionsConsent;
use Shopwell\Core\Service\ServiceLifecycle;
use Shopwell\Core\Service\Subscriber\PermissionsSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PermissionsSubscriber::class)]
class PermissionsSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testReevaluatesServicesOnGrant(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13')
        );
        $event = new PermissionsGrantedEvent($consent, $this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new PermissionsSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testReevaluatesServicesOnRevoke(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13')
        );
        $event = new PermissionsRevokedEvent($consent, $this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        (new PermissionsSubscriber($serviceLifecycle))->reevaluateServices($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = PermissionsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(PermissionsGrantedEvent::class, $events);
        static::assertArrayHasKey(PermissionsRevokedEvent::class, $events);
        static::assertSame('reevaluateServices', $events[PermissionsGrantedEvent::class]);
        static::assertSame('reevaluateServices', $events[PermissionsRevokedEvent::class]);
    }
}
