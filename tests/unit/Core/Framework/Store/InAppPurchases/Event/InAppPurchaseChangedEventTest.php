<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\InAppPurchases\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\InAppPurchase\Event\InAppPurchaseChangedEvent;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InAppPurchaseChangedEvent::class)]
class InAppPurchaseChangedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $appId = Uuid::randomHex();
        $extensionName = 'TestApp';
        $purchaseToken = '["test","test2"]';
        $event = new InAppPurchaseChangedEvent($extensionName, $purchaseToken, $appId, Context::createDefaultContext());

        static::assertSame('in_app_purchase.changed', $event->getName());
        static::assertSame('TestApp', $event->getExtensionName());
        static::assertSame('["test","test2"]', $event->getPurchaseToken());
        static::assertSame($appId, $event->getAppId());
        static::assertSame(['purchaseToken' => $purchaseToken], $event->getWebhookPayload());

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }
}
