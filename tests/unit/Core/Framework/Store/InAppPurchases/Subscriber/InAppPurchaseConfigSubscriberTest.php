<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\InAppPurchases\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopwell\Core\Framework\Store\InAppPurchase\Subscriber\InAppPurchaseConfigSubscriber;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseConfigSubscriber::class)]
class InAppPurchaseConfigSubscriberTest extends TestCase
{
    private RequestStack $requestStack;

    private InAppPurchaseUpdater&MockObject $iapUpdater;

    private InAppPurchaseConfigSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->iapUpdater = $this->createMock(InAppPurchaseUpdater::class);

        $this->subscriber = new InAppPurchaseConfigSubscriber(
            $this->requestStack,
            $this->iapUpdater,
        );
    }

    public function testIsSubscribedToSystemConfigChangedEvents(): void
    {
        $this->iapUpdater->expects($this->never())->method('update');

        static::assertSame([
            SystemConfigChangedEvent::class => 'updateIapKey',
            SystemConfigDomainLoadedEvent::class => 'removeIapInformationFromDomain',
        ], InAppPurchaseConfigSubscriber::getSubscribedEvents());
    }

    public function testUpdateIapKeyOnlyUsesStoreToken(): void
    {
        $this->iapUpdater->expects($this->never())->method('update');

        $event = new SystemConfigChangedEvent('random.config.key', 'whatever', null);
        $this->subscriber->updateIapKey($event);
    }

    public function testUpdateIapKeyOnlyUsesActualToken(): void
    {
        $this->iapUpdater->expects($this->never())->method('update');

        $event = new SystemConfigChangedEvent('core.store.shopSecret', null, null);
        $this->subscriber->updateIapKey($event);
    }

    public function testUpdateIapKeyUpdatesOnStoreSecretSet(): void
    {
        $this->iapUpdater->expects($this->once())->method('update');

        $event = new SystemConfigChangedEvent('core.store.shopSecret', 'secret', null);
        $this->subscriber->updateIapKey($event);
    }

    public function testUpdateIapKeyUsesAdminContext(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(null));
        $this->requestStack->push(new Request(attributes: [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => $context]));

        $this->iapUpdater
            ->expects($this->once())
            ->method('update')
            ->with($context);

        $event = new SystemConfigChangedEvent('core.store.shopSecret', 'secret', null);
        $this->subscriber->updateIapKey($event);
    }

    public function testRemoveIapInformationFromDomainOnlyActsOnStoreDomain(): void
    {
        $this->iapUpdater->expects($this->never())->method('update');

        $event = new SystemConfigDomainLoadedEvent('some.domain.', ['core.store.iapKey' => 'key'], false, null);
        $this->subscriber->removeIapInformationFromDomain($event);

        static::assertSame(['core.store.iapKey' => 'key'], $event->getConfig());
    }

    public function testRemoveIapInformationCleansDomain(): void
    {
        $this->iapUpdater->expects($this->never())->method('update');

        $event = new SystemConfigDomainLoadedEvent('core.store.', ['core.store.iapKey' => 'key'], false, null);
        $this->subscriber->removeIapInformationFromDomain($event);

        static::assertSame([], $event->getConfig());
    }
}
